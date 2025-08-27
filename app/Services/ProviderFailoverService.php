<?php

namespace App\Services;

use App\Models\ProviderConfig;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ProviderFailoverService
{
    private ProviderHealthService $healthService;
    
    public function __construct(ProviderHealthService $healthService)
    {
        $this->healthService = $healthService;
    }
    
    /**
     * Get the best available provider for a message
     */
    public function getBestProvider(Message $message): ?ProviderConfig
    {
        $providers = $this->getAvailableProviders(
            $message->project_id,
            $message->tenant_id,
            $message->channel
        );
        
        if ($providers->isEmpty()) {
            Log::warning('No available providers found', [
                'message_id' => $message->message_id,
                'project_id' => $message->project_id,
                'tenant_id' => $message->tenant_id,
                'channel' => $message->channel
            ]);
            return null;
        }
        
        // Get the best provider using smart selection
        return $this->selectBestProvider($providers, $message);
    }
    
    /**
     * Get fallback providers for a message (excluding failed provider)
     */
    public function getFallbackProviders(Message $message, string $excludeProviderId = null): Collection
    {
        $providers = $this->getAvailableProviders(
            $message->project_id,
            $message->tenant_id,
            $message->channel
        );
        
        if ($excludeProviderId) {
            $providers = $providers->filter(function ($provider) use ($excludeProviderId) {
                return $provider->id !== $excludeProviderId;
            });
        }
        
        return $this->sortProvidersByPreference($providers, $message);
    }
    
    /**
     * Try sending message with automatic failover
     */
    public function sendWithFailover(Message $message, array $renderedContent, int $maxAttempts = 3): array
    {
        $attempts = [];
        $providers = $this->getAvailableProviders(
            $message->project_id,
            $message->tenant_id,
            $message->channel
        );
        
        if ($providers->isEmpty()) {
            return [
                'success' => false,
                'error' => 'No available providers',
                'attempts' => []
            ];
        }
        
        $sortedProviders = $this->sortProvidersByPreference($providers, $message);
        $attemptCount = 0;
        
        foreach ($sortedProviders as $provider) {
            if ($attemptCount >= $maxAttempts) {
                break;
            }
            
            $attemptCount++;
            
            // Check if provider is available (circuit breaker)
            if (!$this->healthService->isProviderAvailable($provider->id)) {
                $attempts[] = [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->provider,
                    'attempt' => $attemptCount,
                    'status' => 'skipped',
                    'reason' => 'Circuit breaker open'
                ];
                continue;
            }
            
            try {
                // Get the appropriate adapter
                $adapter = $this->getAdapter($message->channel);
                
                // Attempt to send
                $response = $adapter->send($message, $renderedContent, $provider);
                
                $attempts[] = [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->provider,
                    'attempt' => $attemptCount,
                    'status' => $response->isSuccess() ? 'success' : 'failed',
                    'provider_message_id' => $response->getProviderMessageId(),
                    'error' => $response->getError(),
                    'response_time' => $response->getResponseTime()
                ];
                
                if ($response->isSuccess()) {
                    // Record success and return
                    $this->healthService->recordSuccess($provider->id);
                    
                    Log::info('Message sent successfully', [
                        'message_id' => $message->message_id,
                        'provider_id' => $provider->id,
                        'provider_name' => $provider->provider,
                        'attempt' => $attemptCount,
                        'provider_message_id' => $response->getProviderMessageId()
                    ]);
                    
                    return [
                        'success' => true,
                        'provider' => $provider,
                        'response' => $response,
                        'attempts' => $attempts
                    ];
                } else {
                    // Record failure and try next provider
                    $this->healthService->recordFailure($provider->id, $response->getError());
                    
                    Log::warning('Provider failed, trying next', [
                        'message_id' => $message->message_id,
                        'provider_id' => $provider->id,
                        'provider_name' => $provider->provider,
                        'error' => $response->getError(),
                        'attempt' => $attemptCount
                    ]);
                }
                
            } catch (\Exception $e) {
                $this->healthService->recordFailure($provider->id, $e->getMessage());
                
                $attempts[] = [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->provider,
                    'attempt' => $attemptCount,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
                
                Log::error('Provider exception, trying next', [
                    'message_id' => $message->message_id,
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->provider,
                    'error' => $e->getMessage(),
                    'attempt' => $attemptCount
                ]);
            }
        }
        
        // All providers failed
        Log::error('All providers failed for message', [
            'message_id' => $message->message_id,
            'total_attempts' => $attemptCount,
            'attempts' => $attempts
        ]);
        
        return [
            'success' => false,
            'error' => 'All providers failed',
            'attempts' => $attempts
        ];
    }
    
    /**
     * Get available providers for project/tenant/channel
     */
    private function getAvailableProviders(string $projectId, string $tenantId, string $channel): Collection
    {
        return ProviderConfig::where('project_id', $projectId)
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->where('enabled', true)
            ->get();
    }
    
    /**
     * Select the best provider using smart algorithm
     */
    private function selectBestProvider(Collection $providers, Message $message): ?ProviderConfig
    {
        $rankedProviders = $this->rankProviders($providers, $message);
        
        return $rankedProviders->first();
    }
    
    /**
     * Sort providers by preference (priority, health, cost, etc.)
     */
    private function sortProvidersByPreference(Collection $providers, Message $message): Collection
    {
        return $this->rankProviders($providers, $message);
    }
    
    /**
     * Rank providers using multiple criteria
     */
    private function rankProviders(Collection $providers, Message $message): Collection
    {
        return $providers->map(function ($provider) use ($message) {
            $health = $this->healthService->getProviderHealth($provider->id);
            $score = $this->calculateProviderScore($provider, $health, $message);
            
            return [
                'provider' => $provider,
                'score' => $score,
                'health' => $health
            ];
        })
        ->sortByDesc('score')
        ->pluck('provider');
    }
    
    /**
     * Calculate provider score based on multiple factors
     */
    private function calculateProviderScore(ProviderConfig $provider, array $health, Message $message): float
    {
        $score = 0;
        
        // Priority score (lower priority number = higher score)
        $priorityScore = (11 - $provider->priority) * 10; // Max 100 points
        $score += $priorityScore;
        
        // Health score
        $healthScore = $health['health_score'] ?? 50;
        $score += $healthScore;
        
        // Availability (circuit breaker)
        if (!$health['is_available']) {
            $score -= 200; // Heavy penalty for unavailable providers
        }
        
        // Response time score (faster = better)
        $avgResponseTime = $health['average_response_time'] ?? 1000;
        $responseTimeScore = max(0, 50 - ($avgResponseTime / 100)); // Max 50 points
        $score += $responseTimeScore;
        
        // Cost efficiency (if cost tracking is enabled)
        if (isset($provider->cost_tracking['cost_per_message'])) {
            $costPerMessage = $provider->cost_tracking['cost_per_message'];
            // Lower cost = higher score (inverse relationship)
            $costScore = max(0, 25 - ($costPerMessage * 1000)); // Max 25 points
            $score += $costScore;
        }
        
        // Message priority boost
        if ($message->priority === 'urgent') {
            // For urgent messages, prefer more reliable providers
            $score += $healthScore * 0.5;
        } elseif ($message->priority === 'low') {
            // For low priority, prefer cost-effective providers
            if (isset($provider->cost_tracking['cost_per_message'])) {
                $costPerMessage = $provider->cost_tracking['cost_per_message'];
                $score += max(0, 50 - ($costPerMessage * 2000));
            }
        }
        
        // Recent failure penalty
        $recentFailures = $health['failure_count'] ?? 0;
        $score -= $recentFailures * 5;
        
        return max(0, $score);
    }
    
    /**
     * Get load balancing statistics
     */
    public function getLoadBalancingStats(string $projectId, string $tenantId, string $channel): array
    {
        $providers = $this->getAvailableProviders($projectId, $tenantId, $channel);
        
        $stats = [];
        foreach ($providers as $provider) {
            $health = $this->healthService->getProviderHealth($provider->id);
            
            $stats[] = [
                'provider_id' => $provider->id,
                'provider_name' => $provider->provider,
                'priority' => $provider->priority,
                'is_available' => $health['is_available'],
                'circuit_state' => $health['circuit_state'],
                'health_score' => $health['health_score'],
                'success_rate' => $health['success_rate'],
                'average_response_time' => $health['average_response_time'],
                'failure_count' => $health['failure_count'],
                'last_success' => $health['last_success'],
                'last_failure' => $health['last_failure']
            ];
        }
        
        return $stats;
    }
    
    /**
     * Force failover to next available provider
     */
    public function forceFailover(string $providerId, string $reason = 'Manual failover'): bool
    {
        try {
            // Record failure to trigger circuit breaker
            $this->healthService->recordFailure($providerId, $reason);
            
            Log::info('Forced failover executed', [
                'provider_id' => $providerId,
                'reason' => $reason
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to execute forced failover', [
                'provider_id' => $providerId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get appropriate adapter for channel
     */
    private function getAdapter(string $channel)
    {
        return match($channel) {
            'email' => app(\App\Services\Adapters\EmailAdapter::class),
            'sms' => app(\App\Services\Adapters\SmsAdapter::class),
            'whatsapp' => app(\App\Services\Adapters\WhatsAppAdapter::class),
            default => throw new \Exception('Unsupported channel: ' . $channel)
        };
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\ProviderHealthService;

class ProviderFailoverService
{
    protected $healthService;
    protected $cachePrefix = 'failover:';
    protected $failureThreshold = 5; // failures before marking as down
    protected $recoveryTime = 300; // seconds before retry

    public function __construct(ProviderHealthService $healthService)
    {
        $this->healthService = $healthService;
    }

    /**
     * Select best available provider for channel
     */
    public function selectProvider($channel)
    {
        $providers = $this->getProvidersForChannel($channel);
        
        // Filter out failed providers
        $availableProviders = collect($providers)->filter(function ($provider) {
            return $this->isProviderAvailable($provider);
        });

        if ($availableProviders->isEmpty()) {
            // If no providers available, try the least recently failed one
            $provider = $this->getLeastRecentlyFailedProvider($providers);
            
            if ($provider) {
                Log::warning('All providers failed, using least recently failed', [
                    'channel' => $channel,
                    'provider' => $provider,
                ]);
                return $provider;
            }
            
            throw new \Exception("No available providers for channel: {$channel}");
        }

        // Select provider based on priority and health
        return $this->selectBestProvider($availableProviders->all());
    }

    /**
     * Check if provider is available
     */
    public function isProviderAvailable($provider)
    {
        // Check failure count
        $failureCount = $this->getProviderFailureCount($provider);
        if ($failureCount >= $this->failureThreshold) {
            // Check if recovery time has passed
            $lastFailure = $this->getLastFailureTime($provider);
            if ($lastFailure && (time() - $lastFailure) < $this->recoveryTime) {
                return false;
            }
            
            // Reset failure count after recovery time
            $this->resetProviderFailures($provider);
        }

        // Check health status
        return $this->healthService->getProviderStatus($provider);
    }

    /**
     * Record provider failure
     */
    public function recordProviderFailure($provider, $error)
    {
        $failureCount = $this->incrementProviderFailureCount($provider);
        $this->setLastFailureTime($provider, time());

        Log::warning('Provider failure recorded', [
            'provider' => $provider,
            'error' => $error,
            'failure_count' => $failureCount,
        ]);

        // If failure threshold reached, notify
        if ($failureCount >= $this->failureThreshold) {
            $this->notifyProviderDown($provider, $failureCount);
        }
    }

    /**
     * Record successful provider operation
     */
    public function recordProviderSuccess($provider)
    {
        $this->resetProviderFailures($provider);
        
        Log::debug('Provider success recorded', [
            'provider' => $provider,
        ]);
    }

    /**
     * Get providers for specific channel
     */
    protected function getProvidersForChannel($channel)
    {
        $channelProviders = config("notification.channels.{$channel}.providers", []);
        
        if (empty($channelProviders)) {
            // Fallback to all providers that support the channel
            $allProviders = config('notification.providers', []);
            $channelProviders = [];
            
            foreach ($allProviders as $provider => $config) {
                $supportedChannels = $config['channels'] ?? [];
                if (in_array($channel, $supportedChannels)) {
                    $channelProviders[] = $provider;
                }
            }
        }

        return $channelProviders;
    }

    /**
     * Select best provider from available options
     */
    protected function selectBestProvider($providers)
    {
        // Get provider priorities and health scores
        $scored = collect($providers)->map(function ($provider) {
            $config = config("notification.providers.{$provider}", []);
            $priority = $config['priority'] ?? 50;
            $health = $this->healthService->checkProvider($provider);
            $responseTime = $health['response_time'] ?? 1000;
            
            // Calculate score (higher is better)
            $score = $priority + (1000 / max($responseTime, 1));
            
            return [
                'provider' => $provider,
                'score' => $score,
                'priority' => $priority,
                'response_time' => $responseTime,
            ];
        })->sortByDesc('score');

        $selected = $scored->first();
        
        Log::debug('Provider selected', [
            'selected' => $selected['provider'],
            'score' => $selected['score'],
            'available_providers' => $scored->pluck('provider')->all(),
        ]);

        return $selected['provider'];
    }

    /**
     * Get least recently failed provider
     */
    protected function getLeastRecentlyFailedProvider($providers)
    {
        $oldestFailure = null;
        $selectedProvider = null;

        foreach ($providers as $provider) {
            $lastFailure = $this->getLastFailureTime($provider);
            
            if ($lastFailure === null) {
                // Never failed, use this one
                return $provider;
            }
            
            if ($oldestFailure === null || $lastFailure < $oldestFailure) {
                $oldestFailure = $lastFailure;
                $selectedProvider = $provider;
            }
        }

        return $selectedProvider;
    }

    /**
     * Get provider failure count
     */
    protected function getProviderFailureCount($provider)
    {
        return Cache::get($this->cachePrefix . "failures:{$provider}", 0);
    }

    /**
     * Increment provider failure count
     */
    protected function incrementProviderFailureCount($provider)
    {
        $key = $this->cachePrefix . "failures:{$provider}";
        $count = Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addHours(24));
        return $count;
    }

    /**
     * Reset provider failures
     */
    protected function resetProviderFailures($provider)
    {
        Cache::forget($this->cachePrefix . "failures:{$provider}");
        Cache::forget($this->cachePrefix . "last_failure:{$provider}");
    }

    /**
     * Get last failure time
     */
    protected function getLastFailureTime($provider)
    {
        return Cache::get($this->cachePrefix . "last_failure:{$provider}");
    }

    /**
     * Set last failure time
     */
    protected function setLastFailureTime($provider, $timestamp)
    {
        Cache::put($this->cachePrefix . "last_failure:{$provider}", $timestamp, now()->addHours(24));
    }

    /**
     * Notify that provider is down
     */
    protected function notifyProviderDown($provider, $failureCount)
    {
        // This would typically send an alert to administrators
        Log::critical('Provider marked as down', [
            'provider' => $provider,
            'failure_count' => $failureCount,
            'threshold' => $this->failureThreshold,
        ]);

        // You could integrate with your alerting service here
        // app(AlertService::class)->sendProviderFailureAlert($provider, $failureCount);
    }

    /**
     * Get failover status for all providers
     */
    public function getFailoverStatus()
    {
        $providers = config('notification.providers', []);
        $status = [];

        foreach (array_keys($providers) as $provider) {
            $status[$provider] = [
                'available' => $this->isProviderAvailable($provider),
                'failure_count' => $this->getProviderFailureCount($provider),
                'last_failure' => $this->getLastFailureTime($provider),
                'health' => $this->healthService->checkProvider($provider),
            ];
        }

        return $status;
    }

    /**
     * Force provider recovery
     */
    public function forceProviderRecovery($provider)
    {
        $this->resetProviderFailures($provider);
        
        Log::info('Provider recovery forced', [
            'provider' => $provider,
        ]);

        return $this->isProviderAvailable($provider);
    }
}

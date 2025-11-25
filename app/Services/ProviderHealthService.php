<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProviderHealthService
{
    protected $providers;
    protected $cachePrefix = 'health:';
    protected $cacheTtl = 300; // 5 minutes

    public function __construct()
    {
        $this->providers = config('notification.providers', []);
    }

    /**
     * Check health of all providers
     */
    public function checkAllProviders()
    {
        $results = [];
        
        foreach ($this->providers as $providerName => $config) {
            $results[$providerName] = $this->checkProvider($providerName);
        }

        return $results;
    }

    /**
     * Check health of specific provider
     */
    public function checkProvider($providerName)
    {
        $cacheKey = $this->cachePrefix . $providerName;

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($providerName) {
            return $this->performHealthCheck($providerName);
        });
    }

    /**
     * Perform actual health check
     */
    protected function performHealthCheck($providerName)
    {
        $startTime = microtime(true);
        $config = $this->providers[$providerName] ?? null;

        if (!$config) {
            return [
                'healthy' => false,
                'error' => 'Provider not configured',
                'response_time' => 0,
                'checked_at' => now()->toISOString(),
            ];
        }

        try {
            $result = match($providerName) {
                'twilio' => $this->checkTwilio($config),
                'whatsapp' => $this->checkWhatsApp($config),
                'sendgrid' => $this->checkSendGrid($config),
                'resend' => $this->checkResend($config),
                'mailgun' => $this->checkMailgun($config),
                default => $this->checkGenericHttp($config),
            };

            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            return array_merge($result, [
                'response_time' => round($responseTime, 2),
                'checked_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'response_time' => round($responseTime, 2),
                'checked_at' => now()->toISOString(),
            ];
        }
    }

    /**
     * Check Twilio health
     */
    protected function checkTwilio($config)
    {
        $accountSid = $config['account_sid'];
        $authToken = $config['auth_token'];

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->timeout(10)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}.json");

        if ($response->successful()) {
            return [
                'healthy' => true,
                'status' => $response->json('status'),
                'account_status' => $response->json('status'),
            ];
        } else {
            return [
                'healthy' => false,
                'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
            ];
        }
    }

    /**
     * Check WhatsApp Business API health
     */
    protected function checkWhatsApp($config)
    {
        $accessToken = $config['access_token'];
        $phoneNumberId = $config['phone_number_id'];

        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->get("https://graph.facebook.com/v17.0/{$phoneNumberId}");

        if ($response->successful()) {
            return [
                'healthy' => true,
                'phone_number' => $response->json('display_phone_number'),
                'verified' => $response->json('verified_name'),
            ];
        } else {
            return [
                'healthy' => false,
                'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
            ];
        }
    }

    /**
     * Check SendGrid health
     */
    protected function checkSendGrid($config)
    {
        $apiKey = $config['api_key'];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])
        ->timeout(10)
        ->get('https://api.sendgrid.com/v3/user/account');

        if ($response->successful()) {
            return [
                'healthy' => true,
                'account_type' => $response->json('type'),
                'reputation' => $response->json('reputation'),
            ];
        } else {
            return [
                'healthy' => false,
                'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
            ];
        }
    }

    /**
     * Check Resend health
     */
    protected function checkResend($config)
    {
        $apiKey = $config['api_key'];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])
        ->timeout(10)
        ->get('https://api.resend.com/api-keys');

        if ($response->successful()) {
            return [
                'healthy' => true,
                'status' => 'OK',
            ];
        } else {
            return [
                'healthy' => false,
                'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
            ];
        }
    }

    /**
     * Check Mailgun health
     */
    protected function checkMailgun($config)
    {
        $apiKey = $config['secret'];
        $domain = $config['domain'];

        $response = Http::withBasicAuth('api', $apiKey)
            ->timeout(10)
            ->get("https://api.mailgun.net/v3/{$domain}");

        if ($response->successful()) {
            return [
                'healthy' => true,
                'domain' => $response->json('domain.name'),
                'state' => $response->json('domain.state'),
            ];
        } else {
            return [
                'healthy' => false,
                'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
            ];
        }
    }

    /**
     * Generic HTTP health check
     */
    protected function checkGenericHttp($config)
    {
        $healthUrl = $config['health_url'] ?? $config['base_url'] . '/health';

        $response = Http::timeout(10)->get($healthUrl);

        if ($response->successful()) {
            return [
                'healthy' => true,
                'status' => 'OK',
            ];
        } else {
            return [
                'healthy' => false,
                'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
            ];
        }
    }

    /**
     * Get provider health status
     */
    public function getProviderStatus($providerName)
    {
        $health = $this->checkProvider($providerName);
        return $health['healthy'] ?? false;
    }

    /**
     * Get overall system health
     */
    public function getOverallHealth()
    {
        $providerHealth = $this->checkAllProviders();
        $healthyCount = 0;
        $totalCount = count($providerHealth);

        foreach ($providerHealth as $health) {
            if ($health['healthy'] ?? false) {
                $healthyCount++;
            }
        }

        $healthPercentage = $totalCount > 0 ? ($healthyCount / $totalCount) * 100 : 0;

        return [
            'healthy' => $healthPercentage >= 50, // At least 50% of providers must be healthy
            'health_percentage' => round($healthPercentage, 2),
            'healthy_providers' => $healthyCount,
            'total_providers' => $totalCount,
            'providers' => $providerHealth,
            'checked_at' => now()->toISOString(),
        ];
    }

    /**
     * Force refresh provider health
     */
    public function refreshProvider($providerName)
    {
        $cacheKey = $this->cachePrefix . $providerName;
        Cache::forget($cacheKey);
        return $this->checkProvider($providerName);
    }

    /**
     * Clear all health cache
     */
    public function clearHealthCache()
    {
        foreach (array_keys($this->providers) as $providerName) {
            Cache::forget($this->cachePrefix . $providerName);
        }
    }

    /**
     * Log health check results
     */
    public function logHealthResults()
    {
        $results = $this->checkAllProviders();
        
        foreach ($results as $provider => $health) {
            if ($health['healthy']) {
                Log::info("Provider {$provider} is healthy", $health);
            } else {
                Log::warning("Provider {$provider} is unhealthy", $health);
            }
        }

        return $results;
    }

    /**
     * Get health history for provider
     */
    public function getHealthHistory($providerName, $hours = 24)
    {
        return DB::table('provider_health_logs')
            ->where('provider', $providerName)
            ->where('checked_at', '>=', now()->subHours($hours))
            ->orderBy('checked_at', 'desc')
            ->get();
    }

    /**
     * Store health check result in database
     */
    public function storeHealthResult($providerName, $result)
    {
        try {
            DB::table('provider_health_logs')->insert([
                'provider' => $providerName,
                'healthy' => $result['healthy'],
                'response_time' => $result['response_time'],
                'error' => $result['error'] ?? null,
                'details' => json_encode($result),
                'checked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store health result', [
                'provider' => $providerName,
                'result' => $result,
                'error' => $e->getMessage()
            ]);
        }
    }
}

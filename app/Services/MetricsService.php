<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetricsService
{
    protected $cachePrefix = 'metrics:';
    protected $cacheTtl = 300; // 5 minutes

    /**
     * Track notification sent
     */
    public function trackNotificationSent($provider, $channel, $metadata = [])
    {
        $this->incrementCounter('notifications.sent', [
            'provider' => $provider,
            'channel' => $channel,
        ]);

        $this->recordHistogram('notification.send_time', $metadata['send_time'] ?? 0, [
            'provider' => $provider,
            'channel' => $channel,
        ]);

        Log::info('Notification sent', array_merge([
            'provider' => $provider,
            'channel' => $channel,
        ], $metadata));
    }

    /**
     * Track notification delivered
     */
    public function trackNotificationDelivered($provider, $channel, $deliveryTime = null)
    {
        $this->incrementCounter('notifications.delivered', [
            'provider' => $provider,
            'channel' => $channel,
        ]);

        if ($deliveryTime) {
            $this->recordHistogram('notification.delivery_time', $deliveryTime, [
                'provider' => $provider,
                'channel' => $channel,
            ]);
        }

        Log::info('Notification delivered', [
            'provider' => $provider,
            'channel' => $channel,
            'delivery_time' => $deliveryTime,
        ]);
    }

    /**
     * Track notification failed
     */
    public function trackNotificationFailed($provider, $channel, $error, $metadata = [])
    {
        $this->incrementCounter('notifications.failed', [
            'provider' => $provider,
            'channel' => $channel,
            'error_type' => $this->categorizeError($error),
        ]);

        Log::error('Notification failed', array_merge([
            'provider' => $provider,
            'channel' => $channel,
            'error' => $error,
        ], $metadata));
    }

    /**
     * Track API request
     */
    public function trackApiRequest($endpoint, $method, $statusCode, $responseTime)
    {
        $this->incrementCounter('api.requests', [
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
        ]);

        $this->recordHistogram('api.response_time', $responseTime, [
            'endpoint' => $endpoint,
            'method' => $method,
        ]);
    }

    /**
     * Track webhook call
     */
    public function trackWebhookCall($url, $statusCode, $responseTime, $success = true)
    {
        $this->incrementCounter('webhooks.calls', [
            'status_code' => $statusCode,
            'success' => $success ? 'true' : 'false',
        ]);

        $this->recordHistogram('webhook.response_time', $responseTime, [
            'success' => $success ? 'true' : 'false',
        ]);
    }

    /**
     * Track provider health check
     */
    public function trackProviderHealthCheck($provider, $healthy, $responseTime)
    {
        $this->setGauge('provider.health', $healthy ? 1 : 0, [
            'provider' => $provider,
        ]);

        $this->recordHistogram('provider.health_check_time', $responseTime, [
            'provider' => $provider,
        ]);
    }

    /**
     * Get current metrics summary
     */
    public function getMetricsSummary($period = '1h')
    {
        $cacheKey = $this->cachePrefix . 'summary:' . $period;

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($period) {
            $timeFilter = $this->getTimeFilter($period);

            return [
                'notifications' => [
                    'sent' => $this->getCounterValue('notifications.sent', $timeFilter),
                    'delivered' => $this->getCounterValue('notifications.delivered', $timeFilter),
                    'failed' => $this->getCounterValue('notifications.failed', $timeFilter),
                ],
                'api' => [
                    'requests' => $this->getCounterValue('api.requests', $timeFilter),
                    'avg_response_time' => $this->getHistogramAverage('api.response_time', $timeFilter),
                ],
                'webhooks' => [
                    'calls' => $this->getCounterValue('webhooks.calls', $timeFilter),
                    'avg_response_time' => $this->getHistogramAverage('webhook.response_time', $timeFilter),
                ],
                'providers' => $this->getProviderHealth(),
            ];
        });
    }

    /**
     * Get provider-specific metrics
     */
    public function getProviderMetrics($provider, $period = '24h')
    {
        $timeFilter = $this->getTimeFilter($period);

        return [
            'notifications_sent' => $this->getCounterValue('notifications.sent', $timeFilter, ['provider' => $provider]),
            'notifications_delivered' => $this->getCounterValue('notifications.delivered', $timeFilter, ['provider' => $provider]),
            'notifications_failed' => $this->getCounterValue('notifications.failed', $timeFilter, ['provider' => $provider]),
            'avg_send_time' => $this->getHistogramAverage('notification.send_time', $timeFilter, ['provider' => $provider]),
            'avg_delivery_time' => $this->getHistogramAverage('notification.delivery_time', $timeFilter, ['provider' => $provider]),
            'health_status' => $this->getGaugeValue('provider.health', ['provider' => $provider]),
        ];
    }

    /**
     * Increment counter metric
     */
    protected function incrementCounter($metric, $labels = [], $value = 1)
    {
        try {
            $labelsJson = json_encode($labels);
            $createdAt = now()->format('Y-m-d H:i:00'); // Round to minute
            
            // Try to find existing record using raw SQL to avoid JSON comparison issues
            $existing = DB::select("
                SELECT id, value FROM metrics 
                WHERE metric = ? AND labels::text = ? AND type = ? AND created_at = ?
            ", [$metric, $labelsJson, 'counter', $createdAt]);
            
            if (!empty($existing)) {
                // Update existing record
                DB::table('metrics')
                    ->where('id', $existing[0]->id)
                    ->update([
                        'value' => $existing[0]->value + $value,
                        'updated_at' => now(),
                    ]);
            } else {
                // Insert new record
                DB::table('metrics')->insert([
                    'metric' => $metric,
                    'labels' => $labelsJson,
                    'type' => 'counter',
                    'value' => $value,
                    'created_at' => $createdAt,
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to record counter metric', [
                'metric' => $metric,
                'labels' => $labels,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record histogram metric
     */
    protected function recordHistogram($metric, $value, $labels = [])
    {
        try {
            DB::table('metrics')->insert([
                'metric' => $metric,
                'labels' => json_encode($labels),
                'type' => 'histogram',
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record histogram metric', [
                'metric' => $metric,
                'labels' => $labels,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Set gauge metric
     */
    protected function setGauge($metric, $value, $labels = [])
    {
        try {
            $labelsJson = json_encode($labels);
            
            // Try to find existing record using raw SQL to avoid JSON comparison issues
            $existing = DB::select("
                SELECT id FROM metrics 
                WHERE metric = ? AND labels::text = ? AND type = ?
            ", [$metric, $labelsJson, 'gauge']);
            
            if (!empty($existing)) {
                // Update existing record
                DB::table('metrics')
                    ->where('id', $existing[0]->id)
                    ->update([
                        'value' => $value,
                        'updated_at' => now(),
                    ]);
            } else {
                // Insert new record
                DB::table('metrics')->insert([
                    'metric' => $metric,
                    'labels' => $labelsJson,
                    'type' => 'gauge',
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to record gauge metric', [
                'metric' => $metric,
                'labels' => $labels,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get counter value
     */
    protected function getCounterValue($metric, $timeFilter, $labels = [])
    {
        $query = DB::table('metrics')
            ->where('metric', $metric)
            ->where('type', 'counter')
            ->where('created_at', '>=', $timeFilter);

        if (!empty($labels)) {
            $query->where('labels', json_encode($labels));
        }

        return $query->sum('value') ?? 0;
    }

    /**
     * Get histogram average
     */
    protected function getHistogramAverage($metric, $timeFilter, $labels = [])
    {
        $query = DB::table('metrics')
            ->where('metric', $metric)
            ->where('type', 'histogram')
            ->where('created_at', '>=', $timeFilter);

        if (!empty($labels)) {
            $query->where('labels', json_encode($labels));
        }

        return $query->avg('value') ?? 0;
    }

    /**
     * Get gauge value
     */
    protected function getGaugeValue($metric, $labels = [])
    {
        $query = DB::table('metrics')
            ->where('metric', $metric)
            ->where('type', 'gauge');

        if (!empty($labels)) {
            $query->where('labels', json_encode($labels));
        }

        return $query->value('value') ?? 0;
    }

    /**
     * Get provider health status
     */
    protected function getProviderHealth()
    {
        return DB::table('metrics')
            ->select('labels', 'value')
            ->where('metric', 'provider.health')
            ->where('type', 'gauge')
            ->get()
            ->mapWithKeys(function ($row) {
                $labels = json_decode($row->labels, true);
                return [$labels['provider'] => $row->value == 1];
            })
            ->toArray();
    }

    /**
     * Get time filter for period
     */
    protected function getTimeFilter($period)
    {
        return match($period) {
            '5m' => now()->subMinutes(5),
            '15m' => now()->subMinutes(15),
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subHour(),
        };
    }

    /**
     * Categorize error for metrics
     */
    protected function categorizeError($error)
    {
        $error = strtolower($error);
        
        if (str_contains($error, 'timeout') || str_contains($error, 'connection')) {
            return 'network';
        } elseif (str_contains($error, 'authentication') || str_contains($error, 'unauthorized')) {
            return 'auth';
        } elseif (str_contains($error, 'rate limit') || str_contains($error, 'quota')) {
            return 'rate_limit';
        } elseif (str_contains($error, 'validation') || str_contains($error, 'invalid')) {
            return 'validation';
        } else {
            return 'unknown';
        }
    }

    /**
     * Clean up old metrics
     */
    public function cleanupOldMetrics($retentionDays = 30)
    {
        $cutoff = now()->subDays($retentionDays);
        
        $deleted = DB::table('metrics')
            ->where('created_at', '<', $cutoff)
            ->delete();

        Log::info('Cleaned up old metrics', ['deleted_count' => $deleted]);
        
        return $deleted;
    }
}

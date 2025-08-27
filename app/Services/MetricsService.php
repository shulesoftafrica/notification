<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Project;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class MetricsService
{
    private $redis;

    public function __construct()
    {
        try {
            $this->redis = Redis::connection();
        } catch (\Exception $e) {
            $this->redis = null;
        }
    }

    /**
     * Record a metric event
     */
    public function recordMetric(string $metric, $value = 1, array $tags = []): void
    {
        try {
            if ($this->redis) {
                $this->recordToRedis($metric, $value, $tags);
            }
            
            // Also log to Laravel logs for backup
            \Log::info('Metric recorded', [
                'metric' => $metric,
                'value' => $value,
                'tags' => $tags,
                'timestamp' => now()->timestamp
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to record metric', [
                'metric' => $metric,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get dashboard metrics
     */
    public function getDashboardMetrics(string $projectId = null, Carbon $since = null): array
    {
        $since = $since ?? now()->subDays(7);
        
        return Cache::remember(
            "dashboard_metrics:{$projectId}:" . $since->format('Y-m-d'),
            300, // 5 minutes
            function () use ($projectId, $since) {
                return [
                    'overview' => $this->getOverviewMetrics($projectId, $since),
                    'channels' => $this->getChannelMetrics($projectId, $since),
                    'providers' => $this->getProviderMetrics($projectId, $since),
                    'delivery_stats' => $this->getDeliveryStats($projectId, $since),
                    'webhook_stats' => $this->getWebhookStats($projectId, $since),
                    'cost_analysis' => $this->getCostAnalysis($projectId, $since),
                    'time_series' => $this->getTimeSeriesData($projectId, $since),
                ];
            }
        );
    }

    /**
     * Get overview metrics
     */
    private function getOverviewMetrics(?string $projectId, Carbon $since): array
    {
        $query = Message::where('created_at', '>=', $since);
        
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $total = $query->count();
        $delivered = $query->where('status', 'delivered')->count();
        $failed = $query->where('status', 'failed')->count();
        $pending = $query->whereIn('status', ['pending', 'queued', 'sending'])->count();

        $deliveryRate = $total > 0 ? round(($delivered / $total) * 100, 2) : 0;
        $failureRate = $total > 0 ? round(($failed / $total) * 100, 2) : 0;

        return [
            'total_messages' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $pending,
            'delivery_rate' => $deliveryRate,
            'failure_rate' => $failureRate,
        ];
    }

    /**
     * Get channel breakdown metrics
     */
    private function getChannelMetrics(?string $projectId, Carbon $since): array
    {
        $query = Message::select('channel', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $since)
            ->groupBy('channel');
            
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $channelCounts = $query->pluck('count', 'channel')->toArray();

        $channels = [];
        foreach (['email', 'sms', 'whatsapp'] as $channel) {
            $count = $channelCounts[$channel] ?? 0;
            $delivered = Message::where('channel', $channel)
                ->where('status', 'delivered')
                ->where('created_at', '>=', $since)
                ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->count();
                
            $channels[$channel] = [
                'total' => $count,
                'delivered' => $delivered,
                'delivery_rate' => $count > 0 ? round(($delivered / $count) * 100, 2) : 0,
            ];
        }

        return $channels;
    }

    /**
     * Get provider performance metrics
     */
    private function getProviderMetrics(?string $projectId, Carbon $since): array
    {
        $query = Message::select('provider', DB::raw('count(*) as count'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('provider')
            ->groupBy('provider');
            
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $providerCounts = $query->pluck('count', 'provider')->toArray();

        $providers = [];
        foreach ($providerCounts as $provider => $count) {
            $delivered = Message::where('provider', $provider)
                ->where('status', 'delivered')
                ->where('created_at', '>=', $since)
                ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->count();

            $avgResponseTime = Message::where('provider', $provider)
                ->whereNotNull('provider_response_time')
                ->where('created_at', '>=', $since)
                ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->avg('provider_response_time');

            $providers[$provider] = [
                'total' => $count,
                'delivered' => $delivered,
                'delivery_rate' => $count > 0 ? round(($delivered / $count) * 100, 2) : 0,
                'avg_response_time' => $avgResponseTime ? round($avgResponseTime, 2) : null,
            ];
        }

        return $providers;
    }

    /**
     * Get delivery statistics
     */
    private function getDeliveryStats(?string $projectId, Carbon $since): array
    {
        $query = Message::where('created_at', '>=', $since);
        
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $avgDeliveryTime = $query->whereNotNull('delivered_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, delivered_at)) as avg_time')
            ->value('avg_time');

        $deliveryTimes = $query->whereNotNull('delivered_at')
            ->selectRaw('TIMESTAMPDIFF(SECOND, created_at, delivered_at) as delivery_time')
            ->pluck('delivery_time')
            ->sort();

        $p95DeliveryTime = null;
        if ($deliveryTimes->count() > 0) {
            $p95Index = (int) ceil($deliveryTimes->count() * 0.95) - 1;
            $p95DeliveryTime = $deliveryTimes->get($p95Index);
        }

        return [
            'avg_delivery_time_seconds' => $avgDeliveryTime ? round($avgDeliveryTime, 2) : null,
            'p95_delivery_time_seconds' => $p95DeliveryTime,
            'total_processed' => $query->count(),
        ];
    }

    /**
     * Get webhook delivery statistics
     */
    private function getWebhookStats(?string $projectId, Carbon $since): array
    {
        $query = WebhookDelivery::where('created_at', '>=', $since);
        
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $total = $query->count();
        $successful = $query->where('status', 'delivered')->count();
        $failed = $query->where('status', 'failed')->count();
        $pending = $query->where('status', 'pending')->count();

        $successRate = $total > 0 ? round(($successful / $total) * 100, 2) : 0;

        return [
            'total_webhooks' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'pending' => $pending,
            'success_rate' => $successRate,
        ];
    }

    /**
     * Get cost analysis
     */
    private function getCostAnalysis(?string $projectId, Carbon $since): array
    {
        $query = Message::where('created_at', '>=', $since)
            ->whereNotNull('cost_amount');
            
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $totalCost = $query->sum('cost_amount');
        $avgCost = $query->avg('cost_amount');

        $costByChannel = Message::select('channel', DB::raw('SUM(cost_amount) as total_cost'))
            ->where('created_at', '>=', $since)
            ->whereNotNull('cost_amount')
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->groupBy('channel')
            ->pluck('total_cost', 'channel')
            ->toArray();

        return [
            'total_cost' => round($totalCost, 4),
            'average_cost_per_message' => round($avgCost, 4),
            'cost_by_channel' => $costByChannel,
            'currency' => 'USD', // TODO: Make this configurable
        ];
    }

    /**
     * Get time series data for charts
     */
    private function getTimeSeriesData(?string $projectId, Carbon $since): array
    {
        $interval = $since->diffInDays(now()) > 7 ? 'hour' : 'hour';
        $format = $interval === 'hour' ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';

        $query = Message::select(
                DB::raw("DATE_FORMAT(created_at, '{$format}') as period"),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            )
            ->where('created_at', '>=', $since)
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $query->map(function ($item) {
            return [
                'period' => $item->period,
                'total' => $item->total,
                'delivered' => $item->delivered,
                'failed' => $item->failed,
                'delivery_rate' => $item->total > 0 ? round(($item->delivered / $item->total) * 100, 2) : 0,
            ];
        })->toArray();
    }

    /**
     * Record metric to Redis
     */
    private function recordToRedis(string $metric, $value, array $tags): void
    {
        if (!$this->redis) {
            return;
        }

        $timestamp = now()->timestamp;
        $key = "metrics:{$metric}";
        
        // Store with tags
        $data = [
            'value' => $value,
            'tags' => $tags,
            'timestamp' => $timestamp,
        ];

        $this->redis->zadd($key, $timestamp, json_encode($data));
        
        // Keep only last 24 hours of metrics
        $cutoff = $timestamp - 86400;
        $this->redis->zremrangebyscore($key, 0, $cutoff);
    }

    /**
     * Get real-time metrics from Redis
     */
    public function getRealTimeMetrics(string $metric, Carbon $since = null): array
    {
        if (!$this->redis) {
            return [];
        }

        $since = $since ?? now()->subHour();
        $key = "metrics:{$metric}";
        
        $data = $this->redis->zrangebyscore(
            $key,
            $since->timestamp,
            '+inf',
            ['withscores' => true]
        );

        $metrics = [];
        foreach ($data as $json => $timestamp) {
            $metric = json_decode($json, true);
            $metrics[] = array_merge($metric, ['timestamp' => $timestamp]);
        }

        return $metrics;
    }

    /**
     * Record common metrics
     */
    public function recordMessageSent(string $projectId, string $channel, string $provider): void
    {
        $this->recordMetric('message.sent', 1, [
            'project_id' => $projectId,
            'channel' => $channel,
            'provider' => $provider,
        ]);
    }

    public function recordMessageDelivered(string $projectId, string $channel, string $provider, float $deliveryTime): void
    {
        $this->recordMetric('message.delivered', 1, [
            'project_id' => $projectId,
            'channel' => $channel,
            'provider' => $provider,
        ]);
        
        $this->recordMetric('delivery.time', $deliveryTime, [
            'project_id' => $projectId,
            'channel' => $channel,
            'provider' => $provider,
        ]);
    }

    public function recordMessageFailed(string $projectId, string $channel, string $provider, string $reason): void
    {
        $this->recordMetric('message.failed', 1, [
            'project_id' => $projectId,
            'channel' => $channel,
            'provider' => $provider,
            'reason' => $reason,
        ]);
    }

    public function recordWebhookDelivered(string $projectId, string $event, int $responseTime): void
    {
        $this->recordMetric('webhook.delivered', 1, [
            'project_id' => $projectId,
            'event' => $event,
        ]);
        
        $this->recordMetric('webhook.response_time', $responseTime, [
            'project_id' => $projectId,
            'event' => $event,
        ]);
    }

    public function recordWebhookFailed(string $projectId, string $event, string $reason): void
    {
        $this->recordMetric('webhook.failed', 1, [
            'project_id' => $projectId,
            'event' => $event,
            'reason' => $reason,
        ]);
    }
}

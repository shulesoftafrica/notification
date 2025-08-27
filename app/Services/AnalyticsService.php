<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get delivery rate analytics
     */
    public function getDeliveryRates(string $projectId, string $tenantId, array $filters = []): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : Carbon::now()->subDays(30);
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : Carbon::now();
        $channel = $filters['channel'] ?? null;

        $query = Message::where('project_id', $projectId)
                       ->where('tenant_id', $tenantId)
                       ->whereBetween('created_at', [$startDate, $endDate]);

        if ($channel) {
            $query->where('channel', $channel);
        }

        $totalMessages = $query->count();
        $sentMessages = $query->where('status', 'sent')->count();
        $deliveredMessages = $query->where('status', 'delivered')->count();
        $failedMessages = $query->where('status', 'failed')->count();

        $deliveryRate = $totalMessages > 0 ? ($deliveredMessages / $totalMessages) * 100 : 0;
        $failureRate = $totalMessages > 0 ? ($failedMessages / $totalMessages) * 100 : 0;

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString()
            ],
            'total_messages' => $totalMessages,
            'sent_messages' => $sentMessages,
            'delivered_messages' => $deliveredMessages,
            'failed_messages' => $failedMessages,
            'delivery_rate' => round($deliveryRate, 2),
            'failure_rate' => round($failureRate, 2)
        ];
    }

    /**
     * Get daily message volume
     */
    public function getDailyVolume(string $projectId, string $tenantId, array $filters = []): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : Carbon::now()->subDays(30);
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : Carbon::now();
        $channel = $filters['channel'] ?? null;

        $query = Message::where('project_id', $projectId)
                       ->where('tenant_id', $tenantId)
                       ->whereBetween('created_at', [$startDate, $endDate]);

        if ($channel) {
            $query->where('channel', $channel);
        }

        $dailyVolume = $query->selectRaw('DATE(created_at) as date, channel, status, COUNT(*) as count')
                           ->groupBy(['date', 'channel', 'status'])
                           ->orderBy('date')
                           ->get()
                           ->groupBy('date');

        $result = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->toDateString();
            $dayData = $dailyVolume->get($dateString, collect());

            $result[] = [
                'date' => $dateString,
                'total' => $dayData->sum('count'),
                'by_channel' => $dayData->groupBy('channel')->map(function ($items) {
                    return $items->sum('count');
                }),
                'by_status' => $dayData->groupBy('status')->map(function ($items) {
                    return $items->sum('count');
                })
            ];

            $currentDate->addDay();
        }

        return $result;
    }

    /**
     * Get provider performance metrics
     */
    public function getProviderPerformance(string $projectId, string $tenantId, array $filters = []): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : Carbon::now()->subDays(30);
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : Carbon::now();

        $providerStats = Message::where('project_id', $projectId)
                               ->where('tenant_id', $tenantId)
                               ->whereBetween('created_at', [$startDate, $endDate])
                               ->whereNotNull('provider_name')
                               ->selectRaw('
                                   provider_name,
                                   channel,
                                   COUNT(*) as total_messages,
                                   SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered,
                                   SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                                   AVG(cost_amount) as avg_cost,
                                   AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as avg_processing_time
                               ')
                               ->groupBy(['provider_name', 'channel'])
                               ->get();

        return $providerStats->map(function ($stat) {
            $deliveryRate = $stat->total_messages > 0 ? ($stat->delivered / $stat->total_messages) * 100 : 0;
            $failureRate = $stat->total_messages > 0 ? ($stat->failed / $stat->total_messages) * 100 : 0;

            return [
                'provider' => $stat->provider_name,
                'channel' => $stat->channel,
                'total_messages' => $stat->total_messages,
                'delivered' => $stat->delivered,
                'failed' => $stat->failed,
                'delivery_rate' => round($deliveryRate, 2),
                'failure_rate' => round($failureRate, 2),
                'avg_cost' => round($stat->avg_cost ?? 0, 4),
                'avg_processing_time_seconds' => round($stat->avg_processing_time ?? 0, 2)
            ];
        })->toArray();
    }

    /**
     * Get cost analytics
     */
    public function getCostAnalytics(string $projectId, string $tenantId, array $filters = []): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : Carbon::now()->subDays(30);
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : Carbon::now();

        $costStats = Message::where('project_id', $projectId)
                           ->where('tenant_id', $tenantId)
                           ->whereBetween('created_at', [$startDate, $endDate])
                           ->whereNotNull('cost_amount')
                           ->selectRaw('
                               channel,
                               provider_name,
                               SUM(cost_amount) as total_cost,
                               AVG(cost_amount) as avg_cost_per_message,
                               COUNT(*) as message_count
                           ')
                           ->groupBy(['channel', 'provider_name'])
                           ->get();

        $totalCost = $costStats->sum('total_cost');
        $totalMessages = $costStats->sum('message_count');

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString()
            ],
            'total_cost' => round($totalCost, 4),
            'total_messages' => $totalMessages,
            'avg_cost_per_message' => $totalMessages > 0 ? round($totalCost / $totalMessages, 4) : 0,
            'by_channel' => $costStats->groupBy('channel')->map(function ($items, $channel) {
                return [
                    'channel' => $channel,
                    'total_cost' => round($items->sum('total_cost'), 4),
                    'message_count' => $items->sum('message_count'),
                    'avg_cost' => round($items->avg('avg_cost_per_message'), 4)
                ];
            })->values(),
            'by_provider' => $costStats->map(function ($stat) {
                return [
                    'provider' => $stat->provider_name,
                    'channel' => $stat->channel,
                    'total_cost' => round($stat->total_cost, 4),
                    'message_count' => $stat->message_count,
                    'avg_cost' => round($stat->avg_cost_per_message, 4)
                ];
            })->toArray()
        ];
    }

    /**
     * Get template usage analytics
     */
    public function getTemplateUsage(string $projectId, string $tenantId, array $filters = []): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : Carbon::now()->subDays(30);
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : Carbon::now();

        $templateStats = Message::where('project_id', $projectId)
                               ->where('tenant_id', $tenantId)
                               ->whereBetween('created_at', [$startDate, $endDate])
                               ->whereNotNull('template_id')
                               ->selectRaw('
                                   template_id,
                                   channel,
                                   COUNT(*) as usage_count,
                                   SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered_count,
                                   SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count
                               ')
                               ->groupBy(['template_id', 'channel'])
                               ->orderBy('usage_count', 'desc')
                               ->get();

        return $templateStats->map(function ($stat) {
            $deliveryRate = $stat->usage_count > 0 ? ($stat->delivered_count / $stat->usage_count) * 100 : 0;

            return [
                'template_id' => $stat->template_id,
                'channel' => $stat->channel,
                'usage_count' => $stat->usage_count,
                'delivered_count' => $stat->delivered_count,
                'failed_count' => $stat->failed_count,
                'delivery_rate' => round($deliveryRate, 2)
            ];
        })->toArray();
    }

    /**
     * Get real-time queue metrics
     */
    public function getQueueMetrics(): array
    {
        // Get queue statistics from Redis
        $redis = app('redis')->connection();
        
        try {
            $queueLength = $redis->llen('queues:default');
            $highPriorityQueue = $redis->llen('queues:high');
            $lowPriorityQueue = $redis->llen('queues:low');
            
            // Get processing statistics
            $processingCount = Message::where('status', 'processing')->count();
            $queuedCount = Message::where('status', 'queued')->count();
            
            return [
                'queue_lengths' => [
                    'high_priority' => $highPriorityQueue,
                    'default' => $queueLength,
                    'low_priority' => $lowPriorityQueue,
                    'total' => $highPriorityQueue + $queueLength + $lowPriorityQueue
                ],
                'message_counts' => [
                    'processing' => $processingCount,
                    'queued' => $queuedCount
                ],
                'timestamp' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to retrieve queue metrics: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Get engagement metrics (for email)
     */
    public function getEngagementMetrics(string $projectId, string $tenantId, array $filters = []): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : Carbon::now()->subDays(30);
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : Carbon::now();

        // Get engagement data from receipts
        $engagementStats = Receipt::join('messages', 'receipts.message_id', '=', 'messages.message_id')
                                 ->where('messages.project_id', $projectId)
                                 ->where('messages.tenant_id', $tenantId)
                                 ->where('messages.channel', 'email')
                                 ->whereBetween('receipts.occurred_at', [$startDate, $endDate])
                                 ->selectRaw('
                                     event_type,
                                     COUNT(*) as event_count,
                                     COUNT(DISTINCT messages.message_id) as unique_messages
                                 ')
                                 ->groupBy('event_type')
                                 ->get();

        $totalEmails = Message::where('project_id', $projectId)
                             ->where('tenant_id', $tenantId)
                             ->where('channel', 'email')
                             ->whereBetween('created_at', [$startDate, $endDate])
                             ->count();

        $engagement = [];
        foreach ($engagementStats as $stat) {
            $rate = $totalEmails > 0 ? ($stat->unique_messages / $totalEmails) * 100 : 0;
            $engagement[$stat->event_type] = [
                'count' => $stat->event_count,
                'unique_messages' => $stat->unique_messages,
                'rate' => round($rate, 2)
            ];
        }

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString()
            ],
            'total_emails_sent' => $totalEmails,
            'engagement' => $engagement
        ];
    }
}

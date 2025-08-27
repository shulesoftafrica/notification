<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Message;
use App\Models\Template;
use App\Models\WebhookDelivery;
use App\Services\MetricsService;
use App\Services\ProviderHealthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private MetricsService $metricsService;
    private ProviderHealthService $healthService;

    public function __construct(
        MetricsService $metricsService,
        ProviderHealthService $healthService
    ) {
        $this->metricsService = $metricsService;
        $this->healthService = $healthService;
    }

    /**
     * Get dashboard overview
     */
    public function overview(Request $request): JsonResponse
    {
        $projectId = $request->get('project_id');
        $since = $request->get('since') ? Carbon::parse($request->get('since')) : Carbon::now()->subDays(7);

        $data = [
            'summary' => $this->getSummaryStats($projectId, $since),
            'recent_activity' => $this->getRecentActivity($projectId),
            'provider_health' => $this->getProviderHealthStatus(),
            'system_status' => $this->getSystemStatus(),
        ];

        return response()->json([
            'data' => $data,
            'meta' => [
                'generated_at' => now()->toISOString(),
                'period' => [
                    'since' => $since->toISOString(),
                    'until' => now()->toISOString(),
                ],
                'project_id' => $projectId,
            ]
        ]);
    }

    /**
     * Get detailed metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        $projectId = $request->get('project_id');
        $since = $request->get('since') ? Carbon::parse($request->get('since')) : Carbon::now()->subDays(7);

        $metrics = $this->metricsService->getDashboardMetrics($projectId, $since);

        return response()->json([
            'data' => $metrics,
            'meta' => [
                'generated_at' => now()->toISOString(),
                'period' => [
                    'since' => $since->toISOString(),
                    'until' => now()->toISOString(),
                ],
                'project_id' => $projectId,
            ]
        ]);
    }

    /**
     * Get provider health status
     */
    public function providerHealth(): JsonResponse
    {
        $providers = $this->getProviderHealthStatus();

        return response()->json([
            'data' => $providers,
            'meta' => [
                'checked_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Get recent messages
     */
    public function recentMessages(Request $request): JsonResponse
    {
        $projectId = $request->get('project_id');
        $limit = min($request->get('limit', 50), 100);

        $query = Message::with(['project'])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $messages = $query->get()->map(function ($message) {
            return [
                'message_id' => $message->message_id,
                'project_id' => $message->project_id,
                'tenant_id' => $message->tenant_id,
                'channel' => $message->channel,
                'to' => $message->to,
                'status' => $message->status,
                'provider' => $message->provider,
                'created_at' => $message->created_at->toISOString(),
                'delivered_at' => $message->delivered_at?->toISOString(),
                'failed_at' => $message->failed_at?->toISOString(),
                'failure_reason' => $message->failure_reason,
                'cost_amount' => $message->cost_amount,
                'retry_count' => $message->retry_count,
            ];
        });

        return response()->json([
            'data' => $messages,
            'meta' => [
                'count' => $messages->count(),
                'limit' => $limit,
            ]
        ]);
    }

    /**
     * Get project statistics
     */
    public function projectStats(Request $request): JsonResponse
    {
        $since = $request->get('since') ? Carbon::parse($request->get('since')) : Carbon::now()->subDays(30);

        $projects = Project::withCount([
            'messages' => fn($q) => $q->where('created_at', '>=', $since),
            'templates',
        ])
        ->with(['projectTenants'])
        ->get()
        ->map(function ($project) use ($since) {
            $delivered = Message::where('project_id', $project->project_id)
                ->where('status', 'delivered')
                ->where('created_at', '>=', $since)
                ->count();

            $failed = Message::where('project_id', $project->project_id)
                ->where('status', 'failed')
                ->where('created_at', '>=', $since)
                ->count();

            $totalCost = Message::where('project_id', $project->project_id)
                ->where('created_at', '>=', $since)
                ->sum('cost_amount');

            return [
                'project_id' => $project->project_id,
                'name' => $project->name,
                'status' => $project->status,
                'messages_count' => $project->messages_count,
                'templates_count' => $project->templates_count,
                'tenants_count' => $project->project_tenants->count(),
                'delivered_count' => $delivered,
                'failed_count' => $failed,
                'delivery_rate' => $project->messages_count > 0 
                    ? round(($delivered / $project->messages_count) * 100, 2) 
                    : 0,
                'total_cost' => round($totalCost, 4),
                'created_at' => $project->created_at->toISOString(),
            ];
        });

        return response()->json([
            'data' => $projects,
            'meta' => [
                'period' => [
                    'since' => $since->toISOString(),
                    'until' => now()->toISOString(),
                ],
                'total_projects' => $projects->count(),
            ]
        ]);
    }

    /**
     * Get webhook delivery status
     */
    public function webhookStats(Request $request): JsonResponse
    {
        $projectId = $request->get('project_id');
        $since = $request->get('since') ? Carbon::parse($request->get('since')) : Carbon::now()->subDays(7);

        $query = WebhookDelivery::where('created_at', '>=', $since);
        
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $stats = [
            'total' => $query->count(),
            'successful' => $query->where('status', 'delivered')->count(),
            'failed' => $query->where('status', 'failed')->count(),
            'pending' => $query->where('status', 'pending')->count(),
        ];

        $stats['success_rate'] = $stats['total'] > 0 
            ? round(($stats['successful'] / $stats['total']) * 100, 2)
            : 0;

        $recentDeliveries = $query->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($delivery) => $delivery->getSummary());

        return response()->json([
            'data' => [
                'stats' => $stats,
                'recent_deliveries' => $recentDeliveries,
            ],
            'meta' => [
                'period' => [
                    'since' => $since->toISOString(),
                    'until' => now()->toISOString(),
                ],
                'project_id' => $projectId,
            ]
        ]);
    }

    /**
     * Get queue status
     */
    public function queueStatus(): JsonResponse
    {
        // Note: This would need to be implemented based on your queue system
        // For Redis/Horizon, you can get queue lengths
        
        $queueStats = [
            'high' => $this->getQueueLength('high'),
            'default' => $this->getQueueLength('default'),
            'low' => $this->getQueueLength('low'),
            'webhooks-high' => $this->getQueueLength('webhooks-high'),
            'webhooks-default' => $this->getQueueLength('webhooks-default'),
            'webhooks-low' => $this->getQueueLength('webhooks-low'),
        ];

        $totalJobs = array_sum($queueStats);
        
        return response()->json([
            'data' => [
                'queues' => $queueStats,
                'total_jobs' => $totalJobs,
                'status' => $totalJobs > 10000 ? 'warning' : 'healthy',
            ],
            'meta' => [
                'checked_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Get summary statistics
     */
    private function getSummaryStats(?string $projectId, Carbon $since): array
    {
        $query = Message::where('created_at', '>=', $since);
        
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $total = $query->count();
        $delivered = $query->where('status', 'delivered')->count();
        $failed = $query->where('status', 'failed')->count();
        $pending = $query->whereIn('status', ['pending', 'queued', 'sending'])->count();

        $totalCost = $query->sum('cost_amount');
        
        return [
            'messages' => [
                'total' => $total,
                'delivered' => $delivered,
                'failed' => $failed,
                'pending' => $pending,
                'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
            ],
            'cost' => [
                'total' => round($totalCost, 4),
                'average_per_message' => $total > 0 ? round($totalCost / $total, 6) : 0,
                'currency' => 'USD',
            ],
            'period' => [
                'since' => $since->toISOString(),
                'until' => now()->toISOString(),
                'days' => $since->diffInDays(now()),
            ],
        ];
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(?string $projectId): array
    {
        $query = Message::orderBy('created_at', 'desc')->limit(10);
        
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->get()->map(function ($message) {
            return [
                'id' => $message->message_id,
                'type' => 'message',
                'action' => $message->status,
                'channel' => $message->channel,
                'to' => $message->to,
                'timestamp' => $message->created_at->toISOString(),
                'project_id' => $message->project_id,
            ];
        })->toArray();
    }

    /**
     * Get provider health status
     */
    private function getProviderHealthStatus(): array
    {
        $providers = [];
        $providerTypes = ['email', 'sms', 'whatsapp'];
        
        foreach ($providerTypes as $type) {
            $typeProviders = config("notification.providers.{$type}", []);
            
            foreach ($typeProviders as $providerId => $config) {
                try {
                    $isAvailable = $this->healthService->isProviderAvailable($providerId);
                    $healthScore = $this->healthService->getHealthScore($providerId);
                    $circuitState = $this->healthService->getCircuitState($providerId);
                    
                    $providers[] = [
                        'id' => $providerId,
                        'name' => $config['name'],
                        'type' => $type,
                        'status' => $isAvailable ? 'healthy' : 'unhealthy',
                        'health_score' => $healthScore,
                        'circuit_state' => $circuitState,
                        'priority' => $config['priority'],
                    ];
                } catch (\Exception $e) {
                    $providers[] = [
                        'id' => $providerId,
                        'name' => $config['name'] ?? $providerId,
                        'type' => $type,
                        'status' => 'error',
                        'health_score' => 0,
                        'circuit_state' => 'unknown',
                        'priority' => $config['priority'] ?? 999,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $providers;
    }

    /**
     * Get system status
     */
    private function getSystemStatus(): array
    {
        $status = 'healthy';
        $issues = [];

        // Check database connection
        try {
            \DB::connection()->getPdo();
        } catch (\Exception $e) {
            $status = 'unhealthy';
            $issues[] = 'Database connection failed';
        }

        // Check Redis connection
        try {
            \Redis::connection()->ping();
        } catch (\Exception $e) {
            $status = 'degraded';
            $issues[] = 'Redis connection failed';
        }

        // Check queue status
        $totalQueueJobs = $this->getQueueLength('default') + $this->getQueueLength('high') + $this->getQueueLength('low');
        if ($totalQueueJobs > 10000) {
            $status = 'warning';
            $issues[] = 'High queue backlog';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'uptime' => $this->getUptime(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ];
    }

    /**
     * Get queue length (simplified implementation)
     */
    private function getQueueLength(string $queue): int
    {
        try {
            return \Redis::connection()->llen("queues:{$queue}");
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get system uptime (simplified)
     */
    private function getUptime(): string
    {
        // This is a simplified implementation
        // In production, you might track this differently
        return "System operational";
    }
}

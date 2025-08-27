<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnalyticsController extends Controller
{
    private AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get delivery rate analytics
     */
    public function deliveryRates(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'channel' => 'sometimes|in:email,sms,whatsapp'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        $filters = $request->only(['start_date', 'end_date', 'channel']);
        $data = $this->analyticsService->getDeliveryRates($project->project_id, $tenantId, $filters);

        return response()->json([
            'data' => $data,
            'meta' => [
                'type' => 'delivery_rates',
                'filters' => $filters,
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Get daily message volume
     */
    public function dailyVolume(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'channel' => 'sometimes|in:email,sms,whatsapp'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        $filters = $request->only(['start_date', 'end_date', 'channel']);
        $data = $this->analyticsService->getDailyVolume($project->project_id, $tenantId, $filters);

        return response()->json([
            'data' => $data,
            'meta' => [
                'type' => 'daily_volume',
                'filters' => $filters,
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Get provider performance metrics
     */
    public function providerPerformance(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        $filters = $request->only(['start_date', 'end_date']);
        $data = $this->analyticsService->getProviderPerformance($project->project_id, $tenantId, $filters);

        return response()->json([
            'data' => $data,
            'meta' => [
                'type' => 'provider_performance',
                'filters' => $filters,
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Get cost analytics
     */
    public function costAnalytics(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        $filters = $request->only(['start_date', 'end_date']);
        $data = $this->analyticsService->getCostAnalytics($project->project_id, $tenantId, $filters);

        return response()->json([
            'data' => $data,
            'meta' => [
                'type' => 'cost_analytics',
                'filters' => $filters,
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Get template usage analytics
     */
    public function templateUsage(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        $filters = $request->only(['start_date', 'end_date']);
        $data = $this->analyticsService->getTemplateUsage($project->project_id, $tenantId, $filters);

        return response()->json([
            'data' => $data,
            'meta' => [
                'type' => 'template_usage',
                'filters' => $filters,
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Get engagement metrics (email specific)
     */
    public function engagementMetrics(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        $filters = $request->only(['start_date', 'end_date']);
        $data = $this->analyticsService->getEngagementMetrics($project->project_id, $tenantId, $filters);

        return response()->json([
            'data' => $data,
            'meta' => [
                'type' => 'engagement_metrics',
                'filters' => $filters,
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Get real-time queue metrics
     */
    public function queueMetrics(Request $request): JsonResponse
    {
        $requestId = $request->input('request_id');
        $data = $this->analyticsService->getQueueMetrics();

        return response()->json([
            'data' => $data,
            'meta' => [
                'type' => 'queue_metrics',
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Get comprehensive dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'period' => 'sometimes|in:today,week,month,quarter',
            'timezone' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        $period = $request->input('period', 'week');
        
        // Set date range based on period
        $filters = match($period) {
            'today' => [
                'start_date' => now()->startOfDay()->toDateString(),
                'end_date' => now()->endOfDay()->toDateString()
            ],
            'week' => [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString()
            ],
            'month' => [
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->toDateString()
            ],
            'quarter' => [
                'start_date' => now()->subDays(90)->toDateString(),
                'end_date' => now()->toDateString()
            ]
        };

        // Gather all dashboard data
        $deliveryRates = $this->analyticsService->getDeliveryRates($project->project_id, $tenantId, $filters);
        $dailyVolume = $this->analyticsService->getDailyVolume($project->project_id, $tenantId, $filters);
        $providerPerformance = $this->analyticsService->getProviderPerformance($project->project_id, $tenantId, $filters);
        $costAnalytics = $this->analyticsService->getCostAnalytics($project->project_id, $tenantId, $filters);
        $queueMetrics = $this->analyticsService->getQueueMetrics();

        return response()->json([
            'data' => [
                'overview' => $deliveryRates,
                'daily_volume' => array_slice($dailyVolume, -7), // Last 7 days for chart
                'provider_performance' => $providerPerformance,
                'cost_summary' => [
                    'total_cost' => $costAnalytics['total_cost'],
                    'avg_cost_per_message' => $costAnalytics['avg_cost_per_message'],
                    'by_channel' => $costAnalytics['by_channel']
                ],
                'queue_status' => $queueMetrics
            ],
            'meta' => [
                'period' => $period,
                'filters' => $filters,
                'generated_at' => now()->toISOString(),
                'trace_id' => $requestId
            ]
        ]);
    }
}

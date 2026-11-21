<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AnalyticsService;
use App\Services\MetricsService;

class AnalyticsController extends Controller
{
    protected $analyticsService;
    protected $metricsService;

    public function __construct(AnalyticsService $analyticsService, MetricsService $metricsService)
    {
        $this->analyticsService = $analyticsService;
        $this->metricsService = $metricsService;
    }

    /**
     * Get notification analytics
     */
    public function notifications(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'provider' => 'nullable|string',
            'channel' => 'nullable|string|in:email,sms,whatsapp',
        ]);

        try {
            $stats = $this->analyticsService->getNotificationStats(
                $request->start_date,
                $request->end_date
            );

            return response()->json([
                'success' => true,
                'data' => $stats,
                'filters' => [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'provider' => $request->provider,
                    'channel' => $request->channel,
                ],
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve analytics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get real-time metrics
     */
    public function metrics(Request $request)
    {
        $request->validate([
            'period' => 'nullable|string|in:5m,15m,1h,6h,24h,7d,30d',
        ]);

        try {
            $period = $request->get('period', '1h');
            $metrics = $this->metricsService->getMetricsSummary($period);

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'period' => $period,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve metrics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get provider-specific analytics
     */
    public function provider(Request $request, $provider)
    {
        $request->validate([
            'period' => 'nullable|string|in:1h,6h,24h,7d,30d',
        ]);

        try {
            $period = $request->get('period', '24h');
            $metrics = $this->metricsService->getProviderMetrics($provider, $period);

            return response()->json([
                'success' => true,
                'provider' => $provider,
                'data' => $metrics,
                'period' => $period,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => "Failed to retrieve metrics for provider {$provider}",
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get delivery rate analytics
     */
    public function deliveryRates(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'group_by' => 'nullable|string|in:hour,day,provider,channel',
        ]);

        try {
            $groupBy = $request->get('group_by', 'day');
            
            // Implementation would depend on your specific analytics needs
            $data = [
                'overall_rate' => 95.2,
                'by_provider' => [
                    'twilio' => 96.1,
                    'whatsapp' => 94.8,
                    'sendgrid' => 95.5,
                ],
                'by_channel' => [
                    'sms' => 96.1,
                    'whatsapp' => 94.8,
                    'email' => 95.5,
                ],
                'trending' => 'up',
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'group_by' => $groupBy,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve delivery rates',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get error analytics
     */
    public function errors(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'provider' => 'nullable|string',
        ]);

        try {
            // This would typically query your error logs
            $data = [
                'total_errors' => 127,
                'error_rate' => 2.1,
                'by_type' => [
                    'network' => 45,
                    'auth' => 12,
                    'rate_limit' => 8,
                    'validation' => 62,
                ],
                'by_provider' => [
                    'twilio' => 23,
                    'whatsapp' => 67,
                    'sendgrid' => 37,
                ],
                'trending' => 'down',
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve error analytics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export analytics data
     */
    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|string|in:csv,json,xlsx',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'required|string|in:notifications,metrics,errors,delivery_rates',
        ]);

        try {
            $format = $request->get('format');
            $type = $request->get('type');

            // Get the data based on type
            $data = match($type) {
                'notifications' => $this->analyticsService->getNotificationStats(
                    $request->start_date,
                    $request->end_date
                ),
                'metrics' => $this->metricsService->getMetricsSummary('24h'),
                default => ['message' => 'Export type not implemented yet'],
            };

            // For now, return JSON. In a real implementation, you'd generate actual files
            return response()->json([
                'success' => true,
                'export_url' => url("/api/v1/analytics/download/{$type}_{$format}_" . now()->format('Y-m-d')),
                'data' => $data,
                'format' => $format,
                'type' => $type,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to export analytics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\BulkMessageController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    
    // Health check endpoint (no authentication required)
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'service' => 'notification-service'
        ]);
    });

    // Public webhook endpoints (for provider callbacks)
    Route::prefix('webhooks')->group(function () {
        // Email provider webhooks
        Route::post('/sendgrid', [WebhookController::class, 'sendgrid'])->name('webhooks.sendgrid');
        Route::post('/mailgun', [WebhookController::class, 'mailgun'])->name('webhooks.mailgun');
        Route::post('/resend', [WebhookController::class, 'resend'])->name('webhooks.resend');
        
        // SMS provider webhooks
        Route::post('/twilio', [WebhookController::class, 'twilio'])->name('webhooks.twilio');
        Route::post('/vonage', [WebhookController::class, 'vonage'])->name('webhooks.vonage');
        
        // WhatsApp provider webhooks
        Route::post('/whatsapp', [WebhookController::class, 'whatsapp'])->name('webhooks.whatsapp');
        Route::get('/whatsapp', [WebhookController::class, 'whatsapp'])->name('webhooks.whatsapp.verify');
    });

    // Protected routes with project authentication
    Route::middleware(['auth.project'])->group(function () {
        
        // Core messaging routes with standard rate limiting
        Route::middleware(['rate_limit:tenant'])->group(function () {
            Route::post('/messages', [MessageController::class, 'store'])
                ->name('messages.send');
            Route::get('/messages/{messageId}', [MessageController::class, 'show'])
                ->name('messages.status');
            Route::get('/messages', [MessageController::class, 'index'])
                ->name('messages.index');
        });

        // Template management routes with endpoint-specific rate limiting
        Route::middleware(['rate_limit:endpoint'])->prefix('templates')->group(function () {
            Route::get('/', [TemplateController::class, 'index'])
                ->name('templates.index');
            Route::post('/', [TemplateController::class, 'store'])
                ->name('templates.store');
            Route::get('/{template}', [TemplateController::class, 'show'])
                ->name('templates.show');
            Route::put('/{template}', [TemplateController::class, 'update'])
                ->name('templates.update');
            Route::delete('/{template}', [TemplateController::class, 'destroy'])
                ->name('templates.destroy');
            Route::post('/{template}/preview', [TemplateController::class, 'preview'])
                ->name('templates.preview');
            Route::post('/{template}/validate', [TemplateController::class, 'validate'])
                ->name('templates.validate');
        });

        // Provider configuration routes with endpoint-specific rate limiting
        Route::middleware(['rate_limit:endpoint'])->prefix('config')->group(function () {
            Route::get('/providers', [ConfigController::class, 'index'])
                ->name('config.providers.index');
            Route::post('/providers', [ConfigController::class, 'store'])
                ->name('config.providers.store');
            Route::get('/providers/{config}', [ConfigController::class, 'show'])
                ->name('config.providers.show');
            Route::put('/providers/{config}', [ConfigController::class, 'update'])
                ->name('config.providers.update');
            Route::delete('/providers/{config}', [ConfigController::class, 'destroy'])
                ->name('config.providers.destroy');
            Route::post('/providers/{config}/test', [ConfigController::class, 'test'])
                ->name('config.providers.test');
            Route::get('/quotas', [ConfigController::class, 'quotas'])
                ->name('config.quotas');
        });

        // Bulk operations routes with strict rate limiting
        Route::middleware(['rate_limit:bulk'])->prefix('bulk')->group(function () {
            Route::post('/messages', [BulkMessageController::class, 'send'])
                ->name('bulk.messages.send');
            Route::get('/messages/{batchId}', [BulkMessageController::class, 'getStatus'])
                ->name('bulk.messages.status');
            Route::post('/messages/{batchId}/cancel', [BulkMessageController::class, 'cancel'])
                ->name('bulk.messages.cancel');
            Route::get('/jobs', [BulkMessageController::class, 'listJobs'])
                ->name('bulk.jobs.list');
        });

        // Analytics routes with endpoint-specific rate limiting
        Route::middleware(['rate_limit:endpoint'])->prefix('analytics')->group(function () {
            Route::get('/delivery-rates', [AnalyticsController::class, 'getDeliveryRates'])
                ->name('analytics.delivery-rates');
            Route::get('/daily-volume', [AnalyticsController::class, 'getDailyVolume'])
                ->name('analytics.daily-volume');
            Route::get('/provider-performance', [AnalyticsController::class, 'getProviderPerformance'])
                ->name('analytics.provider-performance');
            Route::get('/cost-analytics', [AnalyticsController::class, 'getCostAnalytics'])
                ->name('analytics.cost-analytics');
            Route::get('/engagement-metrics', [AnalyticsController::class, 'getEngagementMetrics'])
                ->name('analytics.engagement-metrics');
            Route::get('/dashboard', [AnalyticsController::class, 'getDashboard'])
                ->name('analytics.dashboard');
        });

        // Administrative routes with stricter rate limiting
        Route::middleware(['rate_limit:endpoint'])->prefix('admin')->group(function () {
            Route::get('/system-health', function () {
                return response()->json([
                    'database' => 'connected',
                    'redis' => 'connected',
                    'queue' => 'active',
                    'providers' => [
                        'sendgrid' => 'active',
                        'mailgun' => 'active',
                        'resend' => 'active',
                        'twilio' => 'active',
                        'vonage' => 'active'
                    ],
                    'timestamp' => now()->toISOString()
                ]);
            })->name('admin.health');
            
            Route::get('/queue-status', function () {
                return response()->json([
                    'pending_jobs' => \Illuminate\Support\Facades\Queue::size(),
                    'failed_jobs' => \Illuminate\Support\Facades\DB::table('failed_jobs')->count(),
                    'processed_today' => \Illuminate\Support\Facades\Cache::get('jobs_processed_today', 0),
                    'timestamp' => now()->toISOString()
                ]);
            })->name('admin.queue-status');
        });
    });
});

// Fallback for undefined routes
Route::fallback(function () {
    return response()->json([
        'error' => [
            'code' => 'NOT_FOUND',
            'message' => 'The requested endpoint was not found.',
            'details' => [
                'path' => request()->path(),
                'method' => request()->method()
            ]
        ]
    ], 404);
});

// Admin Authentication Routes
Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/refresh', [AdminAuthController::class, 'refresh']);
    Route::get('/sessions', [AdminAuthController::class, 'sessions']);
});

// Admin Dashboard Routes (Protected by authentication and rate limiting)
Route::prefix('admin')->middleware(['admin_auth', 'rate_limit_requests'])->group(function () {
    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);
    Route::get('/dashboard/provider-health', [DashboardController::class, 'providerHealth']);
    Route::get('/dashboard/recent-messages', [DashboardController::class, 'recentMessages']);
    Route::get('/dashboard/project-stats', [DashboardController::class, 'projectStats']);
    Route::get('/dashboard/webhook-stats', [DashboardController::class, 'webhookStats']);
    Route::get('/dashboard/queue-status', [DashboardController::class, 'queueStatus']);
});

// Health Check Routes (No authentication required for load balancers)
Route::get('/health', [HealthController::class, 'simple']);
Route::get('/health/detailed', [HealthController::class, 'detailed']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/startup', [HealthController::class, 'startup']);
Route::get('/metrics', [HealthController::class, 'metrics']);
Route::get('/status', [HealthController::class, 'status']);

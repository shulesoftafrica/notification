<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WaSenderSessionController;

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

// Health check
Route::get('/health', [HealthController::class, 'check']);
Route::get('/up', [HealthController::class, 'check']);

// Admin Authentication API
Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::post('/refresh', [AdminAuthController::class, 'refresh']);
    Route::get('/me', [AdminAuthController::class, 'me']);
});

// Protected Admin API routes
Route::middleware(['admin.auth'])->prefix('admin')->group(function () {
    Route::get('/dashboard/stats', function () {
        return response()->json([
            'total_notifications' => 1250,
            'success_rate' => 98.5,
            'active_providers' => 4,
            'queue_size' => 23
        ]);
    });

    Route::get('/users', function () {
        return response()->json([
            'data' => \App\Models\User::all()
        ]);
    });
});

// Notification API
Route::post('/notifications/send', [NotificationController::class, 'send']);
Route::get('/notifications/{id}', [NotificationController::class, 'status']);
Route::get('/notifications', [NotificationController::class, 'index']);

// Bulk Notification API (non-realtime)
Route::post('/notifications/bulk/send', [NotificationController::class, 'sendBulk']);


// Route::any('/notifications/{path?}', [NotificationController::class, 'index'])->where('path', '.*');

// Test routes for debugging
Route::get('/test', function () {
    Log::info('Test route accessed', ['timestamp' => now(), 'ip' => request()->ip()]);
    return response()->json([
        'message' => 'API is working',
        'timestamp' => now(),
        'route' => 'api/test',
        'logging' => 'enabled'
    ]);
});

Route::get('/test-error', function () {
    Log::error('Intentional test error');
    throw new \Exception('This is a test error for logging verification');
});

// Provider Management API
Route::middleware(['admin.auth'])->prefix('providers')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'providers' => [
                'email' => ['status' => 'active', 'last_used' => now()],
                'sms' => ['status' => 'active', 'last_used' => now()],
                'whatsapp' => ['status' => 'inactive', 'last_used' => null],
                'slack' => ['status' => 'active', 'last_used' => now()],
            ]
        ]);
    });

    Route::post('/{provider}/test', function ($provider) {
        return response()->json(['status' => 'test_sent', 'provider' => $provider]);
    });
});

// Analytics API
Route::middleware(['api.auth'])->prefix('analytics')->group(function () {
    Route::get('/summary', function () {
        return response()->json([
            'today' => ['sent' => 145, 'delivered' => 142, 'failed' => 3],
            'week' => ['sent' => 1250, 'delivered' => 1210, 'failed' => 40],
            'month' => ['sent' => 5480, 'delivered' => 5380, 'failed' => 100]
        ]);
    });

    Route::get('/providers', function () {
        return response()->json([
            'email' => 60,
            'sms' => 25,
            'whatsapp' => 10,
            'slack' => 5
        ]);
    });
});

// Webhook routes
Route::prefix('webhook')->group(function () {
    Route::any('/whatsapp', [WebhookController::class, 'whatsappwebhook']);
    Route::any('/twilio', [WebhookController::class, 'twilio']);
    Route::any('/sendgrid', [WebhookController::class, 'sendgrid']);
    Route::any('/mailgun', [WebhookController::class, 'mailgun']);
    Route::any('/test', [WebhookController::class, 'test']);
    Route::any('/{provider}', [WebhookController::class, 'generic']);
});

// WaSender WhatsApp Session Management API
Route::middleware(['api.auth'])->prefix('wasender')->group(function () {
    
    Route::post('/sessions/create', [WaSenderSessionController::class, 'createSession']);
    Route::get('/sessions', [WaSenderSessionController::class, 'getSessions']);
    Route::get('/sessions/{id}', [WaSenderSessionController::class, 'getSession']);
    Route::post('/sessions/{id}/connect', [WaSenderSessionController::class, 'connectSession']);
    Route::get('/sessions/{id}/status', [WaSenderSessionController::class, 'checkStatus']);
    Route::put('/sessions/{id}', [WaSenderSessionController::class, 'updateSession']);
    Route::get('/sessions/{id}/qrcode', [WaSenderSessionController::class, 'getQRCode']);
    Route::delete('/sessions/{id}', [WaSenderSessionController::class, 'deleteSession']);
});

// User info route (for authenticated users)
Route::middleware('api.auth')->get('/user', function (Request $request) {
    // Return user info from the authenticated project
    return response()->json([
        'project_id' => $request->attributes->get('project_id'),
        'project_key' => $request->attributes->get('project_key'),
        'authenticated' => true
    ]);
});

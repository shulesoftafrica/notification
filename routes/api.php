<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\HealthController;

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
Route::middleware(['rate.limit'])->group(function () {
    Route::post('/notifications', [NotificationController::class, 'send']);
    Route::get('/notifications/{id}', [NotificationController::class, 'status']);
    Route::get('/notifications', [NotificationController::class, 'index']);
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
Route::middleware(['admin.auth'])->prefix('analytics')->group(function () {
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

// User info route (for authenticated users)
Route::middleware('api.auth')->get('/user', function (Request $request) {
    // Return user info from the authenticated project
    return response()->json([
        'project_id' => $request->attributes->get('project_id'),
        'project_key' => $request->attributes->get('project_key'),
        'authenticated' => true
    ]);
});

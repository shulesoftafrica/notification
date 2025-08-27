<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Home page
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Health check route
Route::get('/up', [HealthController::class, 'check']);

// Admin Authentication Routes
Route::prefix('admin')->group(function () {
    // Login routes
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
    
    // Admin dashboard routes (protected)
    Route::middleware(['auth'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/users', [DashboardController::class, 'users'])->name('admin.users');
        Route::get('/analytics', [DashboardController::class, 'analytics'])->name('admin.analytics');
        Route::get('/settings', [DashboardController::class, 'settings'])->name('admin.settings');
    });
});

// Webhook routes
Route::prefix('webhooks')->group(function () {
    Route::post('/whatsapp', function () {
        return response()->json(['status' => 'received']);
    });
    
    Route::post('/twilio', function () {
        return response()->json(['status' => 'received']);
    });
});

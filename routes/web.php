<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\DocsController;

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

// Home page - Redirect to documentation
Route::get('/', function () {
    return redirect('/docs');
})->name('home');

// Health check route
Route::get('/up', [HealthController::class, 'check']);

// API Documentation Routes
Route::prefix('docs')->name('docs.')->group(function () {
    Route::get('/', [DocsController::class, 'index'])->name('index');
    Route::get('/getting-started', [DocsController::class, 'gettingStarted'])->name('getting-started');
    Route::get('/reference', [DocsController::class, 'reference'])->name('reference');
    Route::get('/explorer', [DocsController::class, 'explorer'])->name('explorer');
    Route::get('/webhooks', [DocsController::class, 'webhooks'])->name('webhooks');
    Route::get('/code-examples', [DocsController::class, 'codeExamples'])->name('code-examples');
    Route::get('/guides/{guide?}', [DocsController::class, 'guides'])->name('guides');
    Route::get('/changelog', [DocsController::class, 'changelog'])->name('changelog');
    Route::get('/status', [DocsController::class, 'status'])->name('status');
    
    // API spec endpoints
    Route::get('/openapi.json', [DocsController::class, 'openApiSpec'])->name('openapi');
    Route::get('/postman-collection', [DocsController::class, 'postmanCollection'])->name('postman');
});

// Admin Authentication Routes
Route::prefix('admin')->group(function () {
    // Login routes
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
    
    // Admin dashboard routes (protected)
    Route::middleware(['admin.auth'])->group(function () {
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

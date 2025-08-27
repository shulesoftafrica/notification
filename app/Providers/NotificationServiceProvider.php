<?php

namespace App\Providers;

use App\Services\ProviderHealthService;
use App\Services\ProviderFailoverService;
use App\Services\WebhookProcessorService;
use App\Services\ClientWebhookService;
use App\Services\MetricsService;
use App\Services\RateLimitService;
use App\Services\ProductionMonitoringService;
use App\Services\AlertService;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ProviderHealthService::class, function ($app) {
            return new ProviderHealthService(
                $app->make('redis'),
                config('notification.providers', [])
            );
        });

        $this->app->singleton(ProviderFailoverService::class, function ($app) {
            return new ProviderFailoverService(
                $app->make(ProviderHealthService::class),
                config('notification.providers', [])
            );
        });

        $this->app->singleton(WebhookProcessorService::class, function ($app) {
            return new WebhookProcessorService();
        });

        $this->app->singleton(ClientWebhookService::class, function ($app) {
            return new ClientWebhookService();
        });

        $this->app->singleton(MetricsService::class, function ($app) {
            return new MetricsService();
        });

        $this->app->singleton(RateLimitService::class, function ($app) {
            return new RateLimitService();
        });

        $this->app->singleton(ProductionMonitoringService::class, function ($app) {
            return new ProductionMonitoringService();
        });

        $this->app->singleton(AlertService::class, function ($app) {
            return new AlertService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\NotificationService as NotificationServiceClass;
use App\Services\AnalyticsService;
use App\Services\MetricsService;
use App\Services\AlertService;
use App\Services\ClientWebhookService;
use App\Services\ProviderHealthService;
use App\Services\ProviderFailoverService;
use App\Services\RateLimitService;
use App\Services\WebhookProcessorService;
use App\Services\ProductionMonitoringService;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register core services
        $this->app->singleton(AnalyticsService::class);
        $this->app->singleton(MetricsService::class);
        $this->app->singleton(AlertService::class);
        $this->app->singleton(ClientWebhookService::class);
        $this->app->singleton(ProviderHealthService::class);
        $this->app->singleton(RateLimitService::class);
        $this->app->singleton(WebhookProcessorService::class);

        // Register services with dependencies
        $this->app->singleton(ProviderFailoverService::class, function ($app) {
            return new ProviderFailoverService(
                $app->make(ProviderHealthService::class)
            );
        });

        $this->app->singleton(NotificationServiceClass::class, function ($app) {
            return new NotificationServiceClass(
                $app->make(ProviderFailoverService::class),
                $app->make(MetricsService::class),
                $app->make(AnalyticsService::class)
            );
        });

        $this->app->singleton(ProductionMonitoringService::class, function ($app) {
            return new ProductionMonitoringService(
                $app->make(MetricsService::class),
                $app->make(AlertService::class),
                $app->make(ProviderHealthService::class)
            );
        });

        // Register provider adapters
        $this->registerProviderAdapters();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register middleware
        $this->registerMiddleware();

        // Start monitoring if in production
        if ($this->app->environment('production')) {
            $this->startProductionMonitoring();
        }
    }

    /**
     * Register provider adapters
     */
    protected function registerProviderAdapters(): void
    {
        // Register adapters when they're created
    }

    /**
     * Register middleware
     */
    protected function registerMiddleware(): void
    {
        // Register middleware aliases if needed
    }

    /**
     * Start production monitoring
     */
    protected function startProductionMonitoring(): void
    {
        $monitoring = $this->app->make(ProductionMonitoringService::class);
        
        // Schedule monitoring tasks
        $this->app->booted(function () use ($monitoring) {
            $monitoring->startMonitoring();
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            NotificationServiceClass::class,
            AnalyticsService::class,
            MetricsService::class,
            AlertService::class,
            ClientWebhookService::class,
            ProviderHealthService::class,
            ProviderFailoverService::class,
            RateLimitService::class,
            WebhookProcessorService::class,
            ProductionMonitoringService::class,
        ];
    }
}
use App\Services\NotificationService;
use App\Services\RateLimitService;
use App\Services\ProductionMonitoringService;
use App\Services\AlertService;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
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
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Providers;

use App\Services\AlertService;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Raised by the scheduled `queue:monitor` when a queue backs up.
        Event::listen(QueueBusy::class, function (QueueBusy $event) {
            Log::warning('Queue backlog detected', [
                'connection' => $event->connection,
                'queue' => $event->queue,
                'size' => $event->size,
            ]);

            try {
                app(AlertService::class)->sendWarningAlert(
                    'Queue backlog',
                    "Queue {$event->queue} has {$event->size} pending jobs",
                    ['connection' => $event->connection, 'queue' => $event->queue, 'size' => $event->size]
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send queue backlog alert', ['error' => $e->getMessage()]);
            }
        });
    }
}

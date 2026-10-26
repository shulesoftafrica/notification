<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Requires the scheduler to run: `php artisan schedule:work` under supervisor
| (see deploy/supervisor/notification.conf) or a `schedule:run` cron entry.
*/

// Keep failed_jobs from growing without bound (one week kept for investigation).
Schedule::command('queue:prune-failed --hours=168')->daily()->onOneServer();

// Warn when queues back up (fires Illuminate\Queue\Events\QueueBusy, logged in AppServiceProvider).
Schedule::command('queue:monitor', [
    'redis:notifications-urgent,redis:notifications-high,redis:notifications,redis:notifications-low,redis:webhooks',
    '--max=' . (int) config('notification.maintenance.queue_monitor_max_jobs', 1000),
])->everyFiveMinutes()->onOneServer();

// Optional pruning of audit tables. Off unless a retention period is configured,
// because it permanently deletes rows.
Schedule::call(function () {
    $days = (int) config('notification.maintenance.log_retention_days', 0);
    if ($days <= 0) {
        return;
    }

    $cutoff = now()->subDays($days);

    foreach (['notification_logs', 'api_request_logs'] as $table) {
        do {
            $deleted = DB::table($table)
                ->whereIn('id', DB::table($table)->where('created_at', '<', $cutoff)->limit(5000)->select('id'))
                ->delete();
        } while ($deleted > 0);
    }
})->name('prune-notification-audit-logs')->dailyAt('02:30')->onOneServer();

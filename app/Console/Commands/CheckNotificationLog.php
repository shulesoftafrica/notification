<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckNotificationLog extends Command
{
    protected $signature = 'check:notification {id}';
    protected $description = 'Check notification log details';

    public function handle()
    {
        $id = $this->argument('id');
        
        $log = DB::table('notification_logs')->where('id', $id)->first();
        
        if (!$log) {
            $this->error("No notification found with ID: {$id}");
            return;
        }
        
        $this->info('=== Notification Log Details ===');
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $log->id],
                ['Channel', $log->channel],
                ['Recipient', $log->to],
                ['Status', $log->status],
                ['Provider', $log->provider],
                ['External ID', $log->external_id],
                ['Error Message', $log->error_message],
                ['Created At', $log->created_at],
                ['Sent At', $log->sent_at],
                ['Failed At', $log->failed_at],
                ['Duration (ms)', $log->duration_ms],
            ]
        );
        
        if ($log->provider_response) {
            $this->newLine();
            $this->info('Provider Response:');
            $this->line($log->provider_response);
        }
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckLastWasenderLog extends Command
{
    protected $signature = 'check:last-wasender';
    protected $description = 'Check the last Wasender notification log';

    public function handle()
    {
        $this->info('=== Last Wasender Notification ===');
        $this->newLine();

        $log = DB::table('notification_logs')
            ->where('provider', 'wasender')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$log) {
            $this->warn('No Wasender logs found');
            return Command::FAILURE;
        }

        $this->line("ID: {$log->id}");
        $this->line("To: {$log->recipient}");
        $this->line("Status: {$log->status}");
        $this->line("Provider: {$log->provider}");
        $this->line("Message: " . substr($log->message, 0, 50) . '...');
        $this->line("Created: {$log->created_at}");
        $this->newLine();

        if ($log->error_message) {
            $this->error("Error: {$log->error_message}");
        } else {
            $this->info("No error message recorded");
        }

        if ($log->provider_response) {
            $this->newLine();
            $this->info("Provider Response:");
            $response = json_decode($log->provider_response, true);
            $this->line(json_encode($response, JSON_PRETTY_PRINT));
        }

        if ($log->external_id) {
            $this->newLine();
            $this->info("External ID: {$log->external_id}");
        }

        return Command::SUCCESS;
    }
}
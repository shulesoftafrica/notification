<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DebugConfig extends Command
{
    protected $signature = 'debug:config';
    protected $description = 'Debug notification configuration structure';

    public function handle()
    {
        $config = config('notification');
        
        $this->info('Full config structure:');
        $this->line(json_encode($config, JSON_PRETTY_PRINT));
        
        $this->newLine();
        $this->info('Channels structure:');
        $this->line(json_encode($config['channels'] ?? 'not found', JSON_PRETTY_PRINT));
        
        $this->newLine();
        $this->info('SMS channel:');
        $this->line(json_encode($config['channels']['sms'] ?? 'not found', JSON_PRETTY_PRINT));
        
        return Command::SUCCESS;
    }
}

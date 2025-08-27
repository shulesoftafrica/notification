<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessNotificationQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:queue:work 
                            {--queue=default : The queue to process}
                            {--timeout=60 : Worker timeout in seconds}
                            {--memory=128 : Memory limit in MB}
                            {--max-jobs=100 : Maximum jobs to process}
                            {--sleep=3 : Sleep when no jobs available}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process notification queue with optimized settings';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting notification queue worker...');
        
        $options = [
            '--queue' => $this->option('queue'),
            '--timeout' => $this->option('timeout'),
            '--memory' => $this->option('memory'),
            '--max-jobs' => $this->option('max-jobs'),
            '--sleep' => $this->option('sleep'),
            '--tries' => 3,
            '--backoff' => '30,60,120',
            '--rest' => 3
        ];

        $this->info('Queue: ' . $this->option('queue'));
        $this->info('Timeout: ' . $this->option('timeout') . 's');
        $this->info('Memory: ' . $this->option('memory') . 'MB');
        $this->info('Max Jobs: ' . $this->option('max-jobs'));
        
        Artisan::call('queue:work', $options);
        
        $this->info($this->laravel['artisan']->output());
    }
}

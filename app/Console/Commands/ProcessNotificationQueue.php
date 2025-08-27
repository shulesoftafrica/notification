<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessNotification;

class ProcessNotificationQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:process-notifications {--limit=100} {--timeout=300} {--retry=3}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending notification queue jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $timeout = $this->option('timeout');
        $retries = $this->option('retry');

        $this->info('Starting notification queue processing...');
        $this->info("Limit: {$limit} jobs");
        $this->info("Timeout: {$timeout} seconds");
        $this->info("Max Retries: {$retries}");
        $this->newLine();

        $processed = 0;
        $successful = 0;
        $failed = 0;
        $startTime = time();

        try {
            while ($processed < $limit && (time() - $startTime) < $timeout) {
                // Get queue size
                $queueSize = $this->getQueueSize();
                
                if ($queueSize === 0) {
                    $this->info('No jobs in queue. Waiting for new jobs...');
                    sleep(5);
                    continue;
                }

                $this->line("Queue size: {$queueSize} jobs");

                // Process a batch of jobs
                $batchResult = $this->processBatch(min(10, $limit - $processed));
                
                $processed += $batchResult['processed'];
                $successful += $batchResult['successful'];
                $failed += $batchResult['failed'];

                $this->line("Processed: {$batchResult['processed']}, Successful: {$batchResult['successful']}, Failed: {$batchResult['failed']}");

                // Short pause between batches
                usleep(100000); // 0.1 seconds
            }

        } catch (\Exception $e) {
            $this->error('Queue processing failed: ' . $e->getMessage());
            Log::error('Notification queue processing failed', [
                'error' => $e->getMessage(),
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $failed
            ]);
            return 1;
        }

        $this->newLine();
        $this->info('Queue processing completed!');
        $this->info("Total Processed: {$processed}");
        $this->info("Successful: {$successful}");
        $this->info("Failed: {$failed}");
        $this->info("Success Rate: " . ($processed > 0 ? round(($successful / $processed) * 100, 2) : 0) . "%");
        $this->info("Duration: " . (time() - $startTime) . " seconds");

        return 0;
    }

    protected function getQueueSize(): int
    {
        try {
            // Get the queue size for the notifications queue
            $redis = app('redis');
            $queueName = 'queues:notifications';
            return $redis->llen($queueName);
        } catch (\Exception $e) {
            Log::warning('Failed to get queue size', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    protected function processBatch(int $batchSize): array
    {
        $processed = 0;
        $successful = 0;
        $failed = 0;

        for ($i = 0; $i < $batchSize; $i++) {
            try {
                // Run queue worker for a single job
                $exitCode = \Artisan::call('queue:work', [
                    '--queue' => 'notifications',
                    '--once' => true,
                    '--timeout' => 60,
                    '--memory' => 128,
                    '--tries' => 3
                ]);

                $processed++;

                if ($exitCode === 0) {
                    $successful++;
                } else {
                    $failed++;
                }

            } catch (\Exception $e) {
                $processed++;
                $failed++;
                Log::error('Failed to process queue job', [
                    'error' => $e->getMessage(),
                    'batch_index' => $i
                ]);
            }
        }

        return [
            'processed' => $processed,
            'successful' => $successful,
            'failed' => $failed
        ];
    }

    protected function getQueueStats(): array
    {
        try {
            $redis = app('redis');
            
            $stats = [
                'pending' => $redis->llen('queues:notifications'),
                'processing' => $redis->llen('queues:notifications:reserved'),
                'failed' => $redis->llen('queues:notifications:failed'),
                'delayed' => $redis->zcard('queues:notifications:delayed'),
            ];

            return $stats;
        } catch (\Exception $e) {
            Log::warning('Failed to get queue stats', ['error' => $e->getMessage()]);
            return [
                'pending' => 0,
                'processing' => 0,
                'failed' => 0,
                'delayed' => 0,
            ];
        }
    }

    protected function displayQueueStats(): void
    {
        $stats = $this->getQueueStats();
        
        $this->info('Queue Statistics:');
        $this->line("  Pending: {$stats['pending']}");
        $this->line("  Processing: {$stats['processing']}");
        $this->line("  Failed: {$stats['failed']}");
        $this->line("  Delayed: {$stats['delayed']}");
        $this->newLine();
    }
}

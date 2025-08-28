<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;

class TestMessageCreation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:message';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test message creation to verify recipient column fix';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Testing message creation...');
            
            $message = new Message();
            $message->channel = 'email';
            $message->recipient = 'test@example.com';
            $message->subject = 'Test Subject';
            $message->message = 'Test message content';
            $message->status = 'pending';
            $message->metadata = ['name' => 'Test User'];
            
            $message->save();
            
            $this->info("SUCCESS: Message created with ID: {$message->id}");
            $this->info("Recipient field stored correctly: {$message->recipient}");
            
            // Clean up
            $message->delete();
            $this->info("Test message deleted.");
            
        } catch (\Exception $e) {
            $this->error("ERROR: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}

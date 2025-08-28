<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\Message;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

try {
    echo "Testing message creation...\n";
    
    $message = new Message();
    $message->channel = 'email';
    $message->recipient = 'test@example.com';
    $message->subject = 'Test Subject';
    $message->message = 'Test message content';
    $message->status = 'pending';
    $message->metadata = ['name' => 'Test User'];
    
    $message->save();
    
    echo "SUCCESS: Message created with ID: " . $message->id . "\n";
    echo "Recipient field stored correctly: " . $message->recipient . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

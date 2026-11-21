<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel'); // email, sms, whatsapp
            $table->string('to'); // recipient
            $table->string('subject')->nullable(); // for email
            $table->text('message'); // message content
            $table->string('template_id')->nullable();
            $table->json('template_data')->nullable();
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('status')->default('queued'); // queued, sent, delivered, failed, cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('provider')->nullable(); // twilio, sendgrid, etc.
            $table->string('provider_message_id')->nullable();
            $table->string('client_webhook_url')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('error')->nullable();
            $table->decimal('cost', 10, 6)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('channel');
            $table->index('status');
            $table->index('created_at');
            $table->index(['channel', 'status']);
            $table->index(['to', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};

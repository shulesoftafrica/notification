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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->enum('channel', ['email', 'sms', 'whatsapp'])->index();
            $table->string('recipient')->index();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->enum('status', ['pending', 'queued', 'sending', 'sent', 'delivered', 'failed', 'cancelled'])->default('pending')->index();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->index();
            $table->string('provider')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            
            // Timestamps
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Error handling
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            
            // JSON data
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            
            // Webhook tracking
            $table->string('webhook_url')->nullable();
            $table->boolean('webhook_delivered')->default(false);
            $table->integer('webhook_attempts')->default(0);
            $table->text('webhook_error')->nullable();
            $table->timestamp('webhook_failed_at')->nullable();
            
            // Request tracking
            $table->string('api_key')->index();
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            
            // Cost tracking
            $table->decimal('cost_amount', 10, 4)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->integer('duration_ms')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['channel', 'status']);
            $table->index(['api_key', 'created_at']);
            $table->index(['scheduled_at', 'status']);
            $table->index(['created_at', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

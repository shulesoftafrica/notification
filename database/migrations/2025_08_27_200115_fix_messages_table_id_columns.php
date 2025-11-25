<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop foreign key constraints first
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
        });
        
        // Drop the entire table and recreate it cleanly
        Schema::dropIfExists('messages');
        
        Schema::create('messages', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->string('message_id')->unique()->nullable();
            $table->string('project_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('external_id')->nullable();
            $table->string('recipient');
            $table->string('channel'); // email, sms, whatsapp
            $table->string('template_id')->nullable();
            $table->json('variables')->nullable();
            $table->json('options')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('pending'); // pending, sent, delivered, failed, cancelled
            $table->string('priority')->default('normal'); // high, normal, low
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->decimal('cost', 10, 6)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Additional columns for specific channels
            $table->string('subject')->nullable(); // for email
            $table->text('message');
            $table->json('tags')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('api_key')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('webhook_delivered')->default(false);
            $table->integer('webhook_attempts')->default(0);
            $table->text('webhook_error')->nullable();
            $table->timestamp('webhook_failed_at')->nullable();
            $table->decimal('cost_amount', 10, 6)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->integer('duration_ms')->nullable();
        });
        
        // Note: Foreign key constraint with receipts will need to be handled separately
        // as receipts.message_id is string type while messages.id is bigint
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

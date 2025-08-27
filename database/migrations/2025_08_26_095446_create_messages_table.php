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
            $table->uuid('id')->primary();
            $table->string('message_id', 100)->unique();
            $table->string('project_id', 50);
            $table->string('tenant_id', 50);
            $table->string('idempotency_key', 255)->nullable();
            $table->string('external_id', 255)->nullable(); // Client's reference
            $table->json('recipient'); // to field (email, phone, name)
            $table->enum('channel', ['email', 'sms', 'whatsapp']);
            $table->string('template_id', 100);
            $table->json('variables')->nullable(); // Template variables
            $table->json('options')->nullable(); // priority, fallback_channels, etc.
            $table->json('metadata')->nullable(); // campaign_id, source, etc.
            $table->enum('status', ['queued', 'processing', 'sent', 'delivered', 'failed', 'cancelled'])->default('queued');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->decimal('cost', 10, 6)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
            
            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index('created_at');
            $table->index('idempotency_key');
            $table->index('scheduled_at');
            $table->index(['status', 'priority', 'scheduled_at']);
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

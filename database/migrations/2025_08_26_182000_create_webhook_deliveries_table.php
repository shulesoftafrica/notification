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
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained('messages')->onDelete('cascade');
            $table->string('webhook_url');
            $table->string('event_type'); // e.g., 'message.sent', 'message.delivered', 'message.failed'
            $table->json('payload');
            $table->integer('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->enum('status', ['pending', 'delivered', 'failed', 'retrying']);
            $table->integer('attempt_count')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('message_id');
            $table->index('webhook_url');
            $table->index('event_type');
            $table->index('status');
            $table->index('next_retry_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};

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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->onDelete('cascade');
            $table->string('provider'); // Provider name (e.g., 'twilio', 'sendgrid')
            $table->string('provider_message_id')->nullable();
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed', 'bounced', 'rejected']);
            $table->text('status_message')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('attempt_count')->default(1);
            $table->decimal('cost', 10, 6)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            // Indexes
            $table->index('message_id');
            $table->index('provider');
            $table->index('provider_message_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};

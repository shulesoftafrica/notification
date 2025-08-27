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
            $table->uuid('id')->primary();
            $table->string('message_id', 100);
            $table->string('provider', 50);
            $table->string('provider_message_id', 255);
            $table->enum('event_type', ['sent', 'delivered', 'failed', 'clicked', 'opened']);
            $table->json('raw_data'); // Full webhook payload
            $table->timestamp('event_timestamp');
            $table->timestamps();
            
            // Foreign key
            $table->foreign('message_id')->references('message_id')->on('messages')->onDelete('cascade');
            
            // Indexes
            $table->index('message_id');
            $table->index(['provider', 'provider_message_id']);
            $table->index('event_timestamp');
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

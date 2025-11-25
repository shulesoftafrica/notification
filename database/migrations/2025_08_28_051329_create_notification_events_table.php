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
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id');
            $table->string('event'); // sent, delivered, failed, opened, clicked, etc.
            $table->string('description')->nullable();
            $table->json('data')->nullable(); // event specific data
            $table->string('provider')->nullable();
            $table->string('provider_event_id')->nullable();
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('message_id')->references('id')->on('notification_logs')->onDelete('cascade');
            
            // Indexes
            $table->index('message_id');
            $table->index('event');
            $table->index('created_at');
            $table->index(['message_id', 'event']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};

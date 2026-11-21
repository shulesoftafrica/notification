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
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('level', ['info', 'warning', 'error', 'critical']);
            $table->string('title');
            $table->text('message');
            $table->string('category')->nullable(); // e.g., 'system', 'api', 'provider', 'security'
            $table->json('context')->nullable(); // Additional context data
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('resolution_notes')->nullable();
            $table->boolean('send_notification')->default(false);
            $table->json('notified_users')->nullable(); // Array of user IDs that were notified
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('level');
            $table->index('category');
            $table->index('is_resolved');
            $table->index('created_at');
            $table->index(['level', 'is_resolved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};

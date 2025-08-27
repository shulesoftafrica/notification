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
            $table->string('type', 100)->index();
            $table->enum('severity', ['info', 'warning', 'critical'])->index();
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('environment', 20)->index();
            $table->boolean('escalated')->default(false)->index();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            // Indexes for performance
            $table->index(['severity', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['escalated', 'created_at']);
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

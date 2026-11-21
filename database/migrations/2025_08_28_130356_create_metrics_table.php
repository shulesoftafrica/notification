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
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric'); // metric name like 'notifications.sent'
            $table->json('labels'); // labels like {"provider":"sendgrid","channel":"email"}
            $table->string('type'); // counter, histogram, gauge
            $table->decimal('value', 15, 6)->default(0); // metric value
            $table->timestamps();
            
            // Indexes for performance (excluding JSON column from composite indexes)
            $table->index('metric');
            $table->index('type');
            $table->index('created_at');
            $table->index(['metric', 'type']);
            $table->index(['metric', 'type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};

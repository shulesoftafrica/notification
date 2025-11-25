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
        Schema::create('provider_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->enum('channel', ['email', 'sms', 'whatsapp']);
            $table->string('provider_name'); // e.g., 'sendgrid', 'twilio', 'resend'
            $table->json('credentials'); // Encrypted provider credentials
            $table->json('settings')->nullable(); // Additional provider settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(0); // For failover ordering
            $table->integer('rate_limit')->nullable(); // Messages per minute/hour
            $table->string('rate_limit_period')->nullable(); // 'minute', 'hour', 'day'
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('project_id');
            $table->index('channel');
            $table->index('provider_name');
            $table->index('is_active');
            $table->index(['project_id', 'channel', 'is_active']);
            $table->index(['project_id', 'channel', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_configs');
    }
};

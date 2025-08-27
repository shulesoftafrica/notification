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
            $table->uuid('id')->primary();
            $table->string('project_id', 50);
            $table->string('tenant_id', 50);
            $table->enum('channel', ['email', 'sms', 'whatsapp']);
            $table->string('provider', 50); // sendgrid, twilio, etc.
            $table->integer('priority')->default(1); // Lower number = higher priority
            $table->boolean('enabled')->default(true);
            $table->json('config'); // API keys, settings (encrypted)
            $table->json('limits')->nullable(); // daily_limit, monthly_limit, rate_per_minute
            $table->json('cost_tracking')->nullable(); // cost_per_message, currency, billing_code
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
            
            // Indexes
            $table->index(['tenant_id', 'channel', 'enabled']);
            $table->index(['project_id', 'channel']);
            $table->index(['channel', 'provider']);
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

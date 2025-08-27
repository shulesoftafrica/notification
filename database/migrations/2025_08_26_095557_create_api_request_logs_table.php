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
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_id', 50);
            $table->string('tenant_id', 50)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('endpoint', 255);
            $table->string('method', 10);
            $table->integer('status_code');
            $table->integer('response_time_ms');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_headers')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            // Foreign key
            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
            
            // Indexes
            $table->index(['project_id', 'created_at']);
            $table->index('request_id');
            $table->index(['endpoint', 'method']);
            $table->index('status_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};

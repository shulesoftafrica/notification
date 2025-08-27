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
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_id', 50)->unique();
            $table->string('name');
            $table->string('api_key', 100)->unique();
            $table->text('secret_key'); // Will be encrypted
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
            $table->integer('rate_limit_per_minute')->default(1000);
            $table->integer('rate_limit_per_hour')->default(50000);
            $table->integer('rate_limit_per_day')->default(1000000);
            $table->text('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable(); // Will be encrypted
            $table->timestamps();
            
            // Indexes
            $table->index('api_key');
            $table->index('project_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

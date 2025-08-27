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
        Schema::create('project_tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_id', 50);
            $table->string('tenant_id', 50);
            $table->json('permissions'); // ["send_messages", "manage_templates"]
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();
            
            // Foreign key
            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['project_id', 'tenant_id']);
            
            // Indexes
            $table->index(['tenant_id', 'project_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tenants');
    }
};

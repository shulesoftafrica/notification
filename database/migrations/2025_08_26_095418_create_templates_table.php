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
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('template_id', 100);
            $table->string('name');
            $table->string('project_id', 50);
            $table->string('tenant_id', 50);
            $table->enum('channel', ['email', 'sms', 'whatsapp']);
            $table->string('locale', 10)->default('en');
            $table->string('version', 20)->default('1.0');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->json('content'); // subject, html_body, text_body, etc.
            $table->json('variables'); // variable definitions and validation rules
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['template_id', 'project_id', 'tenant_id']);
            
            // Indexes
            $table->index(['tenant_id', 'channel', 'status']);
            $table->index(['project_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};

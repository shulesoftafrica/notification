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
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('channel', ['email', 'sms', 'whatsapp']);
            $table->string('subject')->nullable(); // For email templates
            $table->text('body');
            $table->json('variables')->nullable(); // Template variables/placeholders
            $table->string('language', 10)->default('en');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('project_id');
            $table->index('slug');
            $table->index('channel');
            $table->index('is_active');
            $table->index(['project_id', 'channel']);
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

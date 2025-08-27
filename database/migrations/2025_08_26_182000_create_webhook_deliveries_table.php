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
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_id', 100)->unique();
            $table->string('project_id', 50)->index();
            $table->text('webhook_url');
            $table->string('event', 100)->index();
            $table->longText('payload');
            $table->integer('attempt_number')->default(1);
            $table->enum('status', ['pending', 'delivered', 'failed'])->default('pending')->index();
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['project_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['event', 'created_at']);
            
            // Foreign key
            $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};

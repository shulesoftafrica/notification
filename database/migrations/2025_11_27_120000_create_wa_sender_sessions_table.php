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
        Schema::create('wa_sender_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('schema_name');
            $table->string('name');
            $table->string('phone_number');
            $table->string('status')->default('disconnected');
            $table->boolean('account_protection')->default(true);
            $table->boolean('log_messages')->default(true);
            $table->boolean('read_incoming_messages')->default(false);
            $table->string('webhook_url')->nullable();
            $table->boolean('webhook_enabled')->default(false);
            $table->json('webhook_events')->nullable();
            $table->string('api_key')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('wasender_session_id')->nullable()->unique();
            $table->timestamps();

            $table->index('schema_name');
            $table->index('phone_number');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_sender_sessions');
    }
};

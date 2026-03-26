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
        Schema::create('sms_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('schema_name')->index();
            $table->string('sender_name')->nullable();
            $table->string('provider')->default('beem'); // beem, termii, twilio
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();

            // Add indexes for performance
            $table->index(['schema_name', 'provider']);
            $table->index(['schema_name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_sessions');
    }
};
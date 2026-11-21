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
        Schema::table('messages', function (Blueprint $table) {
            // Add channel column if it doesn't exist
            if (!Schema::hasColumn('messages', 'channel')) {
                $table->enum('channel', ['email', 'sms', 'whatsapp'])->after('id')->index();
            }
            
            // Remove type column if it exists
            if (Schema::hasColumn('messages', 'type')) {
                $table->dropColumn('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Add type column back
            if (!Schema::hasColumn('messages', 'type')) {
                $table->enum('type', ['email', 'sms', 'whatsapp'])->after('id')->index();
            }
            
            // Remove channel column
            if (Schema::hasColumn('messages', 'channel')) {
                $table->dropColumn('channel');
            }
        });
    }
};

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
            // Add schema_name column
            $table->string('schema_name')->index()->after('webhook_failed_at');
            
            // Remove api_key column
            $table->dropIndex(['api_key']); // Drop index first
            $table->dropColumn('api_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Add back api_key column
            $table->string('api_key')->index()->after('webhook_failed_at');
            
            // Remove schema_name column
            $table->dropIndex(['schema_name']); // Drop index first
            $table->dropColumn('schema_name');
        });
    }
};
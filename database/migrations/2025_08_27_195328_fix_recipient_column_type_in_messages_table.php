<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change recipient column from JSON to STRING
        DB::statement('ALTER TABLE messages ALTER COLUMN recipient TYPE VARCHAR(255) USING recipient::text');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change back to JSON (this might cause data loss)
        DB::statement('ALTER TABLE messages ALTER COLUMN recipient TYPE JSON USING recipient::json');
    }
};

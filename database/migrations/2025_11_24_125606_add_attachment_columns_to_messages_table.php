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
            // Add attachment column to store file path
            if (!Schema::hasColumn('messages', 'attachment')) {
                $table->string('attachment')->nullable()->after('message');
            }
            
            // Add attachment metadata column to store original filename, size, mime type
            if (!Schema::hasColumn('messages', 'attachment_metadata')) {
                $table->json('attachment_metadata')->nullable()->after('attachment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'attachment')) {
                $table->dropColumn('attachment');
            }
            
            if (Schema::hasColumn('messages', 'attachment_metadata')) {
                $table->dropColumn('attachment_metadata');
            }
        });
    }
};

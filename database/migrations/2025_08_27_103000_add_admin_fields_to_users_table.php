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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email');
            $table->boolean('is_super_admin')->default(false)->after('is_admin');
            $table->json('permissions')->nullable()->after('is_super_admin');
            $table->string('role')->default('user')->after('permissions'); // 'user', 'admin', 'super_admin'
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            // Indexes
            $table->index('is_admin');
            $table->index('is_super_admin');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin',
                'is_super_admin',
                'permissions',
                'role',
                'last_login_at',
                'last_login_ip'
            ]);
        });
    }
};

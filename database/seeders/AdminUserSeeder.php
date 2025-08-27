<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create primary admin user
        User::updateOrCreate(
            ['email' => 'admin@notification.local'],
            [
                'name' => 'Admin User',
                'email' => 'admin@notification.local',
                'password' => Hash::make('MySecurePassword123'),
                'email_verified_at' => now(),
                'is_admin' => true,
                'admin_permissions' => json_encode([
                    'dashboard' => true,
                    'users' => true,
                    'settings' => true,
                    'providers' => true,
                    'messages' => true,
                    'analytics' => true
                ])
            ]
        );

        // Create secondary admin user
        User::updateOrCreate(
            ['email' => 'admin@yourcompany.com'],
            [
                'name' => 'Company Admin',
                'email' => 'admin@yourcompany.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => true,
                'admin_permissions' => json_encode([
                    'dashboard' => true,
                    'users' => false,
                    'settings' => false,
                    'providers' => true,
                    'messages' => true,
                    'analytics' => true
                ])
            ]
        );

        // Create demo admin user
        User::updateOrCreate(
            ['email' => 'demo@admin.com'],
            [
                'name' => 'Demo Admin',
                'email' => 'demo@admin.com',
                'password' => Hash::make('demo123'),
                'email_verified_at' => now(),
                'is_admin' => true,
                'admin_permissions' => json_encode([
                    'dashboard' => true,
                    'users' => false,
                    'settings' => false,
                    'providers' => true,
                    'messages' => true,
                    'analytics' => false
                ])
            ]
        );

        $this->command->info('Admin users created successfully!');
        $this->command->table(
            ['Email', 'Password', 'Permissions'],
            [
                ['admin@notification.local', 'MySecurePassword123', 'Full Access'],
                ['admin@yourcompany.com', 'password', 'Limited Access'],
                ['demo@admin.com', 'demo123', 'Demo Access']
            ]
        );
    }
}

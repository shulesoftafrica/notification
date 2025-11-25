<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUsers = [
            [
                'name' => 'Super Admin',
                'email' => 'admin@notification.local',
                'password' => Hash::make('MySecurePassword123'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Analytics Admin', 
                'email' => 'analytics@notification.local',
                'password' => Hash::make('AnalyticsPass123'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Support Admin',
                'email' => 'support@notification.local', 
                'password' => Hash::make('SupportPass123'),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($adminUsers as $userData) {
            $existingUser = User::where('email', $userData['email'])->first();
            
            if (!$existingUser) {
                $user = User::create($userData);
                $this->command->info("Created admin user: {$user->email}");
            } else {
                $this->command->info("Admin user already exists: {$userData['email']}");
            }
        }

        $this->command->table(
            ['ID', 'Name', 'Email'],
            User::whereIn('email', ['admin@notification.local', 'analytics@notification.local', 'support@notification.local'])
                ->get()->map(function ($user) {
                    return [
                        $user->id,
                        $user->name,
                        $user->email,
                    ];
                })->toArray()
        );
    }
}

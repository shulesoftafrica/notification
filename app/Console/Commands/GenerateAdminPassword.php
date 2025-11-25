<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class GenerateAdminPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:generate-password {email} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate or update admin user password';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->option('password');

        // Generate random password if not provided
        if (!$password) {
            $password = $this->generateSecurePassword();
            $this->info("Generated password: {$password}");
        }

        // Find or create admin user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->info("Creating new admin user...");
            $user = User::create([
                'name' => 'Admin User',
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);
            $this->info("Admin user created successfully!");
        } else {
            $this->info("Updating existing user password...");
            $user->update([
                'password' => Hash::make($password),
                'role' => 'admin',
            ]);
            $this->info("Password updated successfully!");
        }

        $this->newLine();
        $this->info("Admin Login Details:");
        $this->info("Email: {$email}");
        $this->info("Password: {$password}");
        $this->info("Login URL: " . url('/admin/login'));
        $this->newLine();

        return 0;
    }

    /**
     * Generate a secure random password
     */
    protected function generateSecurePassword(): string
    {
        $length = 16;
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $password;
    }
}

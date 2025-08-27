<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Generate Admin Password Hash Command
 * Creates hashed passwords for admin configuration
 */
class GenerateAdminPassword extends Command
{
    protected $signature = 'admin:generate-password {password}';
    protected $description = 'Generate a hashed password for admin configuration';

    public function handle(): int
    {
        $password = $this->argument('password');
        
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters long');
            return Command::FAILURE;
        }

        $hashedPassword = Hash::make($password);
        
        $this->info('Generated hashed password:');
        $this->line($hashedPassword);
        $this->newLine();
        
        $this->comment('Add this to your config/notification.php file:');
        $this->line("'your-email@domain.com' => '{$hashedPassword}',");
        $this->newLine();
        
        $this->warn('Make sure to keep your password secure and never commit it to version control.');
        
        return Command::SUCCESS;
    }
}

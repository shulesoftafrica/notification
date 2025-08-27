<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'is_admin',
        'admin_permissions',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'admin_permissions' => 'array',
        ];
    }

    /**
     * Check if user has specific admin permission
     */
    public function hasAdminPermission(string $permission): bool
    {
        if (!$this->is_admin) {
            return false;
        }

        $permissions = $this->admin_permissions ?? [];
        return $permissions[$permission] ?? false;
    }

    /**
     * Check if user is a super admin (has all permissions)
     */
    public function isSuperAdmin(): bool
    {
        if (!$this->is_admin) {
            return false;
        }

        $permissions = $this->admin_permissions ?? [];
        return ($permissions['manage_users'] ?? false) && 
               ($permissions['manage_settings'] ?? false);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login' => now()]);
    }
}

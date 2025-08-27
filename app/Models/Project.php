<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Project extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'name',
        'api_key',
        'secret_key',
        'status',
        'rate_limit_per_minute',
        'rate_limit_per_hour',
        'rate_limit_per_day',
        'webhook_url',
        'webhook_secret',
    ];

    protected $hidden = [
        'secret_key',
        'webhook_secret',
    ];

    protected $casts = [
        'rate_limit_per_minute' => 'integer',
        'rate_limit_per_hour' => 'integer',
        'rate_limit_per_day' => 'integer',
    ];

    /**
     * Automatically encrypt sensitive fields
     */
    public function setSecretKeyAttribute($value)
    {
        $this->attributes['secret_key'] = Crypt::encryptString($value);
    }

    public function getSecretKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setWebhookSecretAttribute($value)
    {
        $this->attributes['webhook_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getWebhookSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Relationships
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(ProjectTenant::class, 'project_id', 'project_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'project_id', 'project_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'project_id', 'project_id');
    }

    public function providerConfigs(): HasMany
    {
        return $this->hasMany(ProviderConfig::class, 'project_id', 'project_id');
    }

    public function apiRequestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class, 'project_id', 'project_id');
    }

    /**
     * Generate API credentials
     */
    public static function generateApiKey(string $projectId): string
    {
        return 'proj_' . strtolower($projectId) . '_' . bin2hex(random_bytes(8));
    }

    public static function generateSecretKey(): string
    {
        return 'sk_' . bin2hex(random_bytes(32));
    }

    /**
     * Check if project is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}space App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    //
}

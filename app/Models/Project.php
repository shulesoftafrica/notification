<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'api_key',
        'api_secret',
        'webhook_url',
        'webhook_secret',
        'active',
        'settings',
        'rate_limits',
        'allowed_ips',
        'owner_email',
        'plan',
        'usage_stats'
    ];

    protected $casts = [
        'active' => 'boolean',
        'settings' => 'array',
        'rate_limits' => 'array',
        'allowed_ips' => 'array',
        'usage_stats' => 'array',
    ];

    protected $hidden = [
        'api_secret',
        'webhook_secret'
    ];

    /**
     * Boot method to generate API keys
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->api_key)) {
                $project->api_key = 'nk_' . Str::random(32);
            }
            if (empty($project->api_secret)) {
                $project->api_secret = Str::random(64);
            }
            if (empty($project->webhook_secret)) {
                $project->webhook_secret = Str::random(32);
            }
        });
    }

    /**
     * Scope for active projects
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Get messages for this project
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'api_key', 'api_key');
    }

    /**
     * Get webhook deliveries for this project
     */
    public function webhookDeliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Get API request logs for this project
     */
    public function apiRequestLogs()
    {
        return $this->hasMany(ApiRequestLog::class, 'api_key', 'api_key');
    }

    /**
     * Check if IP is allowed
     */
    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true; // No IP restrictions
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Check rate limit
     */
    public function checkRateLimit(string $endpoint = 'default'): bool
    {
        $limits = $this->rate_limits ?? [];
        $limit = $limits[$endpoint] ?? $limits['default'] ?? 1000;

        // Get current usage count (last hour)
        $currentHour = now()->format('Y-m-d H');
        $usageKey = "rate_limit:{$this->api_key}:{$endpoint}:{$currentHour}";
        
        $currentUsage = cache()->get($usageKey, 0);
        
        return $currentUsage < $limit;
    }

    /**
     * Increment rate limit counter
     */
    public function incrementRateLimit(string $endpoint = 'default'): void
    {
        $currentHour = now()->format('Y-m-d H');
        $usageKey = "rate_limit:{$this->api_key}:{$endpoint}:{$currentHour}";
        
        cache()->increment($usageKey);
        cache()->put($usageKey, cache()->get($usageKey, 1), now()->addHour());
    }

    /**
     * Get current rate limit usage
     */
    public function getRateLimitUsage(string $endpoint = 'default'): array
    {
        $limits = $this->rate_limits ?? [];
        $limit = $limits[$endpoint] ?? $limits['default'] ?? 1000;
        
        $currentHour = now()->format('Y-m-d H');
        $usageKey = "rate_limit:{$this->api_key}:{$endpoint}:{$currentHour}";
        
        $currentUsage = cache()->get($usageKey, 0);
        
        return [
            'limit' => $limit,
            'used' => $currentUsage,
            'remaining' => max(0, $limit - $currentUsage),
            'reset_time' => now()->addHour()->startOfHour()
        ];
    }

    /**
     * Update usage statistics
     */
    public function updateUsageStats(array $stats): void
    {
        $currentStats = $this->usage_stats ?? [];
        $updatedStats = array_merge_recursive($currentStats, $stats);
        
        $this->update(['usage_stats' => $updatedStats]);
    }

    /**
     * Get project statistics
     */
    public function getStats(int $days = 30): array
    {
        $since = now()->subDays($days);
        
        return [
            'messages_sent' => $this->messages()->where('created_at', '>=', $since)->count(),
            'messages_delivered' => $this->messages()->where('status', 'delivered')->where('created_at', '>=', $since)->count(),
            'messages_failed' => $this->messages()->where('status', 'failed')->where('created_at', '>=', $since)->count(),
            'success_rate' => $this->calculateSuccessRate($days),
            'webhook_deliveries' => $this->webhookDeliveries()->where('created_at', '>=', $since)->count(),
            'api_requests' => $this->apiRequestLogs()->where('created_at', '>=', $since)->count(),
        ];
    }

    /**
     * Calculate success rate
     */
    public function calculateSuccessRate(int $days = 30): float
    {
        $since = now()->subDays($days);
        $total = $this->messages()->where('created_at', '>=', $since)->count();
        
        if ($total === 0) {
            return 0.0;
        }
        
        $successful = $this->messages()
            ->whereIn('status', ['sent', 'delivered'])
            ->where('created_at', '>=', $since)
            ->count();
            
        return round(($successful / $total) * 100, 2);
    }
}

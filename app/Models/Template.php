<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'subject',
        'content',
        'description',
        'variables',
        'active',
        'created_by',
        'metadata'
    ];

    protected $casts = [
        'variables' => 'array',
        'metadata' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for specific type
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get messages using this template
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'template_id');
    }

    /**
     * Check if template has required variables
     */
    public function hasRequiredVariables(array $data): bool
    {
        $requiredVars = $this->variables ?? [];
        return empty(array_diff($requiredVars, array_keys($data)));
    }

    /**
     * Get template usage statistics
     */
    public function getUsageStats(): array
    {
        return [
            'total_uses' => $this->messages()->count(),
            'successful_uses' => $this->messages()->whereIn('status', ['sent', 'delivered'])->count(),
            'failed_uses' => $this->messages()->where('status', 'failed')->count(),
            'last_used' => $this->messages()->latest()->first()?->created_at,
        ];
    }
}

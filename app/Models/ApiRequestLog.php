<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_key',
        'project_id',
        'endpoint',
        'method',
        'ip_address',
        'user_agent',
        'request_headers',
        'request_body',
        'response_status',
        'response_headers',
        'response_body',
        'response_time_ms',
        'error_message',
        'metadata'
    ];

    protected $casts = [
        'request_headers' => 'array',
        'response_headers' => 'array',
        'metadata' => 'array',
        'response_time_ms' => 'integer',
    ];

    /**
     * Get the project that owns this log entry
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Scope for successful requests
     */
    public function scopeSuccessful($query)
    {
        return $query->whereBetween('response_status', [200, 299]);
    }

    /**
     * Scope for failed requests
     */
    public function scopeFailed($query)
    {
        return $query->where('response_status', '>=', 400);
    }

    /**
     * Scope for recent requests
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for specific endpoint
     */
    public function scopeEndpoint($query, $endpoint)
    {
        return $query->where('endpoint', 'LIKE', "%{$endpoint}%");
    }

    /**
     * Get formatted response time
     */
    public function getFormattedResponseTimeAttribute(): string
    {
        if ($this->response_time_ms < 1000) {
            return $this->response_time_ms . 'ms';
        } else {
            return round($this->response_time_ms / 1000, 2) . 's';
        }
    }

    /**
     * Check if request was successful
     */
    public function isSuccessful(): bool
    {
        return $this->response_status >= 200 && $this->response_status < 300;
    }

    /**
     * Get sanitized request body (remove sensitive data)
     */
    public function getSanitizedRequestBody(): ?array
    {
        if (!$this->request_body) {
            return null;
        }

        $body = is_string($this->request_body) ? json_decode($this->request_body, true) : $this->request_body;
        
        if (!is_array($body)) {
            return null;
        }

        // Remove sensitive fields
        $sensitiveFields = ['password', 'api_key', 'secret', 'token', 'authorization'];
        
        return $this->sanitizeArray($body, $sensitiveFields);
    }

    /**
     * Recursively sanitize array to remove sensitive data
     */
    private function sanitizeArray(array $data, array $sensitiveFields): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value, $sensitiveFields);
            }
        }

        return $data;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class ThrottlingController extends Controller
{
    /**
     * Get throttling status for current user/IP
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $redis = Redis::connection(config('throttling.redis_connection', 'default'));
            
            // Get throttling key for current request
            $key = $this->resolveRequestSignature($request, '/notifications/send');
            $bulkKey = $this->resolveRequestSignature($request, '/notifications/bulk/send');
            
            // Get current counts
            $sendCount = (int) $redis->get($key) ?: 0;
            $bulkCount = (int) $redis->get($bulkKey) ?: 0;
            
            // Get TTLs
            $sendTtl = $redis->ttl($key);
            $bulkTtl = $redis->ttl($bulkKey);
            
            $config = config('throttling.notifications');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'single_notifications' => [
                        'current_attempts' => $sendCount,
                        'max_attempts' => $config['send']['max_attempts'],
                        'remaining' => max(0, $config['send']['max_attempts'] - $sendCount),
                        'reset_in_seconds' => $sendTtl > 0 ? $sendTtl : 0,
                        'reset_at' => $sendTtl > 0 ? now()->addSeconds($sendTtl)->toISOString() : null
                    ],
                    'bulk_notifications' => [
                        'current_attempts' => $bulkCount,
                        'max_attempts' => $config['bulk_send']['max_attempts'],
                        'remaining' => max(0, $config['bulk_send']['max_attempts'] - $bulkCount),
                        'reset_in_seconds' => $bulkTtl > 0 ? $bulkTtl : 0,
                        'reset_at' => $bulkTtl > 0 ? now()->addSeconds($bulkTtl)->toISOString() : null
                    ],
                    'identifier' => $this->getIdentifierType($request),
                    'timestamp' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Throttling status check failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to check throttling status',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Clear throttling for current user/IP (admin only)
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            $redis = Redis::connection(config('throttling.redis_connection', 'default'));
            
            // Get all possible throttling keys for current request
            $keys = [
                $this->resolveRequestSignature($request, '/notifications/send'),
                $this->resolveRequestSignature($request, '/notifications/bulk/send')
            ];
            
            $cleared = 0;
            foreach ($keys as $key) {
                if ($redis->del($key)) {
                    $cleared++;
                }
            }
            
            Log::info('Throttling cleared', [
                'keys_cleared' => $cleared,
                'ip' => $request->ip(),
                'identifier' => $this->getIdentifierType($request)
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Throttling cleared successfully',
                'keys_cleared' => $cleared
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clear throttling', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear throttling',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Resolve request signature for throttling key
     */
    protected function resolveRequestSignature(Request $request, string $endpoint): string
    {
        $prefix = config('throttling.key_prefix', 'throttle');
        
        $apiKey = $request->attributes->get('api_key') ?? $request->header('Authorization');
        
        if ($apiKey) {
            $identifier = 'api_key:' . md5($apiKey);
        } else {
            $identifier = 'ip:' . $request->ip();
        }
        
        $endpointKey = str_replace('/', ':', trim($endpoint, '/'));
        
        return "{$prefix}:{$endpointKey}:{$identifier}";
    }
    
    /**
     * Get identifier type for current request
     */
    protected function getIdentifierType(Request $request): string
    {
        $apiKey = $request->attributes->get('api_key') ?? $request->header('Authorization');
        return $apiKey ? 'api_key' : 'ip_address';
    }
}
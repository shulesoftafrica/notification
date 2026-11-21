<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProject
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for API key in header
        $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Please provide an API key in X-API-Key header'
            ], 401);
        }

        // Remove Bearer prefix if present
        $apiKey = str_replace('Bearer ', '', $apiKey);

        // Validate API key format (basic validation)
        if (strlen($apiKey) < 32 || !ctype_alnum(str_replace(['-', '_'], '', $apiKey))) {
            return response()->json([
                'error' => 'Invalid API key format',
                'message' => 'API key must be at least 32 characters and contain only alphanumeric characters, hyphens, and underscores'
            ], 401);
        }

        // Store the API key for use in controllers/services
        $request->attributes->set('api_key', $apiKey);
        
        // You can add database validation here if needed
        // Example: Check if API key exists in projects table
        /*
        $project = \App\Models\Project::where('api_key', $apiKey)->first();
        if (!$project) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is not valid'
            ], 401);
        }
        $request->attributes->set('project', $project);
        */

        return $next($request);
    }
}

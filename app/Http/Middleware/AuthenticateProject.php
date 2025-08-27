<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProject
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Extract headers
        $apiKey = $request->header('X-API-Key');
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $projectId = $request->header('X-Project-ID');
        $tenantId = $request->header('X-Tenant-ID');
        $requestId = $request->header('X-Request-ID');

        // Check required headers
        if (!$apiKey || !$signature || !$timestamp || !$projectId || !$tenantId) {
            return response()->json([
                'error' => [
                    'code' => 'MISSING_HEADERS',
                    'message' => 'Required headers missing',
                    'details' => [
                        'required_headers' => [
                            'X-API-Key',
                            'X-Signature', 
                            'X-Timestamp',
                            'X-Project-ID',
                            'X-Tenant-ID'
                        ]
                    ],
                    'trace_id' => $requestId
                ]
            ], 400);
        }

        // 2. Validate timestamp (prevent replay attacks)
        if (abs(time() - $timestamp) > 300) { // 5 minutes
            return response()->json([
                'error' => [
                    'code' => 'REQUEST_EXPIRED',
                    'message' => 'Request timestamp is too old',
                    'details' => [
                        'max_age_seconds' => 300,
                        'current_timestamp' => time(),
                        'request_timestamp' => $timestamp
                    ],
                    'trace_id' => $requestId
                ]
            ], 401);
        }

        // 3. Lookup project credentials
        $project = Project::where('api_key', $apiKey)
                         ->where('project_id', $projectId)
                         ->where('status', 'active')
                         ->first();

        if (!$project) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_PROJECT',
                    'message' => 'Invalid API key or project ID',
                    'trace_id' => $requestId
                ]
            ], 401);
        }

        // 4. Verify HMAC signature
        $expectedSignature = $this->generateSignature(
            $request->method(),
            $request->getRequestUri(),
            $timestamp,
            $request->getContent(),
            $project->secret_key
        );

        if (!hash_equals($expectedSignature, str_replace('sha256=', '', $signature))) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_SIGNATURE',
                    'message' => 'HMAC signature verification failed',
                    'details' => [
                        'expected_format' => 'X-Signature: sha256=<hmac_hash>',
                        'signing_string_format' => '{method}\\n{uri}\\n{timestamp}\\n{body_hash}'
                    ],
                    'trace_id' => $requestId
                ]
            ], 401);
        }

        // 5. Validate tenant access
        $tenant = $project->tenants()->where('tenant_id', $tenantId)->first();
        if (!$tenant || !$tenant->isActive()) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TENANT',
                    'message' => 'Tenant access denied or inactive',
                    'trace_id' => $requestId
                ]
            ], 403);
        }

        // 6. Attach authenticated data to request
        $request->merge([
            'authenticated_project' => $project,
            'authenticated_tenant' => $tenant,
            'authenticated_tenant_id' => $tenantId,
            'request_id' => $requestId
        ]);

        return $next($request);
    }

    /**
     * Generate HMAC signature for verification
     */
    private function generateSignature(string $method, string $uri, int $timestamp, string $body, string $secretKey): string
    {
        $bodyHash = hash('sha256', $body);
        $signingString = "{$method}\n{$uri}\n{$timestamp}\n{$bodyHash}";
        return hash_hmac('sha256', $signingString, $secretKey);
    }
}

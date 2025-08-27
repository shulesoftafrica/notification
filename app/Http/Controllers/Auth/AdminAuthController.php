<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Admin Authentication Controller
 * Handles login/logout for admin dashboard access
 */
class AdminAuthController extends Controller
{
    /**
     * Admin login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $request->input('email');
        $password = $request->input('password');

        // Find admin user in database
        $user = \App\Models\User::where('email', $email)
            ->where('is_admin', true)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            // Log failed attempt
            \Log::warning('Admin login attempt failed', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'error' => 'Invalid credentials'
            ], 401);
        }

        // Update last login info
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip()
        ]);

        // Generate session token
        $token = Str::random(64);
        $sessionKey = "admin_session:{$token}";
        
        // Store session in Redis with 8 hour expiry
        Cache::put($sessionKey, [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'permissions' => $user->admin_permissions ?? [],
            'ip' => $request->ip(),
            'logged_in_at' => now()->toISOString(),
            'last_activity' => now()->toISOString()
        ], 8 * 60 * 60); // 8 hours

        // Log successful login
        \Log::info('Admin login successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'token' => substr($token, 0, 8) . '...' // Only log first 8 chars
        ]);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => $user->admin_permissions ?? []
            ],
            'expires_in' => 8 * 60 * 60 // 8 hours in seconds
        ]);
    }

    /**
     * Admin logout
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        
        if ($token) {
            $sessionKey = "admin_session:{$token}";
            $session = Cache::get($sessionKey);
            
            if ($session) {
                Cache::forget($sessionKey);
                
                \Log::info('Admin logout', [
                    'email' => $session['email'] ?? 'unknown',
                    'ip' => $request->ip()
                ]);
            }
        }

        return response()->json([
            'message' => 'Logout successful'
        ]);
    }

    /**
     * Get current admin user info
     */
    public function me(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        
        if (!$token) {
            return response()->json(['error' => 'No token provided'], 401);
        }

        $sessionKey = "admin_session:{$token}";
        $session = Cache::get($sessionKey);

        if (!$session) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        // Update last activity
        $session['last_activity'] = now()->toISOString();
        Cache::put($sessionKey, $session, 8 * 60 * 60);

        return response()->json([
            'email' => $session['email'],
            'name' => $session['name'] ?? 'Admin User',
            'permissions' => $session['permissions'] ?? [],
            'logged_in_at' => $session['logged_in_at'],
            'last_activity' => $session['last_activity'],
            'ip' => $session['ip']
        ]);
    }

    /**
     * Refresh admin token
     */
    public function refresh(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        
        if (!$token) {
            return response()->json(['error' => 'No token provided'], 401);
        }

        $sessionKey = "admin_session:{$token}";
        $session = Cache::get($sessionKey);

        if (!$session) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        // Generate new token
        $newToken = Str::random(64);
        $newSessionKey = "admin_session:{$newToken}";
        
        // Update session with new token
        $session['last_activity'] = now()->toISOString();
        Cache::put($newSessionKey, $session, 8 * 60 * 60);
        
        // Remove old token
        Cache::forget($sessionKey);

        return response()->json([
            'message' => 'Token refreshed',
            'token' => $newToken,
            'expires_in' => 8 * 60 * 60
        ]);
    }

    /**
     * List active admin sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        // This would require scanning Redis keys in production
        // For now, return current session info
        $token = $request->bearerToken() ?? $request->input('token');
        
        if (!$token) {
            return response()->json(['error' => 'No token provided'], 401);
        }

        $sessionKey = "admin_session:{$token}";
        $session = Cache::get($sessionKey);

        if (!$session) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        return response()->json([
            'active_sessions' => [
                [
                    'email' => $session['email'],
                    'ip' => $session['ip'],
                    'logged_in_at' => $session['logged_in_at'],
                    'last_activity' => $session['last_activity'],
                    'current' => true
                ]
            ]
        ]);
    }
}

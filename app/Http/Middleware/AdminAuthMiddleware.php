<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required',
                    'message' => 'Please login to access admin panel',
                ], 401);
            }
            
            return redirect()->route('admin.login')
                ->with('error', 'Please login to access admin panel');
        }

        // Check if user is admin
        $user = Auth::user();
        if (!$user->is_admin) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient privileges',
                    'message' => 'Admin access required',
                ], 403);
            }
            
            Auth::logout();
            return redirect()->route('admin.login')
                ->with('error', 'Admin access required');
        }

        // Update last login timestamp
        if (method_exists($user, 'updateLastLogin')) {
            $user->updateLastLogin();
        }

        return $next($request);
    }
}

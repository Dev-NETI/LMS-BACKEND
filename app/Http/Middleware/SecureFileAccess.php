<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\TrainingMaterial;

class SecureFileAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Rate limiting for file access attempts
        $key = 'file-access:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 60)) { // 60 attempts per minute
            return response()->json([
                'success' => false,
                'message' => 'Too many file access attempts'
            ], 429);
        }

        RateLimiter::hit($key, 60);

        // Ensure user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Get the training material from the route
        $trainingMaterialId = $request->route('trainingMaterial');

        if ($trainingMaterialId) {
            // Check if user has permission to access this specific file
            $material = TrainingMaterial::find($trainingMaterialId->id ?? $trainingMaterialId);

            if (!$material) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // For admin users, allow access to all materials
            // For regular users, you can add additional checks here
            if (Auth::guard('admin-sanctum')->check()) {
                // Admin has access
                return $next($request);
            } elseif (Auth::guard('trainee-sanctum')->check()) {
                // Trainee has access to their own materials
                return $next($request);
            }

            // Add additional permission checks here for other user types
            // For now, require admin access for file operations
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }

        return $next($request);
    }
}

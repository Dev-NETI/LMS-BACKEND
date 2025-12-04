<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\TrainingMaterial;
use App\Models\CourseContent;

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

        // Get the content from the route (either training material or course content)
        $trainingMaterialId = $request->route('trainingMaterial');
        $courseContentId = $request->route('courseContent');

        if ($trainingMaterialId || $courseContentId) {
            $material = null;
            
            if ($trainingMaterialId) {
                // Check training material access
                $material = TrainingMaterial::find($trainingMaterialId->id ?? $trainingMaterialId);
            } elseif ($courseContentId) {
                // Check course content access
                $material = CourseContent::find($courseContentId->id ?? $courseContentId);
            }

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
                // Trainee has access to materials
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

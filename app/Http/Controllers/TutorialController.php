<?php

namespace App\Http\Controllers;

use App\Models\Tutorial;
use App\Services\SecureFileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TutorialController extends Controller
{
    protected SecureFileService $secureFileService;

    public function __construct(SecureFileService $secureFileService)
    {
        $this->secureFileService = $secureFileService;
    }

    /**
     * Get all tutorials for the dashboard feed
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'nullable|in:user_manual,quality_procedure,tutorial',
            'is_active' => 'nullable',
            'per_page' => 'nullable|integer|min:1|max:50'
        ]);

        $query = Tutorial::with('uploadedBy');

        if (!empty($validated['category'])) {
            $query->byCategory($validated['category']);
        }

        if (isset($validated['is_active'])) {
            if ($validated['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        } else {
            // Default to active only
            $query->active();
        }

        $perPage = $validated['per_page'] ?? 10;
        $tutorials = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tutorials->items(),
            'pagination' => [
                'current_page' => $tutorials->currentPage(),
                'per_page' => $tutorials->perPage(),
                'total' => $tutorials->total(),
                'total_pages' => $tutorials->lastPage(),
            ]
        ]);
    }

    /**
     * Get tutorial statistics for dashboard
     */
    public function getStats(): JsonResponse
    {
        $stats = [
            'total_tutorials' => Tutorial::active()->count(),
            'total_user_manuals' => Tutorial::active()->byCategory('user_manual')->count(),
            'total_quality_procedures' => Tutorial::active()->byCategory('quality_procedure')->count(),
            'total_video_tutorials' => Tutorial::active()->byCategory('tutorial')->count(),
            'total_views' => Tutorial::active()->sum('total_views'),
            'recent_uploads' => Tutorial::active()->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Store a new tutorial
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'video' => 'required|file|mimes:mp4,webm,avi,mov|max:512000', // 500MB max
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'duration_seconds' => 'nullable|integer|min:0',
            'category' => 'required|in:user_manual,quality_procedure,tutorial',
        ]);

        $videoFile = $request->file('video');

        // Store video file securely with encryption
        $secureVideoData = $this->secureFileService->storeSecureFile($videoFile);

        $tutorial = Tutorial::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'video_file_name' => $secureVideoData['original_name'],
            'video_file_path' => $secureVideoData['encrypted_path'],
            'video_file_type' => $secureVideoData['mime_type'],
            'video_file_size' => $secureVideoData['size'],
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'category' => $validated['category'],
            'uploaded_by_user_id' => Auth::id(),
        ]);

        $tutorial->load('uploadedBy');

        Log::info('Tutorial uploaded', [
            'user_id' => Auth::id(),
            'tutorial_id' => $tutorial->id,
            'title' => $tutorial->title,
            'category' => $tutorial->category
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tutorial uploaded successfully',
            'tutorial' => $tutorial
        ], 201);
    }

    /**
     * Get a single tutorial
     */
    public function show(Tutorial $tutorial): JsonResponse
    {
        $tutorial->load('uploadedBy');

        return response()->json([
            'success' => true,
            'tutorial' => $tutorial
        ]);
    }

    /**
     * Update a tutorial
     */
    public function update(Request $request, Tutorial $tutorial): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'duration_seconds' => 'nullable|integer|min:0',
            'category' => 'sometimes|required|in:user_manual,quality_procedure,tutorial',
            'is_active' => 'sometimes|boolean'
        ]);

        $tutorial->update($validated);
        $tutorial->load('uploadedBy');

        Log::info('Tutorial updated', [
            'user_id' => Auth::id(),
            'tutorial_id' => $tutorial->id,
            'title' => $tutorial->title
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tutorial updated successfully',
            'tutorial' => $tutorial
        ]);
    }

    /**
     * Delete a tutorial
     */
    public function destroy(Tutorial $tutorial): JsonResponse
    {
        // Delete the encrypted video file
        if ($this->secureFileService->secureFileExists($tutorial->video_file_path)) {
            $this->secureFileService->deleteSecureFile($tutorial->video_file_path);
        }

        Log::info('Tutorial deleted', [
            'user_id' => Auth::id(),
            'tutorial_id' => $tutorial->id,
            'title' => $tutorial->title
        ]);

        $tutorial->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tutorial deleted successfully'
        ]);
    }

    /**
     * View/stream tutorial video
     */
    public function viewVideo(Tutorial $tutorial)
    {
        // Increment view count
        $tutorial->incrementViews();

        Log::info('Tutorial video viewed', [
            'user_id' => Auth::id(),
            'tutorial_id' => $tutorial->id,
            'title' => $tutorial->title,
            'total_views' => $tutorial->total_views + 1
        ]);

        if (!$this->secureFileService->secureFileExists($tutorial->video_file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Video file not found'
            ], 404);
        }

        $decryptedContent = $this->secureFileService->getSecureFile($tutorial->video_file_path);

        if ($decryptedContent === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to access video file'
            ], 500);
        }

        return response($decryptedContent, 200, [
            'Content-Type' => $tutorial->video_file_type,
            'Content-Disposition' => 'inline; filename="' . $tutorial->video_file_name . '"',
            'Content-Length' => strlen($decryptedContent)
        ]);
    }

    /**
     * Bulk delete tutorials
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:tutorials,id'
        ]);

        $tutorials = Tutorial::whereIn('id', $validated['ids'])->get();

        $deletedCount = 0;
        foreach ($tutorials as $tutorial) {
            // Delete video file
            if ($this->secureFileService->secureFileExists($tutorial->video_file_path)) {
                $this->secureFileService->deleteSecureFile($tutorial->video_file_path);
            }

            $tutorial->delete();
            $deletedCount++;
        }

        Log::info('Bulk delete tutorials', [
            'user_id' => Auth::id(),
            'deleted_count' => $deletedCount,
            'tutorial_ids' => $validated['ids']
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} tutorial(s) deleted successfully",
            'deleted_count' => $deletedCount
        ]);
    }
}

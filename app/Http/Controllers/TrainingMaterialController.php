<?php

namespace App\Http\Controllers;

use App\Models\TrainingMaterial;
use App\Services\SecureFileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TrainingMaterialController extends Controller
{
    protected SecureFileService $secureFileService;

    public function __construct(SecureFileService $secureFileService)
    {
        $this->secureFileService = $secureFileService;
    }
    public function index(Request $request): JsonResponse
    {
        $query = TrainingMaterial::active()->with('uploadedBy');

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('file_category_type')) {
            $query->byCategory($request->file_category_type);
        }

        $materials = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $materials
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|integer|exists:main_db.tblcourses,courseid',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'file' => 'required|file|mimes:pdf|max:50000', // 50MB max, PDF only
            'file_category_type' => 'required|in:handout,document,manual',
            'order' => 'integer|min:0'
        ]);

        $file = $request->file('file');

        // Store file securely with encryption
        $secureFileData = $this->secureFileService->storeSecureFile($file);

        $material = TrainingMaterial::create([
            'course_id' => $validated['course_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'file_name' => $secureFileData['original_name'],
            'file_path' => $secureFileData['encrypted_path'],
            'file_type' => $secureFileData['mime_type'],
            'file_category_type' => $validated['file_category_type'],
            'file_size' => $secureFileData['size'],
            'order' => $validated['order'] ?? 0,
            'uploaded_by_user_id' => Auth::id(),
        ]);

        // Load the uploadedBy relationship for the response
        $material->load('uploadedBy');

        return response()->json([
            'success' => true,
            'message' => 'Training material uploaded successfully',
            'material' => $material
        ], 201);
    }

    public function show(TrainingMaterial $trainingMaterial): JsonResponse
    {
        $trainingMaterial->load('uploadedBy');

        return response()->json([
            'success' => true,
            'material' => $trainingMaterial
        ]);
    }

    public function update(Request $request, TrainingMaterial $trainingMaterial): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'file_category_type' => 'sometimes|in:handout,document,manual',
            'order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean'
        ]);

        $trainingMaterial->update($validated);

        // Load the uploadedBy relationship for the response
        $trainingMaterial->load('uploadedBy');

        return response()->json([
            'success' => true,
            'message' => 'Training material updated successfully',
            'material' => $trainingMaterial
        ]);
    }

    public function destroy(TrainingMaterial $trainingMaterial): JsonResponse
    {
        // Delete the encrypted file from secure storage
        if ($this->secureFileService->secureFileExists($trainingMaterial->file_path)) {
            $this->secureFileService->deleteSecureFile($trainingMaterial->file_path);
        }

        $trainingMaterial->delete();

        return response()->json([
            'success' => true,
            'message' => 'Training material deleted successfully'
        ]);
    }

    public function getByCourse($courseId): JsonResponse
    {
        $materials = TrainingMaterial::where('course_id', $courseId)
            ->active()
            ->with('uploadedBy')
            ->ordered()
            ->get()
            ->groupBy('file_category_type');

        return response()->json([
            'success' => true,
            'course_id' => $courseId,
            'materials' => $materials->flatten(),
            'handouts' => $materials->get('handout', []),
            'documents' => $materials->get('document', []),
            'manuals' => $materials->get('manual', [])
        ]);
    }

    public function download(TrainingMaterial $trainingMaterial)
    {
        // Log file access for security monitoring
        Log::info('Secure file access attempt', [
            'user_id' => Auth::id(),
            'file_id' => $trainingMaterial->id,
            'file_name' => $trainingMaterial->file_name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'action' => 'download'
        ]);

        if (!$this->secureFileService->secureFileExists($trainingMaterial->file_path)) {
            Log::warning('Secure file not found for download', [
                'file_id' => $trainingMaterial->id,
                'file_path' => $trainingMaterial->file_path
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        $decryptedContent = $this->secureFileService->getSecureFile($trainingMaterial->file_path);

        if ($decryptedContent === null) {
            Log::error('Failed to decrypt secure file for download', [
                'file_id' => $trainingMaterial->id,
                'file_path' => $trainingMaterial->file_path,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to access file'
            ], 500);
        }

        Log::info('Secure file downloaded successfully', [
            'user_id' => Auth::id(),
            'file_id' => $trainingMaterial->id,
            'file_name' => $trainingMaterial->file_name
        ]);

        return response($decryptedContent, 200, [
            'Content-Type' => $trainingMaterial->file_type,
            'Content-Disposition' => 'attachment; filename="' . $trainingMaterial->file_name . '"',
            'Content-Length' => strlen($decryptedContent)
        ]);
    }

    public function view(TrainingMaterial $trainingMaterial)
    {
        // Log file access for security monitoring
        Log::info('Secure file access attempt', [
            'user_id' => Auth::id(),
            'file_id' => $trainingMaterial->id,
            'file_name' => $trainingMaterial->file_name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'action' => 'view'
        ]);

        if (!$this->secureFileService->secureFileExists($trainingMaterial->file_path)) {
            Log::warning('Secure file not found', [
                'file_id' => $trainingMaterial->id,
                'file_path' => $trainingMaterial->file_path
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        $decryptedContent = $this->secureFileService->getSecureFile($trainingMaterial->file_path);

        if ($decryptedContent === null) {
            Log::error('Failed to decrypt secure file', [
                'file_id' => $trainingMaterial->id,
                'file_path' => $trainingMaterial->file_path,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to access file'
            ], 500);
        }

        Log::info('Secure file accessed successfully', [
            'user_id' => Auth::id(),
            'file_id' => $trainingMaterial->id,
            'file_name' => $trainingMaterial->file_name
        ]);

        // For PDFs, set content-disposition to inline so they open in browser
        return response($decryptedContent, 200, [
            'Content-Type' => $trainingMaterial->file_type,
            'Content-Disposition' => 'inline; filename="' . $trainingMaterial->file_name . '"',
            'Content-Length' => strlen($decryptedContent)
        ]);
    }

    /**
     * Get all training materials across all courses for document management
     * Supports search, filtering, sorting, and pagination
     */
    public function getAllDocuments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'file_category_type' => 'nullable|in:handout,document,manual',
            'course_id' => 'nullable|integer|exists:main_db.tblcourses,courseid',
            'is_active' => 'nullable|boolean',
            'sort_by' => 'nullable|in:created_at,title,file_size,views',
            'sort_order' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $query = TrainingMaterial::with(['uploadedBy', 'course']);

        // Apply search filter
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhereHas('course', function ($courseQuery) use ($search) {
                        $courseQuery->where('coursetitle', 'like', "%{$search}%");
                    });
            });
        }

        // Apply category filter
        if (!empty($validated['file_category_type'])) {
            $query->byCategory($validated['file_category_type']);
        }

        // Apply course filter
        if (!empty($validated['course_id'])) {
            $query->where('course_id', $validated['course_id']);
        }

        // Apply active status filter
        if (isset($validated['is_active'])) {
            $query->where('is_active', $validated['is_active']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        // For views, we'll sort by id for now (you can add a views column later)
        if ($sortBy === 'views') {
            $sortBy = 'id';
        }

        $query->orderBy($sortBy, $sortOrder);

        // Get stats before pagination
        $stats = $this->calculateDocumentStats();

        // Paginate results
        $perPage = $validated['per_page'] ?? 12;
        $materials = $query->paginate($perPage);

        // Transform the data to include course information
        $transformedData = $materials->map(function ($material) {
            return [
                'id' => $material->id,
                'course_id' => $material->course_id,
                'title' => $material->title,
                'description' => $material->description,
                'file_name' => $material->file_name,
                'file_path' => $material->file_path,
                'file_type' => $material->file_type,
                'file_category_type' => $material->file_category_type,
                'file_size' => $material->file_size,
                'file_size_human' => $material->file_size_human,
                'order' => $material->order,
                'is_active' => $material->is_active,
                'created_at' => $material->created_at,
                'updated_at' => $material->updated_at,
                'uploaded_by' => $material->uploadedBy ? [
                    'id' => $material->uploadedBy->id,
                    'name' => $material->uploadedBy->fullname,
                    'email' => $material->uploadedBy->email,
                ] : null,
                'course_name' => $material->course ? $material->course->coursename : null,
                'course_code' => $material->course ? $material->course->coursecode ?? null : null,
                'views' => 0, // Placeholder - implement view tracking if needed
                'downloads' => 0, // Placeholder - implement download tracking if needed
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedData,
            'stats' => $stats,
            'pagination' => [
                'current_page' => $materials->currentPage(),
                'per_page' => $materials->perPage(),
                'total' => $materials->total(),
                'total_pages' => $materials->lastPage(),
            ]
        ]);
    }

    /**
     * Calculate statistics for document management dashboard
     */
    private function calculateDocumentStats(): array
    {
        $totalDocuments = TrainingMaterial::count();
        $totalHandouts = TrainingMaterial::byCategory('handout')->count();
        $totalManuals = TrainingMaterial::byCategory('manual')->count();
        $totalSize = TrainingMaterial::sum('file_size');

        // Calculate human-readable total size
        $bytes = $totalSize;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        for (; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        $totalSizeHuman = round($bytes, 2) . ' ' . $units[$i];

        // Recent uploads (last 7 days)
        $recentUploads = TrainingMaterial::where('created_at', '>=', now()->subDays(7))->count();

        // Active courses with materials
        $activeCourses = TrainingMaterial::distinct('course_id')->count('course_id');

        return [
            'total_documents' => $totalDocuments,
            'total_handouts' => $totalHandouts,
            'total_manuals' => $totalManuals,
            'total_size' => $totalSize,
            'total_size_human' => $totalSizeHuman,
            'recent_uploads' => $recentUploads,
            'active_courses' => $activeCourses,
        ];
    }

    /**
     * Bulk delete training materials
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:training_materials,id'
        ]);

        $materials = TrainingMaterial::whereIn('id', $validated['ids'])->get();

        $deletedCount = 0;
        foreach ($materials as $material) {
            // Delete the encrypted file from secure storage
            if ($this->secureFileService->secureFileExists($material->file_path)) {
                $this->secureFileService->deleteSecureFile($material->file_path);
            }

            $material->delete();
            $deletedCount++;
        }

        Log::info('Bulk delete training materials', [
            'user_id' => Auth::id(),
            'deleted_count' => $deletedCount,
            'material_ids' => $validated['ids']
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} document(s) deleted successfully",
            'deleted_count' => $deletedCount
        ]);
    }

    /**
     * Bulk update training materials status (activate/deactivate)
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:training_materials,id',
            'is_active' => 'required|boolean'
        ]);

        $updatedCount = TrainingMaterial::whereIn('id', $validated['ids'])
            ->update(['is_active' => $validated['is_active']]);

        $status = $validated['is_active'] ? 'activated' : 'deactivated';

        Log::info('Bulk update training materials status', [
            'user_id' => Auth::id(),
            'updated_count' => $updatedCount,
            'material_ids' => $validated['ids'],
            'status' => $status
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$updatedCount} document(s) {$status} successfully",
            'updated_count' => $updatedCount
        ]);
    }
}

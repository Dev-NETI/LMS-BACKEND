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
}

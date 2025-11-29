<?php

namespace App\Http\Controllers;

use App\Models\TrainingMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class TrainingMaterialController extends Controller
{
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
            'file' => 'required|file|max:50000', // 50MB max
            'file_category_type' => 'required|in:handout,document,manual',
            'order' => 'integer|min:0'
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('training-materials', $fileName, 'public');

        $material = TrainingMaterial::create([
            'course_id' => $validated['course_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_type' => $file->getClientMimeType(),
            'file_category_type' => $validated['file_category_type'],
            'file_size' => $file->getSize(),
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
        // Delete the file from storage
        if (Storage::disk('public')->exists($trainingMaterial->file_path)) {
            Storage::disk('public')->delete($trainingMaterial->file_path);
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
        $filePath = storage_path('app/public/' . $trainingMaterial->file_path);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        return response()->download($filePath, $trainingMaterial->file_name);
    }
}

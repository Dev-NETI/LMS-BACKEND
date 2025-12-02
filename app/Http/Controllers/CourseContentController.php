<?php

namespace App\Http\Controllers;

use App\Models\CourseContent;
use App\Services\SecureFileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class CourseContentController extends Controller
{
    protected SecureFileService $secureFileService;

    public function __construct(SecureFileService $secureFileService)
    {
        $this->secureFileService = $secureFileService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = CourseContent::active()->with('uploadedBy');

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('content_type')) {
            $query->byType($request->content_type);
        }

        if ($request->has('file_type')) {
            $query->byFileType($request->file_type);
        }

        $contents = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $contents
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|integer|exists:main_db.tblcourses,courseid',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'content_type' => 'required|in:file,url',
            'order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        if ($validated['content_type'] === 'url') {
            return $this->storeUrlContent($request, $validated);
        } else {
            return $this->storeFileContent($request, $validated);
        }
    }

    private function storeUrlContent(Request $request, array $validated): JsonResponse
    {
        $urlValidator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048'
        ]);

        if ($urlValidator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'URL validation failed',
                'errors' => $urlValidator->errors()
            ], 422);
        }

        $content = CourseContent::create([
            'course_id' => $validated['course_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content_type' => 'url',
            'file_type' => 'link',
            'url' => $request->url,
            'order' => $validated['order'] ?? 0,
            'uploaded_by_user_id' => Auth::id(),
        ]);

        $content->load('uploadedBy');

        return response()->json([
            'success' => true,
            'message' => 'Course content URL added successfully',
            'content' => $content
        ], 201);
    }

    private function storeFileContent(Request $request, array $validated): JsonResponse
    {
        $fileValidator = Validator::make($request->all(), [
            'file' => 'required|file|max:100000',
            'file_type' => 'required|in:articulate_html,pdf'
        ]);

        if ($fileValidator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'File validation failed',
                'errors' => $fileValidator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $fileType = $request->input('file_type');

        if ($fileType === 'articulate_html') {
            if (!$this->isValidArticulateHtmlFile($file)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Articulate HTML file. Please upload a ZIP file containing Articulate content.'
                ], 422);
            }
        } elseif ($fileType === 'pdf') {
            if ($file->getClientMimeType() !== 'application/pdf') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid PDF file.'
                ], 422);
            }
        }

        $secureFileData = $this->secureFileService->storeSecureFile($file, 'secure-course-content');

        $content = CourseContent::create([
            'course_id' => $validated['course_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content_type' => 'file',
            'file_type' => $fileType,
            'file_name' => $secureFileData['original_name'],
            'file_path' => $secureFileData['encrypted_path'],
            'mime_type' => $secureFileData['mime_type'],
            'file_size' => $secureFileData['size'],
            'order' => $validated['order'] ?? 0,
            'uploaded_by_user_id' => Auth::id(),
        ]);

        $content->load('uploadedBy');

        return response()->json([
            'success' => true,
            'message' => 'Course content file uploaded successfully',
            'content' => $content
        ], 201);
    }

    private function isValidArticulateHtmlFile($file): bool
    {
        $mimeType = $file->getClientMimeType();
        return in_array($mimeType, ['application/zip', 'application/x-zip-compressed']);
    }

    public function show(CourseContent $courseContent): JsonResponse
    {
        $courseContent->load('uploadedBy');

        return response()->json([
            'success' => true,
            'content' => $courseContent
        ]);
    }

    public function update(Request $request, CourseContent $courseContent): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($courseContent->content_type === 'url') {
            $urlValidator = Validator::make($request->all(), [
                'url' => 'sometimes|required|url|max:2048'
            ]);

            if ($urlValidator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'URL validation failed',
                    'errors' => $urlValidator->errors()
                ], 422);
            }
        }

        $courseContent->update($validator->validated());

        if ($request->has('url') && $courseContent->content_type === 'url') {
            $courseContent->update(['url' => $request->url]);
        }

        $courseContent->load('uploadedBy');

        return response()->json([
            'success' => true,
            'message' => 'Course content updated successfully',
            'content' => $courseContent
        ]);
    }

    public function destroy(CourseContent $courseContent): JsonResponse
    {
        if ($courseContent->isFile() && $courseContent->file_path) {
            if ($this->secureFileService->secureFileExists($courseContent->file_path)) {
                $this->secureFileService->deleteSecureFile($courseContent->file_path);
            }
        }

        $courseContent->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course content deleted successfully'
        ]);
    }

    public function getByCourse($courseId): JsonResponse
    {
        $contents = CourseContent::where('course_id', $courseId)
            ->active()
            ->with('uploadedBy')
            ->ordered()
            ->get()
            ->groupBy('file_type');

        $articleContents = $contents->get('articulate_html', collect());
        $pdfContents = $contents->get('pdf', collect());
        $linkContents = $contents->get('link', collect());

        return response()->json([
            'success' => true,
            'course_id' => $courseId,
            'contents' => $contents->flatten(),
            'articulate_contents' => $articleContents,
            'pdf_contents' => $pdfContents,
            'link_contents' => $linkContents,
            'total_count' => $contents->flatten()->count()
        ]);
    }

    public function download(CourseContent $courseContent)
    {
        if (!$courseContent->isFile()) {
            return response()->json([
                'success' => false,
                'message' => 'Content is not a file'
            ], 400);
        }

        Log::info('Course content file access attempt', [
            'user_id' => Auth::id(),
            'content_id' => $courseContent->id,
            'file_name' => $courseContent->file_name,
            'ip_address' => request()->ip(),
            'action' => 'download'
        ]);

        if (!$this->secureFileService->secureFileExists($courseContent->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        $decryptedContent = $this->secureFileService->getSecureFile($courseContent->file_path);

        if ($decryptedContent === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to access file'
            ], 500);
        }

        return response($decryptedContent, 200, [
            'Content-Type' => $courseContent->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $courseContent->file_name . '"',
            'Content-Length' => strlen($decryptedContent)
        ]);
    }

    public function view(CourseContent $courseContent)
    {
        if (!$courseContent->isFile()) {
            return response()->json([
                'success' => false,
                'message' => 'Content is not a file'
            ], 400);
        }

        Log::info('Course content file access attempt', [
            'user_id' => Auth::id(),
            'content_id' => $courseContent->id,
            'file_name' => $courseContent->file_name,
            'ip_address' => request()->ip(),
            'action' => 'view'
        ]);

        if (!$this->secureFileService->secureFileExists($courseContent->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        $decryptedContent = $this->secureFileService->getSecureFile($courseContent->file_path);

        if ($decryptedContent === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to access file'
            ], 500);
        }

        return response($decryptedContent, 200, [
            'Content-Type' => $courseContent->mime_type,
            'Content-Disposition' => 'inline; filename="' . $courseContent->file_name . '"',
            'Content-Length' => strlen($decryptedContent)
        ]);
    }
}

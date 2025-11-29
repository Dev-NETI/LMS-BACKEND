<?php

namespace App\Http\Controllers;

use App\Models\CourseDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CourseDetailController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CourseDetail::with('course')->active();

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        $details = $query->ordered()->get();

        // Group by type for easier frontend handling
        $grouped = $details->groupBy('type');

        return response()->json([
            'success' => true,
            'data' => $grouped,
            'details' => $details
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|integer|exists:main_db.tblcourses,courseid',
            'type' => 'required|in:description,learning_objective,prerequisite',
            'content' => 'required|string|max:2000',
            'order' => 'integer|min:0'
        ]);

        $validated['order'] = $validated['order'] ?? 0;

        $detail = CourseDetail::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course detail created successfully',
            'detail' => $detail
        ], 201);
    }

    public function show(CourseDetail $courseDetail): JsonResponse
    {
        $courseDetail->load('course');

        return response()->json([
            'success' => true,
            'detail' => $courseDetail
        ]);
    }

    public function update(Request $request, CourseDetail $courseDetail): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'sometimes|required|string|max:2000',
            'order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean'
        ]);

        $courseDetail->update($validated);
        $courseDetail->load('course');

        return response()->json([
            'success' => true,
            'message' => 'Course detail updated successfully',
            'detail' => $courseDetail
        ]);
    }

    public function destroy(CourseDetail $courseDetail): JsonResponse
    {
        $courseDetail->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course detail deleted successfully'
        ]);
    }

    public function getByCourse($courseId): JsonResponse
    {
        $details = CourseDetail::where('course_id', $courseId)
            ->active()
            ->ordered()
            ->get()
            ->groupBy('type');

        return response()->json([
            'success' => true,
            'course_id' => $courseId,
            'descriptions' => $details->get('description', []),
            'learning_objectives' => $details->get('learning_objective', []),
            'prerequisites' => $details->get('prerequisite', [])
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:course_details,id',
            'items.*.order' => 'required|integer|min:0'
        ]);

        foreach ($validated['items'] as $item) {
            CourseDetail::where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Course details reordered successfully'
        ]);
    }
}

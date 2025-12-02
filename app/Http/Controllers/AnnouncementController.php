<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::with(['schedule', 'createdByUser']);

        if ($request->has('schedule_id')) {
            $query->forSchedule($request->schedule_id);
        }

        if ($request->boolean('active_only', true)) {
            $query->active()->published();
        }

        if ($request->boolean('with_replies', false)) {
            $query->withCount('replies');
        }

        $announcements = $query->orderBy('published_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($announcements);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer|exists:main_db.tblcourseschedule,scheduleid',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'boolean',
            'published_at' => 'nullable|date'
        ]);

        $validated['created_by_user_id'] = Auth::id();
        $validated['user_type'] = 'admin';  // Since this is admin controller
        $validated['published_at'] = $validated['published_at'] ?? now();

        $announcement = Announcement::create($validated);
        $announcement->load(['schedule']);

        // Load the correct user relationship based on user_type
        if ($announcement->user_type === 'admin') {
            $announcement->load('createdByUser');
        }
        return response()->json([
            'message' => 'Announcement created successfully',
            'announcement' => $announcement
        ], 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $announcement->load(['schedule', 'createdByUser', 'activeReplies']);
        $announcement->loadCount('replies');
        return response()->json($announcement);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'is_active' => 'sometimes|boolean',
            'published_at' => 'nullable|date'
        ]);

        $announcement->update($validated);
        $announcement->load(['schedule', 'createdByUser']);

        return response()->json([
            'message' => 'Announcement updated successfully',
            'announcement' => $announcement
        ]);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json([
            'message' => 'Announcement deleted successfully'
        ]);
    }

    public function getBySchedule(Request $request): JsonResponse
    {
        $schedule = Schedule::find($request->sched_id);
        if (!$schedule) {
            return response()->json(['message' => 'Schedule not found'], 404);
        }

        $announcements = Announcement::where('schedule_id', $request->sched_id)
            ->with(['createdByUser'])
            ->withCount('replies')
            ->orderBy('published_at', 'desc')
            ->get();

        return response()->json([
            'schedule' => $schedule,
            'announcements' => $announcements
        ]);
    }

    public function toggleActive(Announcement $announcement): JsonResponse
    {
        $announcement->update(['is_active' => !$announcement->is_active]);

        return response()->json([
            'message' => 'Announcement status updated successfully',
            'announcement' => $announcement
        ]);
    }
}

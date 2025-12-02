<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementReply;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnnouncementReplyController extends Controller
{
    public function index(Request $request, $announcementId): JsonResponse
    {
        $announcement = Announcement::findOrFail($announcementId);

        $query = AnnouncementReply::forAnnouncement($announcementId);

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $replies = $query->orderBy('created_at', 'asc')
            ->get()
            ->each(function ($reply) {
                // Load only the correct relationship based on user_type
                if ($reply->user_type === 'admin') {
                    $reply->load('user');
                } else {
                    $reply->load('traineeUser');
                }
            });

        $paginatedReplies = new \Illuminate\Pagination\LengthAwarePaginator(
            $replies,
            $query->count(),
            $request->get('per_page', 20),
            $request->get('page', 1)
        );

        return response()->json([
            'announcement' => $announcement,
            'replies' => $paginatedReplies
        ]);
    }

    public function store(Request $request, $announcementId): JsonResponse
    {
        $announcement = Announcement::findOrFail($announcementId);

        if (!$announcement->is_active) {
            return response()->json(['message' => 'Cannot reply to inactive announcement'], 422);
        }

        $validated = $request->validate([
            'content' => 'required|string|min:1|max:2000'
        ]);

        $reply = AnnouncementReply::create([
            'announcement_id' => $announcementId,
            'user_id' => auth()->id(),
            'user_type' => $request->user_type,  // Since this is admin controller  
            'content' => $validated['content']
        ]);

        // Load the correct relationship based on user_type
        if ($reply->user_type === 'admin') {
            $reply->load('user');
        } else {
            $reply->load('traineeUser');
        }

        return response()->json([
            'message' => 'Reply posted successfully',
            'reply' => $reply
        ], 201);
    }

    public function show(AnnouncementReply $reply): JsonResponse
    {
        $reply->load(['user', 'announcement']);
        return response()->json($reply);
    }

    public function update(Request $request, AnnouncementReply $reply): JsonResponse
    {
        if ($reply->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized to edit this reply'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|min:1|max:2000'
        ]);

        $reply->update($validated);
        
        // Load the correct relationship based on user_type
        if ($reply->user_type === 'admin') {
            $reply->load('user');
        } else {
            $reply->load('traineeUser');
        }

        return response()->json([
            'message' => 'Reply updated successfully',
            'reply' => $reply
        ]);
    }

    public function destroy(AnnouncementReply $reply): JsonResponse
    {
        if ($reply->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized to delete this reply'], 403);
        }

        $reply->delete();

        return response()->json([
            'message' => 'Reply deleted successfully'
        ]);
    }

    public function toggleActive(AnnouncementReply $reply): JsonResponse
    {
        $reply->update(['is_active' => !$reply->is_active]);

        return response()->json([
            'message' => 'Reply status updated successfully',
            'reply' => $reply
        ]);
    }
}

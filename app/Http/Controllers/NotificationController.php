<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Notification;
use App\Models\Announcement;
use App\Models\Schedule;
use App\Models\Enrolled;

class NotificationController extends Controller
{
    /**
     * Get notifications for trainee (only for enrolled courses).
     */
    public function getTraineeNotifications(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user || !$user->traineeid) {
                return response()->json([
                    'message' => 'Trainee not found',
                ], 404);
            }

            $traineeId = $user->traineeid;
            $limit = $request->get('limit', 10);
            $unreadOnly = $request->boolean('unread_only');

            // Get enrolled schedule IDs for this trainee first
            $enrolledScheduleIds = \DB::connection('main_db')
                ->table('tblenroled')
                ->where('traineeid', $traineeId)
                ->where('pendingid', 0) // Active enrollments only
                ->pluck('scheduleid');

            $query = Notification::where('user_id', $traineeId)
                ->where('user_type', 'trainee')
                ->whereHas('announcement', function ($q) use ($enrolledScheduleIds) {
                    $q->whereIn('schedule_id', $enrolledScheduleIds);
                })
                ->with(['announcement.schedule'])
                ->orderBy('created_at', 'desc');

            if ($unreadOnly) {
                $query->unread();
            }

            $notifications = $query->limit($limit)->get();
            $unreadCount = Notification::where('user_id', $traineeId)
                ->where('user_type', 'trainee')
                ->whereHas('announcement', function ($q) use ($enrolledScheduleIds) {
                    $q->whereIn('schedule_id', $enrolledScheduleIds);
                })
                ->unread()
                ->count();

            // Transform notifications for frontend
            $transformedNotifications = $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'user_id' => $notification->user_id,
                    'user_type' => $notification->user_type,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'related_id' => $notification->related_id,
                    'schedule_id' => $notification->schedule_id,
                    'is_read' => $notification->is_read,
                    'created_at' => $notification->created_at->toISOString(),
                    'updated_at' => $notification->updated_at->toISOString(),
                    'announcement' => $notification->announcement ? [
                        'id' => $notification->announcement->id,
                        'title' => $notification->announcement->title,
                        'schedule_id' => $notification->announcement->schedule_id,
                        'schedule' => $notification->announcement->schedule ? [
                            'scheduleid' => $notification->announcement->schedule->scheduleid,
                            'course_name' => $notification->announcement->schedule->course_name,
                        ] : null,
                    ] : null,
                ];
            });

            return response()->json([
                'notifications' => $transformedNotifications,
                'unread_count' => $unreadCount,
                'total' => $notifications->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching trainee notifications: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread notification count for trainee.
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user || !$user->traineeid) {
                return response()->json(['count' => 0]);
            }

            $traineeId = $user->traineeid;

            // Get enrolled schedule IDs for this trainee first
            $enrolledScheduleIds = \DB::connection('main_db')
                ->table('tblenroled')
                ->where('traineeid', $traineeId)
                ->where('pendingid', 0) // Active enrollments only
                ->pluck('scheduleid');

            $count = Notification::where('user_id', $traineeId)
                ->where('user_type', 'trainee')
                ->whereHas('announcement', function ($q) use ($enrolledScheduleIds) {
                    $q->whereIn('schedule_id', $enrolledScheduleIds);
                })
                ->unread()
                ->count();

            return response()->json(['count' => $count]);
        } catch (\Exception $e) {
            Log::error('Error fetching unread count: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch unread count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, int $notificationId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user || !$user->traineeid) {
                return response()->json(['message' => 'Trainee not found'], 404);
            }

            $traineeId = $user->traineeid;

            // Get enrolled schedule IDs for this trainee first
            $enrolledScheduleIds = \DB::connection('main_db')
                ->table('tblenroled')
                ->where('traineeid', $traineeId)
                ->where('pendingid', 0) // Active enrollments only
                ->pluck('scheduleid');

            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $traineeId)
                ->where('user_type', 'trainee')
                ->whereHas('announcement', function ($q) use ($enrolledScheduleIds) {
                    $q->whereIn('schedule_id', $enrolledScheduleIds);
                })
                ->first();

            if (!$notification) {
                return response()->json(['message' => 'Notification not found'], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'message' => 'Notification marked as read',
                'notification' => $notification
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user || !$user->traineeid) {
                return response()->json(['message' => 'Trainee not found'], 404);
            }

            $traineeId = $user->traineeid;

            // Get enrolled schedule IDs for this trainee first
            $enrolledScheduleIds = \DB::connection('main_db')
                ->table('tblenroled')
                ->where('traineeid', $traineeId)
                ->where('pendingid', 0) // Active enrollments only
                ->pluck('scheduleid');

            $updatedCount = Notification::where('user_id', $traineeId)
                ->where('user_type', 'trainee')
                ->whereHas('announcement', function ($q) use ($enrolledScheduleIds) {
                    $q->whereIn('schedule_id', $enrolledScheduleIds);
                })
                ->unread()
                ->update(['is_read' => true]);

            return response()->json([
                'message' => 'All notifications marked as read',
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification.
     */
    public function deleteNotification(Request $request, int $notificationId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user || !$user->traineeid) {
                return response()->json(['message' => 'Trainee not found'], 404);
            }

            $traineeId = $user->traineeid;

            // Get enrolled schedule IDs for this trainee first
            $enrolledScheduleIds = \DB::connection('main_db')
                ->table('tblenroled')
                ->where('traineeid', $traineeId)
                ->where('pendingid', 0) // Active enrollments only
                ->pluck('scheduleid');

            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $traineeId)
                ->where('user_type', 'trainee')
                ->whereHas('announcement', function ($q) use ($enrolledScheduleIds) {
                    $q->whereIn('schedule_id', $enrolledScheduleIds);
                })
                ->first();

            if (!$notification) {
                return response()->json(['message' => 'Notification not found'], 404);
            }

            $notification->delete();

            return response()->json(['message' => 'Notification deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting notification: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create notifications for announcement (Admin only).
     */
    public function createAnnouncementNotification(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'announcement_id' => 'required|integer|exists:announcements,id',
                'schedule_id' => 'required|integer|exists:main_db.tblcourseschedule,scheduleid',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $announcementId = $request->announcement_id;
            $scheduleId = $request->schedule_id;

            $notificationsCreated = Notification::createForAnnouncement($announcementId, $scheduleId);

            return response()->json([
                'message' => 'Notifications created successfully',
                'notifications_created' => $notificationsCreated
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating announcement notifications: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

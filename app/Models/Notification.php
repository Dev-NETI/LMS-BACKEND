<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'type',
        'title',
        'message',
        'related_id',
        'schedule_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the trainee that owns the notification.
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Trainee::class, 'user_id', 'traineeid');
    }

    /**
     * Get the related announcement if type is 'announcement'.
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'related_id')->with('schedule');
    }

    /**
     * Scope to get notifications for a specific user type.
     */
    public function scopeForUserType($query, string $userType)
    {
        return $query->where('user_type', $userType);
    }

    /**
     * Scope to get unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }


    /**
     * Mark notification as read.
     */
    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]);
    }

    /**
     * Create notification for announcement to all enrolled trainees.
     */
    public static function createForAnnouncement(int $announcementId, int $scheduleId): int
    {
        $announcement = Announcement::find($announcementId);
        if (!$announcement) {
            return 0;
        }

        // Get all enrolled trainees for this schedule using the correct database connection
        $enrolledTrainees = \DB::connection('main_db')
            ->table('tblenroled')
            ->where('scheduleid', $scheduleId)
            ->where('pendingid', 0) // 0 means active enrollment
            ->select('traineeid')
            ->get();

        $notificationsCreated = 0;

        foreach ($enrolledTrainees as $enrolled) {
            try {
                self::create([
                    'user_id' => $enrolled->traineeid,
                    'user_type' => 'trainee',
                    'type' => 'announcement',
                    'title' => 'New Announcement: ' . $announcement->title,
                    'message' => 'A new announcement has been posted: ' . substr($announcement->content, 0, 100) . '...',
                    'related_id' => $announcementId,
                    'schedule_id' => $scheduleId,
                    'is_read' => false,
                ]);
                $notificationsCreated++;
            } catch (\Exception $e) {
                \Log::error("Failed to create notification for trainee {$enrolled->traineeid}: " . $e->getMessage());
            }
        }

        return $notificationsCreated;
    }
}

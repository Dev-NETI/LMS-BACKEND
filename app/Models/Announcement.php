<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'schedule_id',
        'created_by_user_id',
        'user_type',
        'title',
        'content',
        'is_active',
        'published_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'scheduleid');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSchedule($query, $scheduleId)
    {
        return $query->where('schedule_id', $scheduleId);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function replies()
    {
        return $this->hasMany(AnnouncementReply::class);
    }

    public function activeReplies()
    {
        return $this->hasMany(AnnouncementReply::class)
            ->active()
            ->with('user')
            ->orderBy('created_at', 'asc');
    }

    public function getRepliesCountAttribute()
    {
        return $this->replies()->active()->count();
    }
}

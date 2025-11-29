<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementReply extends Model
{
    protected $fillable = [
        'announcement_id',
        'user_id',
        'user_type',
        'content',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function traineeUser()
    {
        return $this->belongsTo(Trainee::class, 'user_id', 'traineeid');
    }

    public function getUserAttribute()
    {
        if ($this->user_type === 'admin') {
            $user = $this->getRelationValue('user');
        } else {
            $user = $this->getRelationValue('traineeUser');
        }

        if ($user) {
            $userData = $user->toArray();
            $userData['user_type'] = $this->user_type;
            return $userData;
        }
        return null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAnnouncement($query, $announcementId)
    {
        return $query->where('announcement_id', $announcementId);
    }
}

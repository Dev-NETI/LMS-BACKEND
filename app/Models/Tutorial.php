<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tutorial extends Model
{
    protected $fillable = [
        'title',
        'description',
        'video_file_name',
        'video_file_path',
        'video_file_type',
        'video_file_size',
        'duration_seconds',
        'category',
        'total_views',
        'is_active',
        'uploaded_by_user_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'video_file_size' => 'integer',
        'duration_seconds' => 'integer',
        'total_views' => 'integer',
        'uploaded_by_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = ['video_file_size_human', 'duration_formatted'];

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function getVideoFileSizeHumanAttribute()
    {
        $bytes = $this->video_file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDurationFormattedAttribute()
    {
        if (!$this->duration_seconds) {
            return '0:00';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function incrementViews()
    {
        $this->increment('total_views');
    }
}

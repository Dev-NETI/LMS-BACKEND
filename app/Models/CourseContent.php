<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseContent extends Model
{
    protected $table = 'course_content';

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'content_type',
        'file_type',
        'file_name',
        'file_path',
        'url',
        'mime_type',
        'file_size',
        'order',
        'is_active',
        'uploaded_by_user_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'uploaded_by_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = ['file_size_human'];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'courseid');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('content_type', $type);
    }

    public function scopeByFileType($query, $fileType)
    {
        return $query->where('file_type', $fileType);
    }

    public function getFileSizeHumanAttribute()
    {
        if (!$this->file_size) {
            return null;
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getUploadedByNameAttribute()
    {
        return $this->uploadedBy ? $this->uploadedBy->name : null;
    }

    public function isFile()
    {
        return $this->content_type === 'file';
    }

    public function isUrl()
    {
        return $this->content_type === 'url';
    }

    public function isArticulateHtml()
    {
        return $this->file_type === 'articulate_html';
    }

    public function isPdf()
    {
        return $this->file_type === 'pdf';
    }

    public function isLink()
    {
        return $this->file_type === 'link';
    }
}

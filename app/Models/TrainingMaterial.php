<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingMaterial extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'file_name',
        'file_path',
        'file_type',
        'file_category_type',
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

    protected $appends = ['uploaded_by_name'];

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

    public function scopeByCategory($query, $category)
    {
        return $query->where('file_category_type', $category);
    }

    public function getFileSizeHumanAttribute()
    {
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
}

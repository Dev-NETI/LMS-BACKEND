<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $connection = 'main_db';
    protected $table = 'tblcourseschedule';
    protected $primaryKey = 'scheduleid';
    public $timestamps = false;

    protected $fillable = [
        'batchno',
        'startdateformat',
        'enddateformat'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'courseid', 'courseid');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrolled::class, 'scheduleid', 'scheduleid');
    }

    public function activeEnrollments()
    {
        return $this->hasMany(Enrolled::class, 'scheduleid', 'scheduleid')
            ->where('pendingid', 0)->where('deletedid', 0);
    }

    public function getEnrolledCountAttribute()
    {
        return $this->enrollments()->count();
    }

    public function getActiveEnrolledCountAttribute()
    {
        return $this->activeEnrollments()->count();
    }

    public function countEnrolledStudents()
    {
        return $this->enrollments()->count();
    }

    public function countActiveEnrolledStudents()
    {
        return $this->activeEnrollments()->count();
    }

    public function getEnrolledStudents()
    {
        return $this->enrollments()->with('trainee')->get();
    }

    public function getActiveEnrolledStudents()
    {
        return $this->activeEnrollments()->with('trainee')->get();
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'schedule_id', 'scheduleid');
    }

    public function activeAnnouncements()
    {
        return $this->hasMany(Announcement::class, 'schedule_id', 'scheduleid')
            ->active()
            ->published()
            ->orderBy('published_at', 'desc');
    }
}

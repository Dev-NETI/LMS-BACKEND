<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TraineeProgress extends Model
{
    protected $table = 'trainee_progress';

    protected $fillable = [
        'trainee_id',
        'course_id',
        'course_content_id',
        'status',
        'time_spent',
        'completion_percentage',
        'started_at',
        'completed_at',
        'last_activity',
        'activity_log',
        'notes'
    ];

    protected $casts = [
        'time_spent' => 'integer',
        'completion_percentage' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_activity' => 'datetime',
        'activity_log' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = ['time_spent_human', 'duration_since_start'];

    public function trainee()
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'courseid');
    }

    public function courseContent()
    {
        return $this->belongsTo(CourseContent::class, 'course_content_id');
    }

    // Scopes
    public function scopeByTrainee($query, $traineeId)
    {
        return $query->where('trainee_id', $traineeId);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeNotStarted($query)
    {
        return $query->where('status', 'not_started');
    }

    // Accessors
    public function getTimeSpentHumanAttribute()
    {
        $minutes = $this->time_spent;
        
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($hours < 24) {
            return $remainingMinutes > 0 ? 
                $hours . 'h ' . $remainingMinutes . 'm' : 
                $hours . 'h';
        }
        
        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        
        return $remainingHours > 0 ? 
            $days . 'd ' . $remainingHours . 'h' : 
            $days . 'd';
    }

    public function getDurationSinceStartAttribute()
    {
        if (!$this->started_at) {
            return null;
        }
        
        return Carbon::parse($this->started_at)->diffForHumans(null, true);
    }

    // Helper methods
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isNotStarted()
    {
        return $this->status === 'not_started';
    }

    public function markAsStarted()
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'last_activity' => now()
        ]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completion_percentage' => 100.00,
            'completed_at' => now(),
            'last_activity' => now()
        ]);
    }

    public function updateProgress($percentage, $timeSpent = null)
    {
        $data = [
            'completion_percentage' => $percentage,
            'last_activity' => now()
        ];

        if ($timeSpent !== null) {
            $data['time_spent'] = $this->time_spent + $timeSpent;
        }

        if ($percentage >= 100) {
            $data['status'] = 'completed';
            $data['completed_at'] = now();
        } elseif ($this->status === 'not_started') {
            $data['status'] = 'in_progress';
            $data['started_at'] = now();
        }

        $this->update($data);
    }

    public function logActivity($activity, $metadata = [])
    {
        $log = $this->activity_log ?? [];
        $log[] = [
            'timestamp' => now()->toISOString(),
            'activity' => $activity,
            'metadata' => $metadata
        ];

        $this->update([
            'activity_log' => $log,
            'last_activity' => now()
        ]);
    }

    // Static methods for progress calculation
    public static function getCourseProgress($traineeId, $courseId)
    {
        $totalContent = CourseContent::where('course_id', $courseId)->where('is_active', true)->count();
        
        if ($totalContent === 0) {
            return [
                'total_modules' => 0,
                'completed_modules' => 0,
                'in_progress_modules' => 0,
                'remaining_modules' => 0,
                'overall_completion_percentage' => 0,
                'total_time_spent' => 0
            ];
        }

        $progress = self::byTrainee($traineeId)->byCourse($courseId)->get();
        
        $completedModules = $progress->where('status', 'completed')->count();
        $inProgressModules = $progress->where('status', 'in_progress')->count();
        $totalTimeSpent = $progress->sum('time_spent');
        
        return [
            'total_modules' => $totalContent,
            'completed_modules' => $completedModules,
            'in_progress_modules' => $inProgressModules,
            'remaining_modules' => $totalContent - $completedModules - $inProgressModules,
            'overall_completion_percentage' => round(($completedModules / $totalContent) * 100, 2),
            'total_time_spent' => $totalTimeSpent
        ];
    }
}

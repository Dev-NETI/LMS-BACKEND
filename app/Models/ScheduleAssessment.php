<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'assessment_id',
        'is_active',
        'available_from',
        'available_until',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the schedule that owns the schedule assessment.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'scheduleid');
    }

    /**
     * Get the assessment that owns the schedule assessment.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Scope for active assignments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for assignments available within date range
     */
    public function scopeAvailable($query)
    {
        $now = now();
        return $query->where(function ($q) use ($now) {
            $q->where(function ($subQ) use ($now) {
                $subQ->whereNull('available_from')
                     ->orWhere('available_from', '<=', $now);
            })
            ->where(function ($subQ) use ($now) {
                $subQ->whereNull('available_until')
                     ->orWhere('available_until', '>=', $now);
            });
        });
    }

    /**
     * Scope for specific schedule
     */
    public function scopeForSchedule($query, $scheduleId)
    {
        return $query->where('schedule_id', $scheduleId);
    }

    /**
     * Check if assessment is available for schedule
     */
    public static function isAssessmentAvailableForSchedule($assessmentId, $scheduleId)
    {
        return self::where('assessment_id', $assessmentId)
                   ->where('schedule_id', $scheduleId)
                   ->active()
                   ->available()
                   ->exists();
    }

    /**
     * Assign assessment to schedule
     */
    public static function assignAssessmentToSchedule($assessmentId, $scheduleId, $availableFrom = null, $availableUntil = null)
    {
        return self::updateOrCreate(
            [
                'assessment_id' => $assessmentId,
                'schedule_id' => $scheduleId,
            ],
            [
                'is_active' => true,
                'available_from' => $availableFrom,
                'available_until' => $availableUntil,
            ]
        );
    }

    /**
     * Remove assessment from schedule
     */
    public static function removeAssessmentFromSchedule($assessmentId, $scheduleId)
    {
        return self::where('assessment_id', $assessmentId)
                   ->where('schedule_id', $scheduleId)
                   ->delete();
    }
}
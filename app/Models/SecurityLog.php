<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainee_id',
        'assessment_id',
        'attempt_id',
        'activity',
        'event_type',
        'severity',
        'ip_address',
        'user_agent',
        'additional_data',
        'event_timestamp',
    ];

    protected $casts = [
        'additional_data' => 'array',
        'event_timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Event type constants
    const EVENT_TYPES = [
        'TAB_SWITCH' => 'tab_switch',
        'RIGHT_CLICK_BLOCKED' => 'right_click_blocked',
        'SHORTCUT_BLOCKED' => 'shortcut_blocked',
        'FULLSCREEN_DENIED' => 'fullscreen_denied',
        'WINDOW_FOCUS_LOST' => 'window_focus_lost',
        'ASSESSMENT_STARTED' => 'assessment_started',
        'ASSESSMENT_COMPLETED' => 'assessment_completed',
        'SUSPICIOUS_ACTIVITY' => 'suspicious_activity',
        'COPY_ATTEMPT' => 'copy_attempt',
        'PASTE_ATTEMPT' => 'paste_attempt',
        'PRINT_ATTEMPT' => 'print_attempt',
        'DEVELOPER_TOOLS' => 'developer_tools',
        'MULTIPLE_TABS' => 'multiple_tabs',
        'SCREEN_RECORDING' => 'screen_recording',
    ];

    // Severity levels
    const SEVERITY_LEVELS = [
        'LOW' => 'low',
        'MEDIUM' => 'medium',
        'HIGH' => 'high',
        'CRITICAL' => 'critical',
    ];

    /**
     * Get the trainee that owns the security log.
     */
    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Trainee::class);
    }

    /**
     * Get the assessment that owns the security log.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Get the assessment attempt that owns the security log.
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'attempt_id');
    }

    /**
     * Scope for suspicious activities
     */
    public function scopeSuspicious($query)
    {
        return $query->whereIn('event_type', [
            self::EVENT_TYPES['TAB_SWITCH'],
            self::EVENT_TYPES['RIGHT_CLICK_BLOCKED'],
            self::EVENT_TYPES['SHORTCUT_BLOCKED'],
            self::EVENT_TYPES['FULLSCREEN_DENIED'],
            self::EVENT_TYPES['WINDOW_FOCUS_LOST'],
            self::EVENT_TYPES['SUSPICIOUS_ACTIVITY'],
            self::EVENT_TYPES['COPY_ATTEMPT'],
            self::EVENT_TYPES['PASTE_ATTEMPT'],
            self::EVENT_TYPES['DEVELOPER_TOOLS'],
            self::EVENT_TYPES['MULTIPLE_TABS'],
        ]);
    }

    /**
     * Scope for high severity events
     */
    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    /**
     * Scope for a specific assessment
     */
    public function scopeForAssessment($query, $assessmentId)
    {
        return $query->where('assessment_id', $assessmentId);
    }

    /**
     * Scope for a specific trainee
     */
    public function scopeForTrainee($query, $traineeId)
    {
        return $query->where('trainee_id', $traineeId);
    }

    /**
     * Scope for events within a date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('event_timestamp', [$startDate, $endDate]);
    }

    /**
     * Get formatted activity description
     */
    public function getFormattedActivityAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->event_type));
    }

    /**
     * Determine severity level based on event type
     */
    public static function determineSeverity($eventType, $additionalContext = [])
    {
        $highSeverityEvents = [
            self::EVENT_TYPES['SHORTCUT_BLOCKED'],
            self::EVENT_TYPES['RIGHT_CLICK_BLOCKED'],
            self::EVENT_TYPES['DEVELOPER_TOOLS'],
            self::EVENT_TYPES['MULTIPLE_TABS'],
        ];

        $mediumSeverityEvents = [
            self::EVENT_TYPES['TAB_SWITCH'],
            self::EVENT_TYPES['WINDOW_FOCUS_LOST'],
            self::EVENT_TYPES['FULLSCREEN_DENIED'],
            self::EVENT_TYPES['COPY_ATTEMPT'],
            self::EVENT_TYPES['PASTE_ATTEMPT'],
        ];

        if (in_array($eventType, $highSeverityEvents)) {
            return self::SEVERITY_LEVELS['HIGH'];
        } elseif (in_array($eventType, $mediumSeverityEvents)) {
            return self::SEVERITY_LEVELS['MEDIUM'];
        }

        return self::SEVERITY_LEVELS['LOW'];
    }

    /**
     * Create a security log entry
     */
    public static function logEvent($traineeId, $assessmentId, $eventType, $activity, $additionalData = [], $attemptId = null)
    {
        return self::create([
            'trainee_id' => $traineeId,
            'assessment_id' => $assessmentId,
            'attempt_id' => $attemptId,
            'activity' => $activity,
            'event_type' => $eventType,
            'severity' => self::determineSeverity($eventType, $additionalData),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'additional_data' => $additionalData,
            'event_timestamp' => now(),
        ]);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentAttempt extends Model
{
    protected $table = 'assessment_attempts';

    protected $fillable = [
        'assessment_id',
        'trainee_id',
        'attempt_number',
        'started_at',
        'submitted_at',
        'time_remaining',
        'score',
        'percentage',
        'status',
        'is_passed'
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'time_remaining' => 'integer',
        'score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'is_passed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Trainee::class, 'trainee_id', 'traineeid');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class, 'attempt_id');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopePassed($query)
    {
        return $query->where('is_passed', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('is_passed', false);
    }

    /**
     * Check if attempt has expired
     */
    public function isExpired(): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        // Check based on stored time_remaining if available
        if ($this->time_remaining !== null) {
            return $this->time_remaining <= 0;
        }

        // Fallback: check based on elapsed time
        $timeLimit = $this->assessment->time_limit * 60; // Convert to seconds
        $elapsed = now()->diffInSeconds($this->started_at);

        return $elapsed >= $timeLimit;
    }

    /**
     * Get remaining time in seconds based on stored time_remaining
     * If time_remaining is null, calculate from session time
     */
    public function getRemainingTime(): int
    {
        if ($this->status !== 'in_progress') {
            return 0;
        }

        // If time_remaining is stored in database, use it
        if ($this->time_remaining !== null) {
            return max(0, $this->time_remaining);
        }

        // Fallback: calculate from elapsed session time
        $timeLimit = $this->assessment->time_limit * 60; // Convert to seconds
        $elapsed = now()->diffInSeconds($this->started_at);

        return max(0, $timeLimit - $elapsed);
    }

    /**
     * Update time remaining in database (used during assessment progress)
     * This method decrements the stored time_remaining rather than recalculating from start time
     * to properly handle page refreshes and pauses
     */
    public function updateTimeRemaining(): void
    {
        if ($this->status !== 'in_progress') {
            return;
        }

        // If time_remaining is null, initialize it from time limit
        if ($this->time_remaining === null) {
            $timeLimit = $this->assessment->time_limit * 60; // Convert to seconds
            $sessionTimeSpent = now()->diffInSeconds($this->started_at);
            $timeRemaining = max(0, $timeLimit - $sessionTimeSpent);
        } else {
            // For ongoing assessments, we keep the stored time_remaining as-is
            // The frontend timer handles the countdown and syncs back to server
            $timeRemaining = max(0, $this->time_remaining);
        }

        $this->update([
            'time_remaining' => $timeRemaining
        ]);
    }

    /**
     * Mark attempt as expired
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => 'expired',
            'submitted_at' => now(),
            'time_remaining' => 0
        ]);
    }

    /**
     * Submit the attempt
     */
    public function submit(): void
    {
        $scoreData = $this->assessment->calculateScore($this);

        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'score' => $scoreData['earned_points'],
            'percentage' => $scoreData['percentage'],
            'is_passed' => $scoreData['is_passed'],
            'time_remaining' => $this->getRemainingTime()
        ]);
    }

    /**
     * Get answer for a specific question
     */
    public function getAnswerForQuestion($questionId)
    {
        return $this->answers()->where('question_id', $questionId)->first();
    }
}

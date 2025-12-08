<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Assessment extends Model
{
    protected $table = 'assessments';

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'instructions',
        'time_limit',
        'max_attempts',
        'passing_score',
        'is_active',
        'is_randomized',
        'show_results_immediately',
        'created_by_user_id'
    ];

    protected $casts = [
        'time_limit' => 'integer',
        'max_attempts' => 'integer',
        'passing_score' => 'decimal:2',
        'is_active' => 'boolean',
        'is_randomized' => 'boolean',
        'show_results_immediately' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = ['questions_count', 'total_points'];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'courseid');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'assessment_questions')
                    ->withPivot('order')
                    ->orderByPivot('order');
    }

    public function assessmentQuestions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class)->orderBy('created_at', 'desc');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function getQuestionsCountAttribute()
    {
        return $this->questions()->count();
    }

    public function getTotalPointsAttribute()
    {
        return $this->questions()->sum('points');
    }

    /**
     * Get questions for assessment attempt (with randomization if enabled)
     */
    public function getQuestionsForAttempt(): \Illuminate\Support\Collection
    {
        $questions = $this->assessmentQuestions()->with('question.options')->get();
        
        if ($this->is_randomized) {
            return $questions->shuffle();
        }
        
        return $questions;
    }

    /**
     * Check if a trainee can attempt this assessment
     */
    public function canAttempt($traineeId): bool
    {
        $attemptCount = $this->attempts()
            ->where('trainee_id', $traineeId)
            ->where('status', '!=', 'expired')
            ->count();
            
        return $attemptCount < $this->max_attempts;
    }

    /**
     * Get trainee's attempts for this assessment
     */
    public function getTraineeAttempts($traineeId)
    {
        return $this->attempts()
            ->where('trainee_id', $traineeId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if trainee has an active (in-progress) attempt
     */
    public function hasActiveAttempt($traineeId): bool
    {
        return $this->attempts()
            ->where('trainee_id', $traineeId)
            ->where('status', 'in_progress')
            ->exists();
    }

    /**
     * Get active attempt for trainee
     */
    public function getActiveAttempt($traineeId)
    {
        return $this->attempts()
            ->where('trainee_id', $traineeId)
            ->where('status', 'in_progress')
            ->first();
    }

    /**
     * Calculate score for an attempt
     */
    public function calculateScore(AssessmentAttempt $attempt): array
    {
        $totalPoints = 0;
        $earnedPoints = 0;
        $correctAnswers = 0;
        $totalQuestions = 0;

        foreach ($this->assessmentQuestions as $assessmentQuestion) {
            $question = $assessmentQuestion->question;
            $totalPoints += $question->points;
            $totalQuestions++;

            $answer = $attempt->answers()
                ->where('question_id', $question->id)
                ->first();

            if ($answer) {
                $answerData = $question->isIdentification() 
                    ? $answer->answer_data 
                    : (is_array($answer->answer_data) ? $answer->answer_data : [$answer->answer_data]);
                
                if ($question->checkAnswer($answerData)) {
                    $earnedPoints += $question->points;
                    $correctAnswers++;
                    
                    // Update answer as correct
                    $answer->update([
                        'is_correct' => true,
                        'points_earned' => $question->points
                    ]);
                } else {
                    $answer->update([
                        'is_correct' => false,
                        'points_earned' => 0
                    ]);
                }
            }
        }

        $percentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
        $isPassed = $percentage >= $this->passing_score;

        return [
            'total_points' => $totalPoints,
            'earned_points' => $earnedPoints,
            'percentage' => $percentage,
            'is_passed' => $isPassed,
            'correct_answers' => $correctAnswers,
            'total_questions' => $totalQuestions
        ];
    }
}
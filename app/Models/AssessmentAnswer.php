<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswer extends Model
{
    protected $table = 'assessment_answers';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'answer_data',
        'is_correct',
        'points_earned'
    ];

    protected $casts = [
        'answer_data' => 'json',
        'is_correct' => 'boolean',
        'points_earned' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
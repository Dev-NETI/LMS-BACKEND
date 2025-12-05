<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $table = 'questions';

    protected $fillable = [
        'course_id',
        'question_text',
        'question_type',
        'points',
        'explanation',
        'difficulty',
        'correct_answer',
        'is_active',
        'order',
        'created_by_user_id'
    ];

    protected $casts = [
        'points' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = ['options_count'];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'courseid');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
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
        return $query->where('question_type', $type);
    }

    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('question_text', 'LIKE', '%' . $search . '%');
    }

    public function getOptionsCountAttribute()
    {
        return $this->options()->count();
    }

    public function isMultipleChoice()
    {
        return $this->question_type === 'multiple_choice';
    }

    public function isCheckbox()
    {
        return $this->question_type === 'checkbox';
    }

    public function isIdentification()
    {
        return $this->question_type === 'identification';
    }

    public function hasOptions()
    {
        return in_array($this->question_type, ['multiple_choice', 'checkbox']);
    }

    /**
     * Get the correct answer(s) for this question
     * For multiple choice/checkbox: returns array of correct option IDs
     * For identification: returns the correct_answer string
     */
    public function getCorrectAnswer()
    {
        if ($this->isIdentification()) {
            return $this->correct_answer;
        }

        if ($this->hasOptions()) {
            return $this->options()->where('is_correct', true)->pluck('id')->toArray();
        }

        return null;
    }

    /**
     * Check if a given answer is correct
     * For multiple choice/checkbox: $answer should be array of option IDs
     * For identification: $answer should be string
     */
    public function checkAnswer($answer): bool
    {
        if ($this->isIdentification()) {
            return trim(strtolower($answer)) === trim(strtolower($this->correct_answer));
        }

        if ($this->hasOptions()) {
            $correctOptionIds = $this->getCorrectAnswer();
            
            if ($this->isMultipleChoice()) {
                return count($answer) === 1 && in_array($answer[0], $correctOptionIds);
            }
            
            if ($this->isCheckbox()) {
                sort($answer);
                sort($correctOptionIds);
                return $answer === $correctOptionIds;
            }
        }

        return false;
    }
}
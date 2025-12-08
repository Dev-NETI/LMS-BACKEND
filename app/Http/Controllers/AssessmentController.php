<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAnswer;
use App\Models\Course;
use App\Models\Enrolled;
use App\Models\Enrollment;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssessmentController extends Controller
{
    /**
     * Get assessment statistics for trainee
     */
    public function getAssessmentStats()
    {
        $traineeId = Auth::id();

        // Get enrolled courses
        $enrolledCourses = Enrolled::where('traineeid', $traineeId)->pluck('courseid');

        if ($enrolledCourses->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_assessments' => 0,
                    'completed_assessments' => 0,
                    'passed_assessments' => 0,
                    'average_score' => 0,
                    'pending_assessments' => 0
                ]
            ]);
        }

        // Get all assessments for enrolled courses
        $totalAssessments = Assessment::whereIn('course_id', $enrolledCourses)
            ->where('is_active', true)
            ->count();

        // Get completed attempts
        $completedAttempts = AssessmentAttempt::whereHas('assessment', function ($query) use ($enrolledCourses) {
            $query->whereIn('course_id', $enrolledCourses)->where('is_active', true);
        })
            ->where('trainee_id', $traineeId)
            ->where('status', 'submitted')
            ->get();

        // Get unique completed assessments
        $completedAssessments = $completedAttempts->unique('assessment_id')->count();

        // Get passed assessments
        $passedAssessments = $completedAttempts->where('is_passed', true)->unique('assessment_id')->count();

        // Calculate average score
        $averageScore = $completedAttempts->where('percentage', '>', 0)->avg('percentage') ?: 0;

        // Pending assessments
        $pendingAssessments = $totalAssessments - $completedAssessments;

        return response()->json([
            'success' => true,
            'data' => [
                'total_assessments' => $totalAssessments,
                'completed_assessments' => $completedAssessments,
                'passed_assessments' => $passedAssessments,
                'average_score' => round($averageScore, 2),
                'pending_assessments' => max(0, $pendingAssessments)
            ]
        ]);
    }

    /**
     * Get assessments for trainee based on schedule
     */
    public function getScheduleAssessments($scheduleId)
    {
        $traineeId = Auth::id();

        // Get the course from schedule and verify enrollment
        $schedule = Enrolled::where('traineeid', $traineeId)->where('scheduleid', $scheduleId)->first();

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'You are not enrolled in this schedule or schedule not found'
            ], 404);
        }

        // Get assessments for the course
        $assessments = Assessment::where('course_id', $schedule->courseid)
            ->where('is_active', true)
            ->with(['course:courseid,coursename'])
            ->get()
            ->map(function ($assessment) use ($traineeId) {
                $attempts = $assessment->getTraineeAttempts($traineeId);
                $activeAttempt = $assessment->getActiveAttempt($traineeId);
                $canAttempt = $assessment->canAttempt($traineeId);

                return [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'description' => $assessment->description,
                    'time_limit' => $assessment->time_limit,
                    'max_attempts' => $assessment->max_attempts,
                    'passing_score' => $assessment->passing_score,
                    'questions_count' => $assessment->questions_count,
                    'total_points' => $assessment->total_points,
                    'course' => [
                        'id' => $assessment->course->courseid,
                        'name' => $assessment->course->coursename
                    ],
                    'attempts_count' => $attempts->count(),
                    'can_attempt' => $canAttempt,
                    'has_active_attempt' => !is_null($activeAttempt),
                    'active_attempt_id' => $activeAttempt?->id,
                    'best_score' => $attempts->max('percentage'),
                    'last_attempt' => $attempts->first()?->only(['id', 'percentage', 'is_passed', 'submitted_at'])
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $assessments
        ]);
    }

    /**
     * Get assessments for trainee based on enrolled courses
     */
    public function getTraineeAssessments()
    {
        $traineeId = Auth::id();

        // Get enrolled courses
        $enrolledCourses = Enrolled::where('traineeid', $traineeId)->pluck('courseid');

        if ($enrolledCourses->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Get assessments for enrolled courses
        $assessments = Assessment::whereIn('course_id', $enrolledCourses)
            ->where('is_active', true)
            ->with(['course:courseid,fullname'])
            ->get()
            ->map(function ($assessment) use ($traineeId) {
                $attempts = $assessment->getTraineeAttempts($traineeId);
                $activeAttempt = $assessment->getActiveAttempt($traineeId);
                $canAttempt = $assessment->canAttempt($traineeId);

                return [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'description' => $assessment->description,
                    'time_limit' => $assessment->time_limit,
                    'max_attempts' => $assessment->max_attempts,
                    'passing_score' => $assessment->passing_score,
                    'questions_count' => $assessment->questions_count,
                    'total_points' => $assessment->total_points,
                    'course' => [
                        'id' => $assessment->course->courseid,
                        'name' => $assessment->course->fullname
                    ],
                    'attempts_count' => $attempts->count(),
                    'can_attempt' => $canAttempt,
                    'has_active_attempt' => !is_null($activeAttempt),
                    'active_attempt_id' => $activeAttempt?->id,
                    'best_score' => $attempts->max('percentage'),
                    'last_attempt' => $attempts->first()?->only(['id', 'percentage', 'is_passed', 'submitted_at'])
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $assessments
        ]);
    }

    /**
     * Start a new assessment attempt
     */
    public function startAttempt($assessmentId)
    {
        $traineeId = Auth::id();

        // Check if assessment exists and trainee is enrolled
        $assessment = Assessment::findOrFail($assessmentId);

        $enrollment = Enrolled::where('userid', $traineeId)
            ->where('courseid', $assessment->course_id)
            ->where('status', 'active')
            ->exists();

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'You are not enrolled in this course'
            ], 403);
        }

        // Check if trainee can attempt
        if (!$assessment->canAttempt($traineeId)) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum attempts reached'
            ], 403);
        }

        // Check if there's already an active attempt
        if ($assessment->hasActiveAttempt($traineeId)) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active attempt'
            ], 409);
        }

        // Get next attempt number
        $attemptNumber = $assessment->attempts()
            ->where('trainee_id', $traineeId)
            ->max('attempt_number') + 1;

        // Create new attempt
        $attempt = AssessmentAttempt::create([
            'assessment_id' => $assessment->id,
            'trainee_id' => $traineeId,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
            'status' => 'in_progress'
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'started_at' => $attempt->started_at,
                'time_limit' => $assessment->time_limit
            ]
        ]);
    }

    /**
     * Get assessment questions for attempt
     */
    public function getAssessmentQuestions($assessmentId)
    {
        $traineeId = Auth::id();

        $assessment = Assessment::with([
            'assessmentQuestions.question.options' => function ($query) {
                $query->select('id', 'question_id', 'text', 'order');
            }
        ])->findOrFail($assessmentId);

        // Check if trainee has active attempt
        $activeAttempt = $assessment->getActiveAttempt($traineeId);
        if (!$activeAttempt) {
            return response()->json([
                'success' => false,
                'message' => 'No active attempt found'
            ], 404);
        }

        // Check if attempt is expired
        if ($activeAttempt->isExpired()) {
            $activeAttempt->markAsExpired();
            return response()->json([
                'success' => false,
                'message' => 'Assessment attempt has expired'
            ], 410);
        }

        $questions = $assessment->getQuestionsForAttempt()->map(function ($assessmentQuestion) use ($activeAttempt) {
            $question = $assessmentQuestion->question;
            $savedAnswer = $activeAttempt->getAnswerForQuestion($question->id);

            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'points' => $question->points,
                'options' => $question->options,
                'saved_answer' => $savedAnswer?->answer_data
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'assessment' => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'description' => $assessment->description,
                    'instructions' => $assessment->instructions,
                    'time_limit' => $assessment->time_limit,
                    'show_results_immediately' => $assessment->show_results_immediately
                ],
                'attempt' => [
                    'id' => $activeAttempt->id,
                    'started_at' => $activeAttempt->started_at,
                    'time_remaining' => $activeAttempt->getRemainingTime()
                ],
                'questions' => $questions
            ]
        ]);
    }

    /**
     * Save answer for a question
     */
    public function saveAnswer(Request $request, $assessmentId, $questionId)
    {
        $traineeId = Auth::id();

        $assessment = Assessment::findOrFail($assessmentId);
        $activeAttempt = $assessment->getActiveAttempt($traineeId);

        if (!$activeAttempt) {
            return response()->json([
                'success' => false,
                'message' => 'No active attempt found'
            ], 404);
        }

        if ($activeAttempt->isExpired()) {
            $activeAttempt->markAsExpired();
            return response()->json([
                'success' => false,
                'message' => 'Assessment attempt has expired'
            ], 410);
        }

        $request->validate([
            'answer' => 'required'
        ]);

        // Save or update answer
        AssessmentAnswer::updateOrCreate(
            [
                'attempt_id' => $activeAttempt->id,
                'question_id' => $questionId
            ],
            [
                'answer_data' => $request->answer
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Answer saved successfully'
        ]);
    }

    /**
     * Submit assessment attempt
     */
    public function submitAttempt($assessmentId)
    {
        $traineeId = Auth::id();

        $assessment = Assessment::with('assessmentQuestions.question')->findOrFail($assessmentId);
        $activeAttempt = $assessment->getActiveAttempt($traineeId);

        if (!$activeAttempt) {
            return response()->json([
                'success' => false,
                'message' => 'No active attempt found'
            ], 404);
        }

        if ($activeAttempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Attempt already submitted'
            ], 409);
        }

        // Submit the attempt (this will calculate scores)
        $activeAttempt->submit();

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $activeAttempt->id,
                'score' => $activeAttempt->score,
                'percentage' => $activeAttempt->percentage,
                'is_passed' => $activeAttempt->is_passed,
                'show_results_immediately' => $assessment->show_results_immediately
            ]
        ]);
    }

    /**
     * Get assessment result
     */
    public function getResult($attemptId)
    {
        $traineeId = Auth::id();

        $attempt = AssessmentAttempt::with([
            'assessment.assessmentQuestions.question.options',
            'answers'
        ])->where('trainee_id', $traineeId)->findOrFail($attemptId);

        if ($attempt->status === 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Assessment not yet submitted'
            ], 409);
        }

        $assessment = $attempt->assessment;
        $questions = $assessment->assessmentQuestions;

        // Prepare correct answers and explanations
        $correctAnswers = [];
        $explanations = [];

        foreach ($questions as $assessmentQuestion) {
            $question = $assessmentQuestion->question;
            $correctAnswers[$question->id] = $question->correct_answer;
            $explanations[$question->id] = $question->explanation;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'attempt' => $attempt,
                'assessment' => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'passing_score' => $assessment->passing_score,
                    'max_attempts' => $assessment->max_attempts
                ],
                'questions' => $questions,
                'correct_answers' => $correctAnswers,
                'explanations' => $explanations
            ]
        ]);
    }

    /**
     * Get attempt status (for polling during assessment)
     */
    public function getAttemptStatus($attemptId)
    {
        $traineeId = Auth::id();

        $attempt = AssessmentAttempt::where('trainee_id', $traineeId)
            ->findOrFail($attemptId);

        if ($attempt->status === 'in_progress' && $attempt->isExpired()) {
            $attempt->markAsExpired();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $attempt->status,
                'time_remaining' => $attempt->getRemainingTime(),
                'is_expired' => $attempt->status === 'expired'
            ]
        ]);
    }
}

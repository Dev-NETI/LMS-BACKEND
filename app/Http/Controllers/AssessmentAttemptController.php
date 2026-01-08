<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Schedule;
use App\Models\Trainee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssessmentAttemptController extends Controller
{
    /**
     * Get all assessment results for a schedule (Instructor view)
     * GET /api/instructor/schedules/{scheduleId}/assessment-results
     */
    public function getScheduleAssessmentResults($scheduleId)
    {
        try {
            // Get the schedule with course
            $schedule = Schedule::with('course')->findOrFail($scheduleId);

            // Get all enrolled trainees for this schedule
            $enrolledTrainees = $schedule->activeEnrollments()
                ->with('trainee')
                ->get()
                ->pluck('trainee');

            // Get assessments assigned to this schedule
            // Using DB query to avoid cross-database join issues
            $scheduleAssessmentIds = DB::table('schedule_assessments')
                ->where('schedule_id', $scheduleId)
                ->pluck('assessment_id');

            $assessments = Assessment::with('course')
                ->whereIn('id', $scheduleAssessmentIds)
                ->active()
                ->get();

            // Prepare trainee results
            $traineeResults = [];

            foreach ($enrolledTrainees as $trainee) {
                if (!$trainee) continue;

                foreach ($assessments as $assessment) {
                    // Get all attempts for this trainee and assessment
                    $attempts = AssessmentAttempt::where('assessment_id', $assessment->id)
                        ->where('trainee_id', $trainee->traineeid)
                        ->orderBy('attempt_number', 'asc')
                        ->get();

                    // Calculate best score and status
                    $bestPercentage = null;
                    $bestScore = null;
                    $status = 'not_started';
                    $lastAttemptDate = null;

                    if ($attempts->count() > 0) {
                        // Find best score from submitted attempts
                        $submittedAttempts = $attempts->where('status', 'submitted');

                        if ($submittedAttempts->count() > 0) {
                            $bestAttempt = $submittedAttempts->sortByDesc('percentage')->first();
                            $bestPercentage = $bestAttempt->percentage;
                            $bestScore = $bestAttempt->score;

                            // Determine status
                            if ($bestAttempt->is_passed) {
                                $status = 'passed';
                            } else {
                                $status = 'failed';
                            }
                        } else if ($attempts->where('status', 'in_progress')->count() > 0) {
                            $status = 'in_progress';
                        } else {
                            $status = 'completed';
                        }

                        $lastAttemptDate = $attempts->sortByDesc('started_at')->first()->started_at;
                    }

                    $traineeResults[] = [
                        'trainee_id' => $trainee->traineeid,
                        'trainee_name' => $trainee->firstname . ' ' . $trainee->lastname,
                        'trainee_email' => $trainee->email,
                        'rank' => $trainee->rank ?? null,
                        'rankacronym' => $trainee->rankacronym ?? null,
                        'assessment_id' => $assessment->id,
                        'assessment_title' => $assessment->title,
                        'attempts' => $attempts->map(function ($attempt) {
                            return [
                                'id' => $attempt->id,
                                'attempt_number' => $attempt->attempt_number,
                                'started_at' => $attempt->started_at?->toISOString(),
                                'submitted_at' => $attempt->submitted_at?->toISOString(),
                                'score' => $attempt->score,
                                'percentage' => $attempt->percentage,
                                'status' => $attempt->status,
                                'is_passed' => $attempt->is_passed,
                                'time_remaining' => $attempt->time_remaining,
                            ];
                        }),
                        'best_score' => $bestScore,
                        'best_percentage' => $bestPercentage,
                        'attempts_count' => $attempts->count(),
                        'last_attempt_date' => $lastAttemptDate?->toISOString(),
                        'status' => $status,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'schedule_id' => $schedule->scheduleid,
                    'course_name' => $schedule->course->coursename ?? 'Unknown Course',
                    'assessments' => $assessments->map(function ($assessment) {
                        return [
                            'id' => $assessment->id,
                            'title' => $assessment->title,
                            'description' => $assessment->description,
                            'time_limit' => $assessment->time_limit,
                            'max_attempts' => $assessment->max_attempts,
                            'passing_score' => $assessment->passing_score,
                            'questions_count' => $assessment->questions_count,
                            'total_points' => $assessment->total_points,
                        ];
                    }),
                    'trainee_results' => $traineeResults,
                ],
                'message' => 'Assessment results retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching schedule assessment results: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific trainee's assessment attempts (Instructor view)
     * GET /api/instructor/schedules/{scheduleId}/trainees/{traineeId}/assessments/{assessmentId}/attempts
     */
    public function getTraineeAssessmentAttempts($scheduleId, $traineeId, $assessmentId)
    {
        try {
            // Verify the schedule exists
            $schedule = Schedule::findOrFail($scheduleId);

            // Verify the trainee is enrolled in this schedule
            $enrollment = $schedule->activeEnrollments()
                ->where('traineeid', $traineeId)
                ->first();

            if (!$enrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trainee is not enrolled in this schedule'
                ], 404);
            }

            // Get trainee details
            $trainee = Trainee::find($traineeId);

            if (!$trainee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trainee not found'
                ], 404);
            }

            // Get assessment
            $assessment = Assessment::with('course')->findOrFail($assessmentId);

            // Get all attempts for this trainee and assessment
            $attempts = AssessmentAttempt::where('assessment_id', $assessmentId)
                ->where('trainee_id', $traineeId)
                ->with('answers')
                ->orderBy('attempt_number', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'trainee' => [
                        'trainee_id' => $trainee->traineeid,
                        'trainee_name' => $trainee->firstname . ' ' . $trainee->lastname,
                        'email' => $trainee->email,
                        'rank' => $trainee->rank ?? null,
                        'rankacronym' => $trainee->rankacronym ?? null,
                    ],
                    'assessment' => [
                        'id' => $assessment->id,
                        'title' => $assessment->title,
                        'description' => $assessment->description,
                        'time_limit' => $assessment->time_limit,
                        'max_attempts' => $assessment->max_attempts,
                        'passing_score' => $assessment->passing_score,
                        'questions_count' => $assessment->questions_count,
                        'total_points' => $assessment->total_points,
                        'course' => [
                            'courseid' => $assessment->course->courseid ?? null,
                            'coursename' => $assessment->course->coursename ?? null,
                        ],
                    ],
                    'attempts' => $attempts->map(function ($attempt) {
                        return [
                            'id' => $attempt->id,
                            'attempt_number' => $attempt->attempt_number,
                            'started_at' => $attempt->started_at?->toISOString(),
                            'submitted_at' => $attempt->submitted_at?->toISOString(),
                            'score' => $attempt->score,
                            'percentage' => $attempt->percentage,
                            'status' => $attempt->status,
                            'is_passed' => $attempt->is_passed,
                            'time_remaining' => $attempt->time_remaining,
                            'answers_count' => $attempt->answers->count(),
                        ];
                    }),
                ],
                'message' => 'Trainee assessment attempts retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching trainee assessment attempts: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve trainee assessment attempts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed attempt results with answers (Instructor view)
     * GET /api/instructor/attempts/{attemptId}/details
     */
    public function getAttemptDetails($attemptId)
    {
        try {
            $attempt = AssessmentAttempt::with([
                'assessment.assessmentQuestions.question.options',
                'answers',
                'trainee'
            ])->findOrFail($attemptId);

            $attemptDetails = [
                'id' => $attempt->id,
                'attempt_number' => $attempt->attempt_number,
                'started_at' => $attempt->started_at?->toISOString(),
                'submitted_at' => $attempt->submitted_at?->toISOString(),
                'score' => $attempt->score,
                'percentage' => $attempt->percentage,
                'status' => $attempt->status,
                'is_passed' => $attempt->is_passed,
                'trainee' => [
                    'trainee_id' => $attempt->trainee->traineeid,
                    'trainee_name' => $attempt->trainee->firstname . ' ' . $attempt->trainee->lastname,
                    'email' => $attempt->trainee->email,
                ],
                'assessment' => [
                    'id' => $attempt->assessment->id,
                    'title' => $attempt->assessment->title,
                    'passing_score' => $attempt->assessment->passing_score,
                ],
                'questions' => $attempt->assessment->assessmentQuestions->map(function ($aq) use ($attempt) {
                    $question = $aq->question;
                    $answer = $attempt->answers->where('question_id', $question->id)->first();

                    return [
                        'question_id' => $question->id,
                        'question_text' => $question->question_text,
                        'question_type' => $question->question_type,
                        'points' => $question->points,
                        'options' => $question->options,
                        'answer_data' => $answer?->answer_data,
                        'is_correct' => $answer?->is_correct,
                        'points_earned' => $answer?->points_earned,
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $attemptDetails,
                'message' => 'Attempt details retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching attempt details: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attempt details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

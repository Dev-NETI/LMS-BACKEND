<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAnswer;
use App\Models\Enrolled;
use App\Models\SecurityLog;
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

        // Get assessments that are specifically assigned to this schedule
        $assessments = Assessment::where('course_id', $schedule->courseid)
            ->where('is_active', true)
            ->assignedToSchedule($scheduleId)
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
                        'coursename' => $assessment->course->coursename
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

        // Get enrolled schedules for the trainee
        $enrolledSchedules = Enrolled::where('traineeid', $traineeId)->pluck('scheduleid');

        if ($enrolledSchedules->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Get assessments that are assigned to the trainee's enrolled schedules
        $assessments = Assessment::where('is_active', true)
            ->whereHas('scheduleAssignments', function ($query) use ($enrolledSchedules) {
                $query->whereIn('schedule_id', $enrolledSchedules)
                    ->where('is_active', true)
                    ->where(function ($subQuery) {
                        $now = now();
                        $subQuery->where(function ($dateQuery) use ($now) {
                            $dateQuery->whereNull('available_from')
                                ->orWhere('available_from', '<=', $now);
                        })
                            ->where(function ($dateQuery) use ($now) {
                                $dateQuery->whereNull('available_until')
                                    ->orWhere('available_until', '>=', $now);
                            });
                    });
            })
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
                        'coursename' => $assessment->course->coursename
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
     * Get assessment details for trainee (without starting an attempt)
     */
    public function getAssessment($assessmentId)
    {
        $traineeId = Auth::id();

        // Check if assessment exists and trainee is enrolled
        $assessment = Assessment::with(['course:courseid,coursename,coursecode'])->findOrFail($assessmentId);

        // Check if trainee is enrolled in the course
        $enrollment = Enrolled::where('traineeid', $traineeId)
            ->where('courseid', $assessment->course_id)
            ->where('pendingid', 0)
            ->exists();

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'You are not enrolled in this course'
            ], 403);
        }

        // Get trainee's attempts for this assessment
        $attempts = $assessment->getTraineeAttempts($traineeId);
        $activeAttempt = $assessment->getActiveAttempt($traineeId);
        $canAttempt = $assessment->canAttempt($traineeId);

        $assessmentData = [
            'id' => $assessment->id,
            'title' => $assessment->title,
            'description' => $assessment->description,
            'instructions' => $assessment->instructions,
            'time_limit' => $assessment->time_limit,
            'max_attempts' => $assessment->max_attempts,
            'passing_score' => $assessment->passing_score,
            'questions_count' => $assessment->questions_count,
            'total_points' => $assessment->total_points,
            'show_results_immediately' => $assessment->show_results_immediately,
            'is_active' => $assessment->is_active,
            'course' => [
                'id' => $assessment->course->courseid,
                'coursename' => $assessment->course->coursename,
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $assessmentData,
            'attempts' => $attempts->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'attempt_number' => $attempt->attempt_number,
                    'percentage' => $attempt->percentage,
                    'is_passed' => $attempt->is_passed,
                    'status' => $attempt->status,
                    'submitted_at' => $attempt->submitted_at,
                    'started_at' => $attempt->started_at
                ];
            }),
            'can_attempt' => $canAttempt,
            'attempts_remaining' => max(0, $assessment->max_attempts - $attempts->count()),
            'has_active_attempt' => !is_null($activeAttempt),
            'active_attempt_id' => $activeAttempt?->id,
            'best_score' => $attempts->max('percentage'),
            'last_attempt' => $attempts->first()?->only(['id', 'percentage', 'is_passed', 'submitted_at'])
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

        $enrollment = Enrolled::where('traineeid', $traineeId)
            ->where('courseid', $assessment->course_id)
            ->where('pendingid', 0)
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

        // Get assessment questions for the attempt
        $assessment = Assessment::with([
            'assessmentQuestions.question.options' => function ($query) {
                $query->select('id', 'question_id', 'text', 'order');
            }
        ])->findOrFail($assessmentId);

        $questions = $assessment->getQuestionsForAttempt()->map(function ($assessmentQuestion) use ($attempt) {
            $question = $assessmentQuestion->question;
            $savedAnswer = $attempt->getAnswerForQuestion($question->id);

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
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'description' => $assessment->description,
                'instructions' => $assessment->instructions,
                'time_limit' => $assessment->time_limit,
                'max_attempts' => $assessment->max_attempts,
                'passing_score' => $assessment->passing_score,
                'questions_count' => $assessment->questions_count,
                'show_results_immediately' => $assessment->show_results_immediately
            ],
            'attempt' => [
                'id' => $attempt->id,
                'attempt_number' => $attempt->attempt_number,
                'started_at' => $attempt->started_at,
                'status' => $attempt->status,
                'time_remaining' => $attempt->getRemainingTime(),
                'answers' => [] // No answers yet for a new attempt
            ],
            'questions' => $questions,
            'time_limit' => $assessment->time_limit
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
     * Log security events for assessment monitoring
     */
    public function logSecurityEvent(Request $request, $assessmentId)
    {
        $traineeId = Auth::id();

        $request->validate([
            'activity' => 'required|string',
            'timestamp' => 'required|date',
            'attempt_id' => 'nullable|integer'
        ]);

        try {
            // Parse event type from activity
            $eventType = $this->parseEventTypeFromActivity($request->activity);

            // Create security log entry in database
            SecurityLog::logEvent(
                $traineeId,
                $assessmentId,
                $eventType,
                $request->activity,
                $request->additional_data ?? [],
                $request->attempt_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Security event logged successfully'
            ]);
        } catch (\Exception $e) {
            // Fallback to Laravel log if database logging fails
            \Log::error('Failed to log security event to database', [
                'error' => $e->getMessage(),
                'trainee_id' => $traineeId,
                'assessment_id' => $assessmentId,
                'activity' => $request->activity
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to log security event'
            ], 500);
        }
    }

    /**
     * Parse event type from activity string
     */
    private function parseEventTypeFromActivity($activity)
    {
        $activity = strtolower($activity);

        if (strpos($activity, 'tab_switch') !== false || strpos($activity, 'tab switch') !== false) {
            return SecurityLog::EVENT_TYPES['TAB_SWITCH'];
        } elseif (strpos($activity, 'right_click_blocked') !== false || strpos($activity, 'right click blocked') !== false) {
            return SecurityLog::EVENT_TYPES['RIGHT_CLICK_BLOCKED'];
        } elseif (strpos($activity, 'shortcut_blocked') !== false || strpos($activity, 'blocked shortcut') !== false) {
            return SecurityLog::EVENT_TYPES['SHORTCUT_BLOCKED'];
        } elseif (strpos($activity, 'fullscreen_denied') !== false || strpos($activity, 'fullscreen denied') !== false) {
            return SecurityLog::EVENT_TYPES['FULLSCREEN_DENIED'];
        } elseif (strpos($activity, 'window_focus_lost') !== false || strpos($activity, 'focus lost') !== false) {
            return SecurityLog::EVENT_TYPES['WINDOW_FOCUS_LOST'];
        } elseif (strpos($activity, 'assessment_started') !== false || strpos($activity, 'started') !== false) {
            return SecurityLog::EVENT_TYPES['ASSESSMENT_STARTED'];
        } elseif (strpos($activity, 'assessment_completed') !== false || strpos($activity, 'completed') !== false) {
            return SecurityLog::EVENT_TYPES['ASSESSMENT_COMPLETED'];
        } elseif (strpos($activity, 'copy') !== false) {
            return SecurityLog::EVENT_TYPES['COPY_ATTEMPT'];
        } elseif (strpos($activity, 'paste') !== false) {
            return SecurityLog::EVENT_TYPES['PASTE_ATTEMPT'];
        } elseif (strpos($activity, 'developer_tools') !== false || strpos($activity, 'f12') !== false) {
            return SecurityLog::EVENT_TYPES['DEVELOPER_TOOLS'];
        } else {
            return SecurityLog::EVENT_TYPES['SUSPICIOUS_ACTIVITY'];
        }
    }

    /**
     * Submit assessment attempt
     */
    public function submitAttempt(Request $request, $assessmentId)
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

    /**
     * Get all security logs for admin monitoring (Admin only)
     */
    public function getSecurityLogs(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);
        $eventType = $request->get('event_type');
        $severity = $request->get('severity');
        $traineeId = $request->get('trainee_id');
        $assessmentId = $request->get('assessment_id');

        $query = SecurityLog::with(['trainee:id,firstname,lastname', 'assessment:id,title'])
            ->orderBy('event_timestamp', 'desc');

        // Apply filters
        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        if ($severity) {
            $query->where('severity', $severity);
        }

        if ($traineeId) {
            $query->where('trainee_id', $traineeId);
        }

        if ($assessmentId) {
            $query->where('assessment_id', $assessmentId);
        }

        // Paginate results
        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage()
            ]
        ]);
    }

    /**
     * Get security logs for a specific assessment (Admin only)
     */
    public function getAssessmentSecurityLogs($assessmentId)
    {
        $logs = SecurityLog::with(['trainee:id,firstname,lastname', 'assessment:id,title'])
            ->where('assessment_id', $assessmentId)
            ->orderBy('event_timestamp', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * Get security logs for a specific trainee (Admin only)
     */
    public function getTraineeSecurityLogs($traineeId)
    {
        $logs = SecurityLog::with(['trainee:id,firstname,lastname', 'assessment:id,title'])
            ->where('trainee_id', $traineeId)
            ->orderBy('event_timestamp', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * Parse a log line to extract security event data
     */
    private function parseLogLine($line)
    {
        // Extract timestamp from log line
        preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $timeMatches);

        // Extract JSON data from log line
        if (preg_match('/\{.*\}/', $line, $jsonMatches)) {
            $jsonData = json_decode($jsonMatches[0], true);

            if ($jsonData) {
                return [
                    'timestamp' => $timeMatches[1] ?? null,
                    'trainee_id' => $jsonData['trainee_id'] ?? null,
                    'assessment_id' => $jsonData['assessment_id'] ?? null,
                    'attempt_id' => $jsonData['attempt_id'] ?? null,
                    'activity' => $jsonData['activity'] ?? null,
                    'ip_address' => $jsonData['ip_address'] ?? null,
                    'user_agent' => $jsonData['user_agent'] ?? null,
                    'event_timestamp' => $jsonData['timestamp'] ?? null,
                ];
            }
        }

        return null;
    }
}

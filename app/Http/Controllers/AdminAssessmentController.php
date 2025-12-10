<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Question;
use App\Models\Schedule;
use App\Models\ScheduleAssessment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminAssessmentController extends Controller
{
    /**
     * Get assessments for a course with schedule assignment information
     */
    public function getAssessmentsByCourse($courseId)
    {
        $assessments = Assessment::where('course_id', $courseId)
            ->with([
                'createdBy:id,f_name,l_name',
                'scheduleAssignments.schedule:scheduleid,batchno,startdateformat,enddateformat'
            ])
            ->withCount('assessmentQuestions as questions_count')
            ->get()
            ->map(function ($assessment) {
                $assessment->total_points = $assessment->questions()->sum('points');
                $assessment->assigned_schedules = $assessment->scheduleAssignments->where('is_active', true)->map(function ($assignment) {
                    return [
                        'schedule_id' => $assignment->schedule_id,
                        'schedule_name' => $assignment->schedule->batchno ?? 'Unknown Schedule',
                        'schedule_code' => $assignment->schedule->scheduleid ?? 'N/A',
                        'available_from' => $assignment->available_from,
                        'available_until' => $assignment->available_until,
                    ];
                });
                return $assessment;
            });

        return response()->json([
            'success' => true,
            'data' => $assessments
        ]);
    }

    /**
     * Create a new assessment
     */
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:main_db.tblcourses,courseid',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'time_limit' => 'required|integer|min:1',
            'max_attempts' => 'required|integer|min:1',
            'passing_score' => 'required|numeric|min:0|max:100',
            'is_randomized' => 'boolean',
            'show_results_immediately' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*' => 'exists:questions,id'
        ]);

        try {
            DB::beginTransaction();

            // Create assessment
            $assessment = Assessment::create([
                'course_id' => $request->course_id,
                'title' => $request->title,
                'description' => $request->description,
                'instructions' => $request->instructions,
                'time_limit' => $request->time_limit,
                'max_attempts' => $request->max_attempts,
                'passing_score' => $request->passing_score,
                'is_active' => true,
                'is_randomized' => $request->is_randomized ?? false,
                'show_results_immediately' => $request->show_results_immediately ?? true,
                'created_by_user_id' => Auth::id()
            ]);

            // Add questions to assessment
            foreach ($request->questions as $index => $questionId) {
                AssessmentQuestion::create([
                    'assessment_id' => $assessment->id,
                    'question_id' => $questionId,
                    'order' => $index + 1
                ]);
            }

            DB::commit();

            // Load the created assessment with relationships
            $assessment->load(['createdBy:id,f_name,l_name']);
            $assessment->questions_count = count($request->questions);
            $assessment->total_points = Question::whereIn('id', $request->questions)->sum('points');

            return response()->json([
                'success' => true,
                'data' => $assessment,
                'message' => 'Assessment created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create assessment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update assessment
     */
    public function update(Request $request, $id)
    {
        $assessment = Assessment::findOrFail($id);

        $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'time_limit' => 'integer|min:1',
            'max_attempts' => 'integer|min:1',
            'passing_score' => 'numeric|min:0|max:100',
            'is_active' => 'boolean',
            'is_randomized' => 'boolean',
            'show_results_immediately' => 'boolean'
        ]);

        $assessment->update($request->only([
            'title',
            'description',
            'instructions',
            'time_limit',
            'max_attempts',
            'passing_score',
            'is_active',
            'is_randomized',
            'show_results_immediately'
        ]));

        return response()->json([
            'success' => true,
            'data' => $assessment,
            'message' => 'Assessment updated successfully'
        ]);
    }

    /**
     * Delete assessment
     */
    public function destroy($id)
    {
        $assessment = Assessment::findOrFail($id);

        // Check if there are any attempts
        $hasAttempts = $assessment->attempts()->exists();

        if ($hasAttempts) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete assessment with existing attempts'
            ], 409);
        }

        $assessment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assessment deleted successfully'
        ]);
    }

    /**
     * Get assessment details with questions
     */
    public function show($id)
    {
        $assessment = Assessment::with([
            'assessmentQuestions.question.options',
            'createdBy:id,f_name,l_name',
            'course:courseid,coursename'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $assessment
        ]);
    }

    /**
     * Update assessment questions
     */
    public function updateQuestions(Request $request, $id)
    {
        $assessment = Assessment::findOrFail($id);

        $request->validate([
            'questions' => 'required|array|min:1',
            'questions.*' => 'exists:questions,id'
        ]);

        try {
            DB::beginTransaction();

            // Remove existing questions
            AssessmentQuestion::where('assessment_id', $assessment->id)->delete();

            // Add new questions
            foreach ($request->questions as $index => $questionId) {
                AssessmentQuestion::create([
                    'assessment_id' => $assessment->id,
                    'question_id' => $questionId,
                    'order' => $index + 1
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Assessment questions updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update questions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assessment statistics
     */
    public function getAssessmentStats($id)
    {
        $assessment = Assessment::with('attempts')->findOrFail($id);

        $attempts = $assessment->attempts;
        $totalAttempts = $attempts->count();
        $submittedAttempts = $attempts->where('status', 'submitted');
        $passedAttempts = $submittedAttempts->where('is_passed', true);

        $stats = [
            'total_attempts' => $totalAttempts,
            'submitted_attempts' => $submittedAttempts->count(),
            'passed_attempts' => $passedAttempts->count(),
            'average_score' => $submittedAttempts->avg('percentage') ?? 0,
            'pass_rate' => $submittedAttempts->count() > 0
                ? ($passedAttempts->count() / $submittedAttempts->count()) * 100
                : 0,
            'unique_trainees' => $attempts->unique('trainee_id')->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get schedule-based assessments for trainees
     */
    public function getScheduleAssessments($scheduleId)
    {
        // Get the course from schedule
        $schedule = DB::table('course_schedules')
            ->where('sched_id', $scheduleId)
            ->first();

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found'
            ], 404);
        }

        // Get assessments for the course
        $assessments = Assessment::where('course_id', $schedule->courseid)
            ->where('is_active', true)
            ->with(['course:courseid,coursename'])
            ->get()
            ->map(function ($assessment) {
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
                    'schedule_id' => $scheduleId
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $assessments
        ]);
    }

    /**
     * Get available schedules for a course
     */
    public function getCourseSchedules($courseId)
    {
        $schedules = Schedule::where('courseid', $courseId)
            ->select('scheduleid', 'batchno', 'startdateformat', 'enddateformat')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules
        ]);
    }

    /**
     * Assign assessment to schedule(s)
     */
    public function assignToSchedules(Request $request, $assessmentId)
    {
        $request->validate([
            'schedule_ids' => 'required|array|min:1',
            'schedule_ids.*' => 'exists:main_db.tblcourseschedule,scheduleid',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after:available_from'
        ]);

        // Validate that assignment dates are within schedule date ranges
        if ($request->available_from || $request->available_until) {
            foreach ($request->schedule_ids as $scheduleId) {
                $schedule = Schedule::where('scheduleid', $scheduleId)->first();

                if (!$schedule) {
                    return response()->json([
                        'success' => false,
                        'message' => "Schedule with ID {$scheduleId} not found"
                    ], 404);
                }

                $scheduleStart = new Carbon($schedule->startdateformat);
                $scheduleEnd = new Carbon($schedule->enddateformat);

                // Check if it's a same-day schedule
                $isSameDay = $scheduleStart->isSameDay($scheduleEnd);

                if ($request->available_from) {
                    $availableFrom = new Carbon($request->available_from);

                    if ($isSameDay) {
                        // For same-day schedules, both dates must be the same day
                        if (!$availableFrom->isSameDay($scheduleStart)) {
                            return response()->json([
                                'success' => false,
                                'message' => "Available from date must be {$scheduleStart->format('M d, Y')} for single-day schedule {$schedule->batchno}"
                            ], 422);
                        }
                    } else {
                        // For multi-day schedules, check normal range
                        if ($availableFrom->lt($scheduleStart) || $availableFrom->gt($scheduleEnd)) {
                            return response()->json([
                                'success' => false,
                                'message' => "Available from date must be within schedule {$schedule->batchno} date range ({$scheduleStart->format('M d, Y')} - {$scheduleEnd->format('M d, Y')})"
                            ], 422);
                        }
                    }
                }

                if ($request->available_until) {
                    $availableUntil = new Carbon($request->available_until);

                    if ($isSameDay) {
                        // For same-day schedules, both dates must be the same day
                        if (!$availableUntil->isSameDay($scheduleStart)) {
                            return response()->json([
                                'success' => false,
                                'message' => "Available until date must be {$scheduleStart->format('M d, Y')} for single-day schedule {$schedule->batchno}"
                            ], 422);
                        }
                    } else {
                        // For multi-day schedules, check normal range
                        if ($availableUntil->lt($scheduleStart) || $availableUntil->gt($scheduleEnd)) {
                            return response()->json([
                                'success' => false,
                                'message' => "Available until date must be within schedule {$schedule->batchno} date range ({$scheduleStart->format('M d, Y')} - {$scheduleEnd->format('M d, Y')})"
                            ], 422);
                        }
                    }
                }
            }
        }

        try {
            DB::beginTransaction();

            foreach ($request->schedule_ids as $scheduleId) {
                $schedule = Schedule::where('scheduleid', $scheduleId)->first();
                $scheduleStart = new Carbon($schedule->startdateformat);
                $scheduleEnd = new Carbon($schedule->enddateformat);

                // Auto-set to full schedule range if dates not provided
                $availableFrom = $request->available_from;
                $availableUntil = $request->available_until;

                // If dates are not provided, use the full schedule range
                if (!$availableFrom) {
                    $availableFrom = $scheduleStart->format('Y-m-d');
                }
                if (!$availableUntil) {
                    $availableUntil = $scheduleEnd->format('Y-m-d');
                }

                ScheduleAssessment::updateOrCreate(
                    [
                        'assessment_id' => $assessmentId,
                        'schedule_id' => $scheduleId
                    ],
                    [
                        'is_active' => true,
                        'available_from' => $availableFrom,
                        'available_until' => $availableUntil
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Assessment assigned to schedule(s) successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign assessment to schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove assessment from schedule
     */
    public function removeFromSchedule(Request $request, $assessmentId, $scheduleId)
    {
        try {
            $deleted = ScheduleAssessment::where('assessment_id', $assessmentId)
                ->where('schedule_id', $scheduleId)
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Assessment removed from schedule successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment assignment not found'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove assessment from schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update schedule assignment settings
     */
    public function updateScheduleAssignment(Request $request, $assessmentId, $scheduleId)
    {
        $request->validate([
            'is_active' => 'boolean',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after:available_from'
        ]);

        try {
            $assignment = ScheduleAssessment::where('assessment_id', $assessmentId)
                ->where('schedule_id', $scheduleId)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment assignment not found'
                ], 404);
            }

            $assignment->update([
                'is_active' => $request->input('is_active', $assignment->is_active),
                'available_from' => $request->available_from,
                'available_until' => $request->available_until
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Schedule assignment updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update schedule assignment: ' . $e->getMessage()
            ], 500);
        }
    }
}

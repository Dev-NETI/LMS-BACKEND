<?php

namespace App\Http\Controllers;

use App\Models\TraineeProgress;
use App\Models\CourseContent;
use App\Models\Course;
use App\Models\Enrolled;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TraineeProgressController extends Controller
{
    // Get progress overview for a specific course and trainee
    public function getCourseProgress($courseId, $scheduleId): JsonResponse
    {
        try {
            $traineeId = $traineeId ?? Auth::id();

            // Overall course progress
            $progressData = TraineeProgress::getCourseProgress($traineeId, $courseId);

            // Get all modules in the course
            $data = CourseContent::where('course_id', $courseId)->get();

            foreach ($data as $module) {

                // Create module-level progress if not exists
                TraineeProgress::firstOrCreate(
                    [
                        'trainee_id' => $traineeId,
                        'course_id' => $courseId,
                        'course_content_id' => $module->id
                    ],
                    [
                        'schedule_id' => $scheduleId,
                        'status' => 'not_started',
                        'completion_percentage' => 0,
                        'time_spent' => 0,
                        'started_at' => null,
                        'completed_at' => null,
                        'last_activity' => now(),
                        'activity_log' => null,
                        'notes' => null,
                    ]
                );
            }

            $modules = TraineeProgress::where('course_id', $courseId)->get();


            return response()->json([
                'success' => true,
                'trainee_id' => $traineeId,
                'course_id' => $courseId,
                'overview' => $progressData,
                'modules' => $modules
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get course progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Get progress for all trainees in a course (admin view)
    public function getTraineeProgressByCourse(Request $request, $courseId): JsonResponse
    {
        try {
            $query = TraineeProgress::with(['trainee', 'courseContent'])
                ->byCourse($courseId);

            // Filter by status if provided
            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            $progress = $query->get();

            // Get course details
            $course = Course::find($courseId);

            // Get all enrolled trainees for this course
            $enrolledTrainees = Enrolled::where('courseid', $courseId)
                ->pluck('traineeid');

            // Calculate statistics
            $stats = [
                'total_trainees' => $enrolledTrainees->count(),
                'active_trainees' => $progress->groupBy('trainee_id')->count(),
                'completed_trainees' => $progress->where('status', 'completed')->groupBy('trainee_id')->count(),
                'average_completion' => $progress->avg('completion_percentage') ?? 0,
                'total_time_spent' => $progress->sum('time_spent')
            ];

            return response()->json([
                'success' => true,
                'course_id' => $courseId,
                'course' => $course,
                'progress' => $progress,
                'statistics' => $stats,
                'enrolled_trainees' => $enrolledTrainees
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get trainee progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update progress for a specific module
    public function updateProgress(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'trainee_id' => 'required|integer',
                'course_content_id' => 'required|integer|exists:course_content,id',
                'completion_percentage' => 'required|numeric|min:0|max:100',
                'time_spent' => 'nullable|integer|min:0',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get course content to determine course_id
            $courseContent = CourseContent::findOrFail($request->course_content_id);

            // Find or create progress record
            $progress = TraineeProgress::updateOrCreate(
                [
                    'trainee_id' => $request->trainee_id,
                    'course_content_id' => $request->course_content_id
                ],
                [
                    'course_id' => $courseContent->course_id,
                    'completion_percentage' => $request->completion_percentage,
                    'time_spent' => ($request->time_spent ?? 0),
                    'notes' => $request->notes,
                    'last_activity' => now()
                ]
            );

            // Update status based on completion percentage
            if ($request->completion_percentage >= 100 && $progress->status !== 'completed') {
                $progress->markAsCompleted();
            } elseif ($request->completion_percentage > 0 && $progress->status === 'not_started') {
                $progress->markAsStarted();
            }

            // Log activity
            $progress->logActivity('progress_updated', [
                'completion_percentage' => $request->completion_percentage,
                'time_spent_added' => $request->time_spent ?? 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Progress updated successfully',
                'progress' => $progress->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Mark a module as started
    public function markAsStarted(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'trainee_id' => 'required|integer',
                'course_content_id' => 'required|integer|exists:course_content,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $courseContent = CourseContent::findOrFail($request->course_content_id);

            $progress = TraineeProgress::updateOrCreate(
                [
                    'trainee_id' => $request->trainee_id,
                    'course_content_id' => $request->course_content_id
                ],
                [
                    'course_id' => $courseContent->course_id,
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'last_activity' => now()
                ]
            );

            $progress->logActivity('module_started', [
                'content_title' => $courseContent->title
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Module marked as started',
                'progress' => $progress
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark module as started',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Mark a module as completed
    public function markAsCompleted(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'trainee_id' => 'required|integer',
                'course_content_id' => 'required|integer|exists:course_content,id',
                'time_spent' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $courseContent = CourseContent::findOrFail($request->course_content_id);

            $progress = TraineeProgress::updateOrCreate(
                [
                    'trainee_id' => $request->trainee_id,
                    'course_content_id' => $request->course_content_id
                ],
                [
                    'course_id' => $courseContent->course_id,
                    'status' => 'completed',
                    'completion_percentage' => 100.00,
                    'completed_at' => now(),
                    'last_activity' => now()
                ]
            );

            if ($request->has('time_spent')) {
                $progress->time_spent += $request->time_spent;
                $progress->save();
            }

            $progress->logActivity('module_completed', [
                'content_title' => $courseContent->title,
                'time_spent' => $request->time_spent ?? 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Module marked as completed',
                'progress' => $progress
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark module as completed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get detailed progress report for admin dashboard
    public function getProgressReport(Request $request): JsonResponse
    {
        try {
            $query = TraineeProgress::with(['trainee', 'course', 'courseContent']);

            // Filter by course if provided
            if ($request->has('course_id')) {
                $query->byCourse($request->course_id);
            }

            // Filter by trainee if provided
            if ($request->has('trainee_id')) {
                $query->byTrainee($request->trainee_id);
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->where('last_activity', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('last_activity', '<=', $request->date_to);
            }

            $progress = $query->orderBy('last_activity', 'desc')->get();

            // Calculate summary statistics
            $summary = [
                'total_progress_records' => $progress->count(),
                'completed_modules' => $progress->where('status', 'completed')->count(),
                'in_progress_modules' => $progress->where('status', 'in_progress')->count(),
                'not_started_modules' => $progress->where('status', 'not_started')->count(),
                'total_time_spent' => $progress->sum('time_spent'),
                'average_completion' => $progress->avg('completion_percentage') ?? 0,
                'active_trainees' => $progress->groupBy('trainee_id')->count(),
                'active_courses' => $progress->groupBy('course_id')->count()
            ];

            return response()->json([
                'success' => true,
                'progress' => $progress,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get progress report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get progress for all trainees in a schedule
    public function getTraineeProgressBySchedule(Request $request, $scheduleId): JsonResponse
    {
        try {
            // Get enrolled trainees for this schedule
            $enrolledTrainees = Enrolled::with('trainee')->where('scheduleid', $scheduleId)->get();

            if ($enrolledTrainees->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'schedule_id' => $scheduleId,
                    'enrolled_trainees' => [],
                    'progress_data' => [],
                    'statistics' => [
                        'total_trainees' => 0,
                        'active_trainees' => 0,
                        'completed_trainees' => 0,
                        'average_completion' => 0,
                        'total_time_spent' => 0
                    ]
                ]);
            }

            // Get the course ID from the first enrolled trainee
            $courseId = $enrolledTrainees->first()->courseid;
            $total_content = CourseContent::where('course_id', $courseId)->count();

            // Get progress for all enrolled trainees
            $traineeIds = $enrolledTrainees->pluck('traineeid');

            $query = TraineeProgress::with(['trainee', 'courseContent'])
                ->whereIn('trainee_id', $traineeIds)
                ->where('course_id', $courseId);

            // Filter by status if provided
            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            $progress = $query->get();

            // Calculate statistics
            $stats = [
                'total_trainees' => $enrolledTrainees->count(),
                'active_trainees' => $progress->groupBy('trainee_id')->count(),
                'completed_trainees' => $progress->where('status', 'completed')->groupBy('trainee_id')->count(),
                'average_completion' => $progress->avg('completion_percentage') ?? 0,
                'total_time_spent' => $progress->sum('time_spent')
            ];

            // Combine enrolled trainee data with progress data
            $progressData = $enrolledTrainees->map(function ($trainee) use ($progress, $total_content) {
                $traineeProgress = $progress->where('trainee_id', $trainee->traineeid);

                return [
                    'trainee_id' => $trainee->traineeid,
                    'trainee_name' => $trainee->trainee->formatName(),
                    'email' => $trainee->trainee->email,
                    'rank' => $trainee->trainee->rank->rank,
                    'course_id' => $trainee->courseid,
                    'progress' => $traineeProgress->values(),
                    'total_modules' => $total_content,
                    'completed_modules' => $traineeProgress->where('status', 'completed')->count(),
                    'in_progress_modules' => $traineeProgress->where('status', 'in_progress')->count(),
                    'total_time_spent' => $traineeProgress->sum('time_spent'),
                    'overall_completion_percentage' => $traineeProgress->count() > 0
                        ? round(($traineeProgress->where('status', 'completed')->count() / $traineeProgress->count()) * 100, 2)
                        : 0,
                    'last_activity' => $traineeProgress->max('last_activity')
                ];
            });

            return response()->json([
                'success' => true,
                'schedule_id' => $scheduleId,
                'course_id' => $courseId,
                'enrolled_trainees' => $enrolledTrainees,
                'progress_data' => $progressData,
                'statistics' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get trainee progress by schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get activity log for a specific progress record
    public function getActivityLog(Request $request, $progressId): JsonResponse
    {
        try {
            $progress = TraineeProgress::with(['trainee', 'courseContent'])->findOrFail($progressId);

            return response()->json([
                'success' => true,
                'progress' => $progress,
                'activity_log' => $progress->activity_log ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get activity log',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

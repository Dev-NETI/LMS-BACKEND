<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function getCourseScheduleByCourseId(Request $request)
    {
        try {
            // Get pagination parameters with defaults
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $search = $request->get('search', '');

            // Validate pagination parameters
            $page = max(1, (int) $page);
            $limit = min(100, max(1, (int) $limit)); // Max 100 items per page

            // Build the query
            $query = Schedule::with(['course', 'activeEnrollments.trainee'])
                ->where('deletedid', 0)
                ->where('courseid', $request->id);

            // Add search functionality for batch number and course name
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('batchno', 'LIKE', '%' . $search . '%')
                        ->orWhereHas('course', function ($courseQuery) use ($search) {
                            $courseQuery->where('coursename', 'LIKE', '%' . $search . '%');
                        });
                });
            }

            // Order by start date (newest first)
            $query->orderBy('startdateformat', 'desc');

            // Get total count for pagination
            $totalItems = $query->count();

            // Apply pagination
            $schedules = $query->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            // Transform the data to include enrollment counts
            $schedulesWithEnrollment = $schedules->map(function ($schedule) {
                return [
                    'scheduleid' => $schedule->scheduleid,
                    'batchno' => $schedule->batchno,
                    'startdateformat' => $schedule->startdateformat,
                    'enddateformat' => $schedule->enddateformat,
                    'courseid' => $schedule->courseid,
                    'course' => $schedule->course,
                    'total_enrolled' => $schedule->countEnrolledStudents(),
                    'active_enrolled' => $schedule->countActiveEnrolledStudents(),
                    'enrolled_students' => $schedule->activeEnrollments->map(function ($enrollment) {
                        return [
                            'enrollment_id' => $enrollment->id,
                            'trainee_id' => $enrollment->traineeid,
                            'trainee_name' => $enrollment->trainee ? $enrollment->trainee->l_name ?? 'N/A' : 'N/A',
                            'date_registered' => $enrollment->dateregistered,
                            'status' => $enrollment->pendingid === 0 ? 'Enrolled' : 'Pending'
                        ];
                    })
                ];
            });

            // Calculate pagination metadata
            $totalPages = ceil($totalItems / $limit);
            $hasNextPage = $page < $totalPages;
            $hasPreviousPage = $page > 1;

            return response()->json([
                'success' => true,
                'data' => $schedulesWithEnrollment,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'totalItems' => $totalItems,
                    'itemsPerPage' => $limit,
                    'hasNextPage' => $hasNextPage,
                    'hasPreviousPage' => $hasPreviousPage
                ],
                'message' => 'Schedules with enrollment data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schedules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCourseScheduleById(Request $request)
    {
        try {
            $schedules = Schedule::with(['course', 'activeEnrollments.trainee'])
                ->where('deletedid', 0)
                ->where('scheduleid', $request->id)
                ->get();

            // Transform the data to include enrollment counts
            $schedulesWithEnrollment = $schedules->map(function ($schedule) {
                return [
                    'scheduleid' => $schedule->scheduleid,
                    'batchno' => $schedule->batchno,
                    'startdateformat' => $schedule->startdateformat,
                    'enddateformat' => $schedule->enddateformat,
                    'courseid' => $schedule->courseid,
                    'course' => $schedule->course,
                    'total_enrolled' => $schedule->countEnrolledStudents(),
                    'active_enrolled' => $schedule->countActiveEnrolledStudents(),
                    'instructor' => ($schedule->instructor->user_id == 93) ? 'N/A' :  $schedule->instructor->fullname,
                    'alternative_instructor' => ($schedule->alt_instructor->user_id == 93) ? 'N/A' :  $schedule->alt_instructor->fullname,
                    'assessor' => ($schedule->assessor->user_id == 93) ? 'N/A' : $schedule->assessor->fullname,
                    'alternative_assessor' => ($schedule->alt_assessor->user_id == 93) ? 'N/A' : $schedule->alt_assessor->fullname,
                    'seat_instructor' => ($schedule->seat_instructor->user_id == 93) ? 'N/A' : $schedule->seat_instructor->fullname,
                    'enrolled_students' => $schedule->enrollments->map(function ($enrollment) {
                        return [
                            'enrollment_id' => $enrollment->enroledid,
                            'trainee_id' => $enrollment->traineeid,
                            'trainee_name' => $enrollment->trainee->formatName(),
                            'date_confirmed' => $enrollment->dateconfirmed,
                            'status' => $enrollment->pendingid === 0 ? 'Enrolled' : 'Pending',
                            'email' => $enrollment->trainee->email,
                            'contact_num' => $enrollment->trainee->formatContactNumber(),
                            'rank' => $enrollment->trainee->rank->rank,
                            'rankacronym' => $enrollment->trainee->rank->rankacronym
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $schedulesWithEnrollment,
                'message' => 'Schedules with enrollment data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schedules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCourseScheduleByInstructorId(Request $request)
    {
        $user = Auth::user();
        try {
            // Get pagination parameters with defaults
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            $search = $request->get('search', '');

            // Validate pagination parameters
            $page = max(1, (int) $page);
            $limit = min(100, max(1, (int) $limit)); // Max 100 items per page

            // Build the query
            $query = Schedule::with(['course', 'activeEnrollments.trainee'])
                ->where('deletedid', 0)
                ->where(function ($q) use ($user) {
                    $q->where('instructorid', $user->user_id)
                        ->orWhere('alt_instructorid', $user->user_id)
                        ->orWhere('assessorid', $user->user_id)
                        ->orWhere('alt_assessorid', $user->user_id);
                });

            // Add search functionality for batch number and course name
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('batchno', 'LIKE', '%' . $search . '%')
                        ->orWhereHas('course', function ($courseQuery) use ($search) {
                            $courseQuery->where('coursename', 'LIKE', '%' . $search . '%');
                        });
                });
            }

            // Order by start date (newest first)
            $query->orderBy('startdateformat', 'desc');

            // Get total count for pagination
            $totalItems = $query->count();

            // Apply pagination
            $schedules = $query->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            // Transform the data to include enrollment counts
            $schedulesWithEnrollment = $schedules->map(function ($schedule) {
                return [
                    'scheduleid' => $schedule->scheduleid,
                    'batchno' => $schedule->batchno,
                    'startdateformat' => $schedule->startdateformat,
                    'enddateformat' => $schedule->enddateformat,
                    'courseid' => $schedule->courseid,
                    'course' => $schedule->course,
                    'total_enrolled' => $schedule->countEnrolledStudents(),
                    'active_enrolled' => $schedule->countActiveEnrolledStudents(),
                    'modeofdelivery' => $schedule->course->modeofdelivery->modeofdelivery,
                    'enrolled_students' => $schedule->activeEnrollments->map(function ($enrollment) {
                        return [
                            'enrollment_id' => $enrollment->id,
                            'trainee_id' => $enrollment->traineeid,
                            'trainee_name' => $enrollment->trainee ? $enrollment->trainee->l_name ?? 'N/A' : 'N/A',
                            'date_registered' => $enrollment->dateregistered,
                            'status' => $enrollment->pendingid === 0 ? 'Enrolled' : 'Pending'
                        ];
                    })
                ];
            });

            // Calculate pagination metadata
            $totalPages = ceil($totalItems / $limit);
            $hasNextPage = $page < $totalPages;
            $hasPreviousPage = $page > 1;

            return response()->json([
                'success' => true,
                'data' => $schedulesWithEnrollment,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'totalItems' => $totalItems,
                    'itemsPerPage' => $limit,
                    'hasNextPage' => $hasNextPage,
                    'hasPreviousPage' => $hasPreviousPage
                ],
                'message' => 'Schedules with enrollment data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schedules',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

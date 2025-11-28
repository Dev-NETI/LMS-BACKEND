<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function getCourseScheduleByCourseId(Request $request)
    {
        try {
            $schedules = Schedule::with(['course', 'activeEnrollments.trainee'])
                ->where('deletedid', 0)
                ->where('courseid', $request->id)
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
                            'status' => $enrollment->pendingid
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
                    'enrolled_students' => $schedule->activeEnrollments->map(function ($enrollment) {
                        return [
                            'enrollment_id' => $enrollment->id,
                            'trainee_id' => $enrollment->traineeid,
                            'trainee_name' => $enrollment->trainee ? $enrollment->trainee->l_name ?? 'N/A' : 'N/A',
                            'date_registered' => $enrollment->dateregistered,
                            'status' => $enrollment->pendingid
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
}

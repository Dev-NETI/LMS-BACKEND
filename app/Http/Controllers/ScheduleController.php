<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function getCourseScheduleById(Request $request)
    {
        $schedules = Schedule::where('deletedid', 0)->where('courseid', $request->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
            'message' => 'Schedules retrieved successfully'
        ], 200);
    }

    public function getAllSchedules(Request $request)
    {
        $schedules = Schedule::where('deletedid', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
            'message' => 'All schedules retrieved successfully'
        ], 200);
    }
}

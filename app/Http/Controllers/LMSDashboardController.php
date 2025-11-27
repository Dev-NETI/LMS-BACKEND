<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LMSDashboardController extends Controller
{
    public function dashboard()
    {
        $trainee = Auth::guard('trainee')->user();
        
        $stats = [
            'total_courses' => 12,
            'completed_courses' => 8,
            'in_progress' => 3,
            'certificates' => 6
        ];
        
        $recent_courses = [
            ['name' => 'Safety Training', 'progress' => 85, 'status' => 'In Progress'],
            ['name' => 'Maritime Law', 'progress' => 100, 'status' => 'Completed'],
            ['name' => 'Navigation Basics', 'progress' => 60, 'status' => 'In Progress'],
        ];

        return view('lms.dashboard', compact('trainee', 'stats', 'recent_courses'));
    }

    public function courses()
    {
        $courses = [
            ['id' => 1, 'name' => 'Safety Training', 'duration' => '4 hours', 'level' => 'Beginner', 'status' => 'Active'],
            ['id' => 2, 'name' => 'Maritime Law', 'duration' => '6 hours', 'level' => 'Intermediate', 'status' => 'Active'],
            ['id' => 3, 'name' => 'Navigation Basics', 'duration' => '8 hours', 'level' => 'Advanced', 'status' => 'Active'],
            ['id' => 4, 'name' => 'Emergency Procedures', 'duration' => '3 hours', 'level' => 'Beginner', 'status' => 'Active'],
        ];

        return view('lms.courses', compact('courses'));
    }

    public function profile()
    {
        $trainee = Auth::guard('trainee')->user();
        return view('lms.profile', compact('trainee'));
    }

    public function certificates()
    {
        $certificates = [
            ['name' => 'Safety Training Certificate', 'date' => '2024-10-15', 'validity' => '2026-10-15'],
            ['name' => 'Maritime Law Certificate', 'date' => '2024-09-20', 'validity' => '2026-09-20'],
        ];

        return view('lms.certificates', compact('certificates'));
    }
}
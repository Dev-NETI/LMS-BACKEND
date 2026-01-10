<?php

namespace App\Http\Controllers;

use App\Models\Trainee;
use App\Models\User;
use App\Models\Enrolled;
use App\Models\AssessmentAttempt;
use App\Models\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');
        $user = User::where('email', $credentials['email'])->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            $token = $user->createToken('admin-auth-token', ['*'], now()->addDays(7))->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'f_name' => $user->f_name,
                    'l_name' => $user->l_name,
                ]
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'f_name' => $user->f_name,
                'l_name' => $user->l_name,
            ]
        ], 200);
    }

    public function getAllUsers(Request $request)
    {
        // Get query parameters with defaults
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);
        $search = $request->get('search');
        $sortBy = $request->get('sortBy', 'f_name');
        $sortOrder = $request->get('sortOrder', 'asc');
        $isActive = $request->get('isActive');

        // Start building the query
        $query = User::where('u_type', 1);

        // Apply active/inactive filter
        if ($isActive !== null) {
            $query->where('is_active', $isActive === 'true' ? 1 : 0);
        } else {
            $query->whereIn('is_active', [0, 1]);
        }

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('f_name', 'LIKE', "%{$search}%")
                    ->orWhere('m_name', 'LIKE', "%{$search}%")
                    ->orWhere('l_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhereRaw("CONCAT(f_name, ' ', IFNULL(m_name, ''), ' ', l_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Apply sorting
        $allowedSortColumns = ['f_name', 'l_name', 'email', 'is_active', 'created_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        }

        // Get total count before pagination
        $totalItems = $query->count();

        // Apply pagination
        $users = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        // Calculate pagination metadata
        $totalPages = ceil($totalItems / $limit);
        $hasNextPage = $page < $totalPages;
        $hasPreviousPage = $page > 1;

        // Transform users data to match frontend expectations
        $transformedUsers = $users->map(function ($user) {
            return [
                'user_id' => $user->id,
                'f_name' => $user->f_name,
                'm_name' => $user->m_name,
                'l_name' => $user->l_name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedUsers,
            'pagination' => [
                'currentPage' => (int) $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
                'itemsPerPage' => (int) $limit,
                'hasNextPage' => $hasNextPage,
                'hasPreviousPage' => $hasPreviousPage,
            ],
            'message' => 'Users retrieved successfully'
        ], 200);
    }

    public function getAllInstructor(Request $request)
    {
        // Get query parameters with defaults
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);
        $search = $request->get('search');
        $sortBy = $request->get('sortBy', 'f_name');
        $sortOrder = $request->get('sortOrder', 'asc');
        $isActive = $request->get('isActive');

        // Start building the query
        $query = User::where('u_type', 2);

        // Apply active/inactive filter
        if ($isActive !== null) {
            $query->where('is_active', $isActive === 'true' ? 1 : 0);
        } else {
            $query->whereIn('is_active', [0, 1]);
        }

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('f_name', 'LIKE', "%{$search}%")
                    ->orWhere('m_name', 'LIKE', "%{$search}%")
                    ->orWhere('l_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhereRaw("CONCAT(f_name, ' ', IFNULL(m_name, ''), ' ', l_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Apply sorting
        $allowedSortColumns = ['f_name', 'l_name', 'email', 'is_active', 'created_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        }

        // Get total count before pagination
        $totalItems = $query->count();

        // Apply pagination
        $users = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        // Calculate pagination metadata
        $totalPages = ceil($totalItems / $limit);
        $hasNextPage = $page < $totalPages;
        $hasPreviousPage = $page > 1;

        // Transform users data to match frontend expectations
        $transformedUsers = $users->map(function ($user) {
            return [
                'user_id' => $user->id,
                'f_name' => $user->f_name,
                'm_name' => $user->m_name,
                'l_name' => $user->l_name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedUsers,
            'pagination' => [
                'currentPage' => (int) $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
                'itemsPerPage' => (int) $limit,
                'hasNextPage' => $hasNextPage,
                'hasPreviousPage' => $hasPreviousPage,
            ],
            'message' => 'Users retrieved successfully'
        ], 200);
    }

    public function getAllTrainees(Request $request)
    {
        // Get query parameters with defaults
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);
        $search = $request->get('search');
        $sortBy = $request->get('sortBy', 'f_name');
        $sortOrder = $request->get('sortOrder', 'asc');

        // Start building the query - only get active trainees (is_active = 1)
        $query = Trainee::where('is_active', 1);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('f_name', 'LIKE', "%{$search}%")
                    ->orWhere('m_name', 'LIKE', "%{$search}%")
                    ->orWhere('l_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%")
                    ->orWhereRaw("CONCAT(f_name, ' ', IFNULL(m_name, ''), ' ', l_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Apply sorting
        $allowedSortColumns = ['f_name', 'l_name', 'email', 'username', 'created_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        }

        // Get total count before pagination
        $totalItems = $query->count();

        // Apply pagination
        $trainees = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        // Calculate pagination metadata
        $totalPages = ceil($totalItems / $limit);
        $hasNextPage = $page < $totalPages;
        $hasPreviousPage = $page > 1;

        // Transform trainees data to match frontend expectations
        $transformedTrainees = $trainees->map(function ($trainee) {
            return [
                'trainee_id' => $trainee->traineeid,
                'f_name' => $trainee->f_name,
                'm_name' => $trainee->m_name,
                'l_name' => $trainee->l_name,
                'email' => $trainee->email,
                'username' => $trainee->username,
                'is_active' => (bool) $trainee->is_active,
                'created_at' => $trainee->created_at,
                'updated_at' => $trainee->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedTrainees,
            'pagination' => [
                'currentPage' => (int) $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
                'itemsPerPage' => (int) $limit,
                'hasNextPage' => $hasNextPage,
                'hasPreviousPage' => $hasPreviousPage,
            ],
            'message' => 'Trainees retrieved successfully'
        ], 200);
    }

    public function getTraineeProfile($traineeId)
    {
        try {
            // Get trainee basic info
            $trainee = Trainee::where('traineeid', $traineeId)
                ->where('is_active', 1)
                ->first();

            if (!$trainee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trainee not found or inactive'
                ], 404);
            }

            // Get enrolled courses with schedule and course details
            $enrolledCourses = Enrolled::where('traineeid', $traineeId)
                ->with(['course', 'schedule'])
                ->get()
                ->map(function ($enrollment) {
                    return [
                        'enrollment_id' => $enrollment->enroledid,
                        'course_id' => $enrollment->courseid,
                        'course_name' => $enrollment->course->coursename ?? 'N/A',
                        'course_code' => $enrollment->course->coursecode ?? 'N/A',
                        'date_registered' => $enrollment->dateregistered,
                        'date_completed' => $enrollment->datecompleted,
                        'status' => $enrollment->status,
                        'schedule_id' => $enrollment->scheduleid,
                        'schedule_data' => $enrollment->schedule,

                    ];
                });

            // Get assessment attempts with assessment details
            $assessmentAttempts = AssessmentAttempt::where('trainee_id', $traineeId)
                ->with(['assessment'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($attempt) {
                    return [
                        'attempt_id' => $attempt->id,
                        'assessment_id' => $attempt->assessment_id,
                        'assessment_title' => $attempt->assessment->title ?? 'N/A',
                        'attempt_number' => $attempt->attempt_number,
                        'started_at' => $attempt->started_at,
                        'submitted_at' => $attempt->submitted_at,
                        'time_remaining' => $attempt->time_remaining,
                        'score' => $attempt->score,
                        'percentage' => $attempt->percentage,
                        'status' => $attempt->status,
                        'is_passed' => $attempt->is_passed,
                        'created_at' => $attempt->created_at,
                    ];
                });

            // Get security logs with assessment details
            $securityLogs = SecurityLog::where('trainee_id', $traineeId)
                ->with(['assessment', 'attempt'])
                ->orderBy('event_timestamp', 'desc')
                ->limit(100) // Limit to recent 100 logs
                ->get()
                ->map(function ($log) {
                    return [
                        'log_id' => $log->id,
                        'assessment_id' => $log->assessment_id,
                        'assessment_title' => $log->assessment->title ?? 'N/A',
                        'attempt_id' => $log->attempt_id,
                        'activity' => $log->activity,
                        'event_type' => $log->event_type,
                        'severity' => $log->severity,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                        'additional_data' => $log->additional_data,
                        'event_timestamp' => $log->event_timestamp,
                        'created_at' => $log->created_at,
                    ];
                });

            // Calculate statistics
            $stats = [
                'total_courses' => $enrolledCourses->count(),
                'completed_courses' => $enrolledCourses->where('status', 'completed')->count(),
                'total_assessments' => $assessmentAttempts->count(),
                'passed_assessments' => $assessmentAttempts->where('is_passed', true)->count(),
                'failed_assessments' => $assessmentAttempts->where('is_passed', false)->count(),
                'security_violations' => $securityLogs->whereIn('severity', ['high', 'critical'])->count(),
                'average_score' => $assessmentAttempts->where('status', 'submitted')->avg('percentage'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'trainee' => [
                        'trainee_id' => $trainee->traineeid,
                        'f_name' => $trainee->f_name,
                        'm_name' => $trainee->m_name,
                        'l_name' => $trainee->l_name,
                        'email' => $trainee->email,
                        'username' => $trainee->username,
                        'is_active' => (bool) $trainee->is_active,
                        'created_at' => $trainee->created_at,
                        'updated_at' => $trainee->updated_at,
                    ],
                    'enrolled_courses' => $enrolledCourses,
                    'assessment_attempts' => $assessmentAttempts,
                    'security_logs' => $securityLogs,
                    'statistics' => $stats,
                ],
                'message' => 'Trainee profile retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve trainee profile: ' . $e->getMessage()
            ], 500);
        }
    }
}

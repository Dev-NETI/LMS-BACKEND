<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Enrolled;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CourseController extends Controller
{
    public function getEnrolledCourses(Request $request)
    {
        try {
            $trainee = Auth::user();

            if (!$trainee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $enrolledCourses = Enrolled::where('traineeid', $trainee->traineeid)->with('course', 'schedule')->get();

            return response()->json([
                'success' => true,
                'data' => $enrolledCourses,
                'message' => 'Enrolled courses retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve enrolled courses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            // Get pagination parameters
            $page = (int) $request->get('page', 1);
            $limit = (int) $request->get('limit', 9);
            $search = $request->get('search');

            // Ensure valid pagination values
            $page = max(1, $page);
            $limit = max(1, min(100, $limit)); // Max 100 items per page

            // Build query - start simple to avoid column name issues
            $query = Course::query()->whereIn('coursetypeid', [1, 2, 3, 4, 5, 7, 8, 12, 13]);

            // Get the actual table name and check available columns once
            $tableName = (new Course())->getTable();
            $availableColumns = Schema::connection('main_db')->getColumnListing($tableName);

            // Filter by deletedid if the column exists
            if (in_array('deletedid', $availableColumns)) {
                $query->where('deletedid', 0);
            }

            // Add search functionality
            if ($search && !empty(trim($search))) {
                $searchTerm = trim($search);

                // Define potential search columns
                $potentialSearchColumns = [
                    'coursecode',
                    'coursename',
                    'coursedescription',
                ];

                // Find which search columns actually exist
                $searchColumns = array_intersect($potentialSearchColumns, $availableColumns);

                if (!empty($searchColumns)) {
                    $query->where(function ($q) use ($searchTerm, $searchColumns) {
                        foreach ($searchColumns as $column) {
                            $q->orWhere($column, 'LIKE', "%{$searchTerm}%");
                        }

                        // Also search in coursetype relationship
                        $q->orWhereHas('coursetype', function ($courseTypeQuery) use ($searchTerm) {
                            $courseTypeQuery->where('coursetype', 'LIKE', "%{$searchTerm}%");
                        });
                    });
                }
            }

            // Get total count for pagination
            $totalItems = $query->count();

            // Calculate pagination metadata
            $totalPages = $totalItems > 0 ? ceil($totalItems / $limit) : 1;
            $currentPage = max(1, min($page, $totalPages));
            $offset = ($currentPage - 1) * $limit;

            // Get paginated results
            $courses = $query->offset($offset)
                ->with(['coursetype', 'modeofdelivery'])
                ->limit($limit)
                ->orderBy('coursename', 'ASC')
                ->get();

            // Build pagination metadata
            $pagination = [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
                'itemsPerPage' => $limit,
                'hasNextPage' => $currentPage < $totalPages,
                'hasPreviousPage' => $currentPage > 1
            ];

            return response()->json([
                'success' => true,
                'data' => $courses,
                'pagination' => $pagination,
                'message' => 'Courses retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('CourseController index error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve courses',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function show(Request $request)
    {
        try {
            $course = Course::find($request->id);

            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $course,
                'message' => 'Course retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve course',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

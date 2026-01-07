<?php

namespace App\Http\Controllers;

use App\Models\User;
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
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InstructorAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        // Find user by email and check if they are an instructor (u_type = 2) and active
        $user = User::where('email', $credentials['email'])
            ->where('u_type', 2)
            ->where('is_active', 1)
            ->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            // Create token with instructor guard
            $token = $user->createToken('instructor-auth-token', ['*'], now()->addDays(7))->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'f_name' => $user->f_name,
                    'm_name' => $user->m_name,
                    'l_name' => $user->l_name,
                    'u_type' => $user->u_type,
                    'is_active' => $user->is_active,
                ]
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials or account not authorized for instructor access'
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
                'm_name' => $user->m_name,
                'l_name' => $user->l_name,
                'u_type' => $user->u_type,
                'is_active' => $user->is_active,
            ]
        ], 200);
    }
}

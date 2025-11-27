<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Trainee;

class TraineeAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');
        $trainee = Trainee::where('email', $credentials['email'])->first();

        if ($trainee && Hash::check($credentials['password'], $trainee->password)) {
            $token = $trainee->createToken('trainee-auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $trainee->traineeid,
                    'username' => $trainee->username,
                    'email' => $trainee->email,
                    'f_name' => $trainee->f_name,
                    'l_name' => $trainee->l_name,
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
        $trainee = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $trainee->traineeid,
                'username' => $trainee->username,
                'email' => $trainee->email,
                'f_name' => $trainee->f_name,
                'l_name' => $trainee->l_name,
            ]
        ], 200);
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken('trainee-auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token
        ], 200);
    }
}

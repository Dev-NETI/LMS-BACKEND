<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Trainee;

class LMSAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('trainee')->check()) {
            return redirect('/dashboard');
        }
        
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');
        $trainee = Trainee::where('email', $credentials['email'])->first();

        if ($trainee && Hash::check($credentials['password'], $trainee->password)) {
            Auth::guard('trainee')->login($trainee);
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials'])->withInput();
    }

    public function logout(Request $request)
    {
        Auth::guard('trainee')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/login');
    }
}
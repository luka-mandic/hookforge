<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function loginAsTestUser(Request $request): RedirectResponse
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@hookforge.test'],
            [
                'name' => 'Demo Recruiter',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showRegisterForm(): View
    {
        return view('auth.register');
    }

    public function showForgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }
}

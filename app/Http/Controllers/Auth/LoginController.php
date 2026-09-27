<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lightweight session auth (no Breeze/Jetstream dependency).
 * Only the six staff/parent roles can sign in — students never get accounts.
 */
class LoginController extends Controller
{
    /** Login page. */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => session('status'),
        ]);
    }

    /** Validate credentials, remember session, redirect to role dashboard. */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        $user = $request->user();

        // Suspended / deactivated accounts may not establish a session
        if (! $user->isActive()) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'This account is inactive. Please contact the school office.',
            ])->onlyInput('email');
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $request->session()->regenerate();

        // Staff & parents all land on the same URL — dispatched by role
        return redirect()->intended(route('dashboard'));
    }

    /** Sign out and invalidate the session. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}

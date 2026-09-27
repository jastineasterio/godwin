<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shares data with EVERY Inertia page (all 6 role dashboards + public site).
 * Anything sensitive (passwords, tokens) is explicitly excluded in $user.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template shell rendered on first visit (and full reloads).
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Properties shared with every page.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // Authenticated user (safe subset only) -------------------------
            'auth' => [
                'user' => $request->user()?->toAuthArray(),
            ],

            // Flash messages for one-shot alerts ----------------------------
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],

            // School brand info reused by layouts, footers and dashboards ---
            'school' => config('school'),

            // Ziggy-free route helper: known named URLs for the React side ---
            'routes' => [
                'home' => url('/'),
                'login' => url('/login'),
                'logout' => url('/logout'),
                'apply' => url('/apply'),
                'dashboard' => url('/dashboard'),
                'contact' => url('/contact'),
            ],
        ];
    }
}

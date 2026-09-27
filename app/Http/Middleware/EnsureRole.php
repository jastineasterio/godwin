<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC route guard — usage: ->middleware('role:admin,head_of_school')
 *
 * Inactive / suspended accounts are bounced to login even with the right role.
 */
class EnsureRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles  One or more UserRole values
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            return redirect()->route('login');
        }

        $allowed = array_map(
            fn (string $role) => UserRole::tryFrom($role),
            $roles
        );

        if (! in_array($user->role, array_filter($allowed), true)) {
            // Right account, wrong area → send to their own dashboard
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to access that area.');
        }

        return $next($request);
    }
}

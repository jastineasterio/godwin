<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Single /dashboard URL → dispatches to the role-specific controller so each
 * of the six roles gets its own page + live chart dataset.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user?->isActive(), 403, 'Account inactive.');

        return match ($user->role) {
            UserRole::Admin => app(AdminDashboardController::class)(),
            UserRole::SeniorPastor => app(SeniorPastorDashboardController::class)(),
            UserRole::HeadOfSchool => app(HeadOfSchoolDashboardController::class)(),
            UserRole::Teacher => app(TeacherDashboardController::class)($request),
            UserRole::Accountant => app(AccountantDashboardController::class)(),
            UserRole::Parent => app(ParentDashboardController::class)($request),

            default => abort(403, 'Unknown role.'),
        };
    }
}

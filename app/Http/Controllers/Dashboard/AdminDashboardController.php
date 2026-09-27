<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\SchoolApplication;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ADMINISTRATOR — system governance: users, audit trail, website CMS.
 */
class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $now = Carbon::now();

        // --- KPI cards ---------------------------------------------------
        $totalStudents = Student::count();
        $totalTeachers = User::role(UserRole::Teacher)->count();
        $totalParents = User::role(UserRole::Parent)->count();
        $activeUsers = User::active()->count();

        // --- Monthly enrollment growth (line chart, last 8 months) --------
        // Grouped in PHP so the query works identically on MySQL & SQLite.
        $enrollmentGrowth = Student::query()
            ->whereNotNull('admission_date')
            ->where('admission_date', '>=', $now->copy()->subMonths(7)->startOfMonth())
            ->pluck('admission_date')
            ->groupBy(fn ($date) => Carbon::parse($date)->format('Y-m'))
            ->sortKeys()
            ->map(fn ($dates, $month) => [
                'month' => Carbon::parse($month.'-01')->format('M'),
                'students' => $dates->count(),
            ])
            ->values();

        // --- User distribution by role (bar chart) ------------------------
        $userActivity = collect(UserRole::cases())
            ->map(fn (UserRole $role) => [
                'role' => str_replace('_', ' ', ucfirst($role->value)),
                'users' => User::role($role)->count(),
            ])
            ->values();

        // --- System health panel ------------------------------------------
        $systemHealth = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => config('database.default'),
            'cache_store' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'disk_free_mb' => (int) (disk_free_space(base_path()) / 1048576),
            'active_sessions' => 0,
        ];

        // --- Latest activity feeds ----------------------------------------
        $recentRegistrations = Student::latest()->take(5)
            ->get(['id', 'reg_no', 'first_name', 'last_name', 'class_id', 'created_at']);

        $recentLogs = AuditLog::with('user')->latest('created_at')->take(8)->get([
            'id', 'user_id', 'action', 'description', 'created_at',
        ]);

        $pendingApplications = SchoolApplication::pending()->count();

        $recentNews = Announcement::latest()->take(5)
            ->get(['id', 'title', 'type', 'is_published', 'created_at']);

        return Inertia::render('Dashboards/Admin', [
            'stats' => [
                ['label' => 'Total Students', 'value' => $totalStudents, 'accent' => 'primary'],
                ['label' => 'Total Teachers', 'value' => $totalTeachers, 'accent' => 'secondary'],
                ['label' => 'Total Parents', 'value' => $totalParents, 'accent' => 'accent'],
                ['label' => 'Active System Users', 'value' => $activeUsers, 'accent' => 'primary'],
            ],
            'charts' => [
                'enrollmentGrowth' => $enrollmentGrowth,
                'userActivity' => $userActivity,
            ],
            'systemHealth' => $systemHealth,
            'recentRegistrations' => $recentRegistrations,
            'recentLogs' => $recentLogs,
            'recentNews' => $recentNews,
            'pendingApplications' => $pendingApplications,
        ]);
    }
}

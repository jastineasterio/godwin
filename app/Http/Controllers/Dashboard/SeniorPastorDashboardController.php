<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\NoteType;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\BehaviorNote;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SENIOR PASTOR — spiritual/educational oversight: read-only strategic view.
 */
class SeniorPastorDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $now = Carbon::now();

        // --- KPI cards ---------------------------------------------------
        $totalEnrollment = Student::active()->count();

        $attendanceRate = $this->generalAttendanceRate($now->copy()->startOfMonth(), $now);

        $spiritualActivities = BehaviorNote::whereIn('type', [NoteType::Spiritual, NoteType::Character])
            ->whereYear('occurred_on', $now->year)
            ->count();

        $characterNotes = BehaviorNote::where('is_positive', true)->count();

        // --- Monthly attendance overview (bar chart, last 6 months) -------
        $monthlyAttendance = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($now) {
                $start = $now->copy()->subMonths($monthsAgo)->startOfMonth();
                $end = $start->copy()->endOfMonth();

                return [
                    'month' => $start->format('M'),
                    'rate' => $this->generalAttendanceRate($start, $end) ?? 0,
                ];
            });

        // --- Spiritual milestone progress (radar chart) --------------------
        $radar = collect(NoteType::cases())
            ->map(fn (NoteType $type) => [
                'dimension' => $type->label(),
                'count' => BehaviorNote::where('type', $type)->count(),
            ])
            ->values();

        // --- Pastoral broadcast widget -------------------------------------
        $broadcasts = Announcement::published()
            ->latest('published_at')
            ->take(5)
            ->get(['id', 'title', 'excerpt', 'type', 'published_at']);

        return Inertia::render('Dashboards/SeniorPastor', [
            'stats' => [
                ['label' => 'Total Enrollment', 'value' => $totalEnrollment, 'accent' => 'primary'],
                ['label' => 'General Attendance Rate', 'value' => ($attendanceRate ?? 0).'%', 'accent' => 'secondary'],
                ['label' => 'Spiritual Activities (YTD)', 'value' => $spiritualActivities, 'accent' => 'accent'],
                ['label' => 'Character Notes', 'value' => $characterNotes, 'accent' => 'primary'],
            ],
            'charts' => [
                'monthlyAttendance' => $monthlyAttendance,
                'spiritualRadar' => $radar,
            ],
            'broadcasts' => $broadcasts,
        ]);
    }

    /** % of marked attendance rows that were present/late in a date range. */
    private function generalAttendanceRate(Carbon $start, Carbon $end): ?float
    {
        $rows = Attendance::between($start->toDateString(), $end->toDateString())
            ->get(['status']);

        if ($rows->isEmpty()) {
            return null;
        }

        $present = $rows->filter(fn ($row) => $row->status->countsAsPresent())->count();

        return round(($present / $rows->count()) * 100, 1);
    }
}

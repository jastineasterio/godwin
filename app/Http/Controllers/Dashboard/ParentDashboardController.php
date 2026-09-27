<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PARENT PORTAL — multi-child selector.
 *
 * The active child is chosen via `?child={id}` (or defaults to the first
 * linked child), enabling instant switching WITHOUT re-login: each switch is
 * just an Inertia visit that re-renders this page with the new child's data.
 */
class ParentDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $parent = $request->user();

        // --- All linked children (child switcher tabs) --------------------
        $children = $parent->students()
            ->get([
                'students.id', 'students.reg_no', 'students.first_name',
                'students.last_name', 'students.photo_path', 'students.class_id',
            ])
            ->map(fn (Student $child) => [
                'id' => $child->id,
                'reg_no' => $child->reg_no,
                'name' => $child->full_name,
                'class' => $child->class?->name,
                'class_id' => $child->class_id,
            ]);

        // A parent account with no linked children gets a friendly empty
        // state (the school office links children during enrolment).
        if ($children->isEmpty()) {
            return Inertia::render('Dashboards/Parent', [
                'children' => [],
                'activeChildId' => null,
                'stats' => [],
                'profile' => null,
                'charts' => ['attendanceDonut' => [], 'subjectGrades' => []],
                'invoices' => [],
            ]);
        }

        // Active child from query string, validated against linked children
        $activeId = (int) $request->query('child', $children->first()['id']);
        $active = $children->firstWhere('id', $activeId) ?? $children->first();

        $child = Student::with('class:id,name,code,level')->findOrFail($active['id']);

        // --- Child summary profile card -----------------------------------
        $attendanceRate = $child->attendanceRate(
            Carbon::now()->copy()->subMonths(1)->toDateString(),
            Carbon::now()->toDateString()
        );

        $recentAssessment = $child->assessments()
            ->latest('obtained_at')
            ->with('subject:id,name')
            ->first();

        // --- Monthly attendance doughnut (last 30 days) --------------------
        $window = Attendance::forStudent($child->id)
            ->between(Carbon::now()->subDays(29)->toDateString(), Carbon::now()->toDateString())
            ->get(['status']);

        $attendanceDonut = [
            ['name' => 'Present', 'value' => $window->filter(fn ($a) => $a->status->countsAsPresent())->count()],
            ['name' => 'Absent', 'value' => $window->filter(fn ($a) => $a->status->value === 'absent')->count()],
            ['name' => 'Late', 'value' => $window->filter(fn ($a) => $a->status->value === 'late')->count()],
            ['name' => 'Excused', 'value' => $window->filter(fn ($a) => $a->status->value === 'excused')->count()],
        ];

        // --- Subject grade breakdown (bar chart, percentage per subject) ----
        $subjectGrades = $child->assessments()
            ->with('subject:id,name')
            ->whereNotNull('subject_id')
            ->get()
            ->groupBy(fn (Assessment $a) => $a->subject?->name ?? 'General')
            ->map(function ($rows, $subject) {
                $avg = $rows->average(fn (Assessment $a) => (float) $a->percentage);

                return ['subject' => $subject, 'score' => round($avg ?? 0, 1)];
            })
            ->values();

        // --- Fee statement summary ------------------------------------------
        $invoices = $child->invoices()->latest('issue_date')->take(5)->get([
            'id', 'invoice_number', 'total_amount', 'amount_paid', 'status', 'due_date',
        ]);

        $outstanding = (float) $child->invoices()->outstanding()->sum('total_amount')
            - (float) $child->invoices()->outstanding()->sum('amount_paid');
        $outstanding = max(0, $outstanding);

        return Inertia::render('Dashboards/Parent', [
            'children' => $children,
            'activeChildId' => $child->id,
            'stats' => [
                ['label' => 'Attendance (30 days)', 'value' => ($attendanceRate ?? 0).'%', 'accent' => 'primary'],
                ['label' => 'Class', 'value' => $child->class?->name ?? '—', 'accent' => 'secondary'],
                ['label' => 'Recent Result', 'value' => $recentAssessment?->percentage !== null
                    ? $recentAssessment->percentage.'%'
                    : '—', 'accent' => 'accent'],
                ['label' => 'Fee Balance', 'value' => 'TZS '.number_format($outstanding, 0), 'accent' => 'primary'],
            ],
            'profile' => [
                'name' => $child->full_name,
                'reg_no' => $child->reg_no,
                'class' => $child->class?->name,
                'photo' => $child->photo_path,
                'recentResult' => $recentAssessment
                    ? [
                        'title' => $recentAssessment->title,
                        'percentage' => $recentAssessment->percentage,
                        'subject' => $recentAssessment->subject?->name,
                    ]
                    : null,
            ],
            'charts' => [
                'attendanceDonut' => array_values(array_filter(
                    $attendanceDonut,
                    fn ($slice) => $slice['value'] > 0
                )),
                'subjectGrades' => $subjectGrades,
            ],
            'invoices' => $invoices,
        ]);
    }
}

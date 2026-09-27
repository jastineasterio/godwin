<?php

namespace App\Http\Controllers\Academics;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\BehaviorNote;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PRINTABLE PROGRESS REPORT (report card).
 *
 * A server-rendered, print-optimised Blade page — parents use the browser's
 * "Print / Save as PDF". Access is limited to the child's class teacher, the
 * Head of School, the Administrator, and the child's own parents.
 */
class ReportCardController extends Controller
{
    public function __invoke(Request $request, Student $student): View
    {
        $this->authorizeAccess($request, $student);

        $student->load('class.teacher');
        $term = Term::current()->first() ?? Term::orderByDesc('start_date')->first();

        // --- Attendance summary for the term --------------------------
        $rows = Attendance::forStudent($student->id)
            ->when($term, fn ($q) => $q->whereBetween('date', [$term->start_date, $term->end_date]))
            ->get(['date', 'status']);

        $attendance = [
            'total' => $rows->count(),
            'present' => $rows->filter(fn ($a) => $a->status === AttendanceStatus::Present)->count(),
            'late' => $rows->filter(fn ($a) => $a->status === AttendanceStatus::Late)->count(),
            'absent' => $rows->filter(fn ($a) => $a->status === AttendanceStatus::Absent)->count(),
            'excused' => $rows->filter(fn ($a) => $a->status === AttendanceStatus::Excused)->count(),
            'rate' => $student->attendanceRate(
                $term?->start_date?->toDateString(),
                $term?->end_date?->toDateString()
            ),
        ];

        // --- Subject-wise averages ------------------------------------
        $subjects = $student->assessments()
            ->whereNotNull('subject_id')
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->with('subject:id,name,code')
            ->get()
            ->groupBy(fn (Assessment $a) => $a->subject?->name ?? 'General')
            ->map(function ($items, $name) {
                $scored = $items->filter(fn (Assessment $a) => (float) $a->max_score > 0);
                $max = (float) $scored->sum('max_score');

                return [
                    'name' => $name,
                    'entries' => $items->count(),
                    'percentage' => $max > 0 ? round((float) $scored->sum('score') / $max * 100, 1) : 0.0,
                ];
            })
            ->values();

        // --- Overall position within the class ------------------------
        // NOTE: the eager-load closure must never call the query builder
        // itself — it receives a Relation, which is not callable.
        $classAverages = Student::where('class_id', $student->class_id)
            ->where('status', 'active')
            ->with(['assessments' => function ($query) use ($term) {
                if ($term) {
                    $query->where('term_id', $term->id);
                }
            }])
            ->get()
            ->map(function (Student $s) {
                $scored = $s->assessments->filter(fn (Assessment $a) => (float) $a->max_score > 0);
                $max = (float) $scored->sum('max_score');

                return [
                    'id' => $s->id,
                    'avg' => $max > 0 ? round((float) $scored->sum('score') / $max * 100, 2) : 0.0,
                ];
            })
            ->sortByDesc('avg')
            ->values();

        $position = $classAverages->search(fn ($row) => $row['id'] === $student->id);

        // --- Teacher / character remarks -------------------------------
        $remarks = BehaviorNote::forStudent($student->id)
            ->where('is_positive', true)
            ->latest('occurred_on')
            ->take(3)
            ->get(['title', 'note', 'type', 'occurred_on']);

        $allScored = $student->assessments()
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->get()
            ->filter(fn (Assessment $a) => (float) $a->max_score > 0);

        $overallMax = (float) $allScored->sum('max_score');
        $overallPct = $overallMax > 0 ? (float) $allScored->sum('score') / $overallMax * 100 : 0.0;

        return view('print.report-card', [
            'school' => config('school'),
            'student' => $student,
            'class' => $student->class,
            'term' => $term,
            'attendance' => $attendance,
            'subjects' => $subjects,
            'remarks' => $remarks,
            'position' => $position === false ? null : $position + 1,
            'classSize' => $classAverages->count(),
            'overall' => round($overallPct, 1),
            'grade' => $this->grade($overallPct),
        ]);
    }

    /** Only the class teacher, leadership, and the child's own parents. */
    protected function authorizeAccess(Request $request, Student $student): void
    {
        $user = $request->user();

        if ($user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool])) {
            return;
        }

        if ($user->isParent() && $student->parents()->where('users.id', $user->id)->exists()) {
            return;
        }

        if ($student->class?->teacher_id === $user->id) {
            return;
        }

        abort(403, 'You are not allowed to view this report.');
    }

    /** Grading bands printed on the report. */
    protected function grade(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'A — Excellent',
            $percentage >= 75 => 'B — Very Good',
            $percentage >= 60 => 'C — Good',
            $percentage >= 50 => 'D — Satisfactory',
            default => 'E — Needs Support',
        };
    }
}

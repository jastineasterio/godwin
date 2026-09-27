<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DAILY ATTENDANCE MARKING — one screen, quick mobile toggles,
 * instant persistence with a per-student/per-day duplicate guard.
 */
class AttendanceController extends Controller
{
    /**
     * The marking screen.
     *
     * Teachers only ever see THEIR OWN classes (strict data isolation);
     * the Head of School and Administrator see every active class.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $date = $request->query('date', now()->toDateString());

        $classes = $this->visibleClasses($user);
        $class = $classes->firstWhere('id', (int) $request->query('class_id')) ?? $classes->first();

        $students = $class
            ? Student::where('class_id', $class->id)->where('status', 'active')
                ->orderBy('first_name')
                ->get(['id', 'reg_no', 'first_name', 'last_name'])
            : collect();

        $existing = $class
            ? Attendance::forClassDate($class->id, $date)->get()->keyBy('student_id')
            : collect();

        return Inertia::render('Attendance/Index', [
            'date' => $date,
            'classes' => $classes->map(fn (SchoolClass $c) => [
                'id' => $c->id, 'name' => $c->name, 'code' => $c->code,
            ]),
            'activeClassId' => $class?->id,
            'roster' => $students->map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->full_name,
                'reg_no' => $s->reg_no,
                'status' => $existing[$s->id]->status->value ?? 'present',
                'already_marked' => $existing->has($s->id),
            ]),
            'summary' => [
                'total' => $students->count(),
                'marked' => $existing->count(),
                'present' => $existing->filter(fn ($a) => $a->status->countsAsPresent())->count(),
                'absent' => $existing->filter(fn ($a) => $a->status === AttendanceStatus::Absent)->count(),
                'late' => $existing->filter(fn ($a) => $a->status === AttendanceStatus::Late)->count(),
            ],
            'statuses' => collect(AttendanceStatus::cases())->map(fn ($s) => [
                'value' => $s->value, 'label' => $s->label(),
            ]),
        ]);
    }

    /**
     * Persist the whole register in one request.
     *
     * Each mark is an upsert keyed on (student_id, date) — the unique index
     * is the real duplicate guard, so tapping "Save" twice is harmless.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'class_id' => ['required', 'exists:classes,id'],
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.student_id' => ['required', 'exists:students,id'],
            'marks.*.status' => ['required', Rule::in(AttendanceStatus::values())],
            'marks.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        // Teachers may only mark their own classes
        abort_unless(
            $user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool])
            || $user->classesTaught()->where('classes.id', $data['class_id'])->exists(),
            403,
            'You are not assigned to that class.'
        );

        // Every marked student must actually belong to the submitted class
        $submitted = array_unique(array_column($data['marks'], 'student_id'));

        $validIds = Student::where('class_id', $data['class_id'])
            ->whereIn('id', $submitted)
            ->pluck('id');

        if ($validIds->count() !== count($submitted)) {
            return back()->withErrors([
                'marks' => 'The register contains students who are not in this class.',
            ]);
        }

        $saved = DB::transaction(function () use ($data, $user) {
            $count = 0;

            foreach ($data['marks'] as $mark) {
                // Look the day up with whereDate() so the comparison is correct
                // regardless of how the driver stores DATE values.
                $existing = Attendance::where('student_id', $mark['student_id'])
                    ->whereDate('date', $data['date'])
                    ->first();

                $attributes = [
                    'status' => $mark['status'],
                    'remarks' => $mark['remarks'] ?? null,
                ];

                if ($existing) {
                    $existing->update($attributes);
                } else {
                    // marked_by keeps the FIRST teacher who recorded the day
                    Attendance::create([
                        ...$attributes,
                        'student_id' => $mark['student_id'],
                        'date' => $data['date'],
                        'marked_by' => $user->id,
                    ]);
                }

                $count++;
            }

            return $count;
        });

        AuditLog::record($user, 'attendance.marked', "Marked {$saved} students for {$data['date']}");

        return back()->with('success', "Attendance saved — {$saved} student(s) recorded.");
    }

    /** Classes the signed-in user is allowed to mark. */
    protected function visibleClasses(User $user)
    {
        if ($user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool])) {
            return SchoolClass::active()->ordered()->get(['id', 'name', 'code']);
        }

        return SchoolClass::active()
            ->where('teacher_id', $user->id)
            ->ordered()
            ->get(['id', 'name', 'code']);
    }
}

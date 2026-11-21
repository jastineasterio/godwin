<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TimetableController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $classId = (int) ($request->query('class_id', 0));

        $query = Timetable::query()
            ->with(['schoolClass:id,name,code', 'subject:id,name,code', 'teacher:id,name']);

        if ($user->isParent()) {
            $children = $user->students()->get(['students.id', 'students.class_id']);
            $childIds = $children->pluck('id')->all();
            $classIds = $children->pluck('class_id')->filter()->all();

            if (empty($classIds)) {
                return Inertia::render('Timetable/Index', [
                    'timetables' => [],
                    'classes' => [],
                    'children' => [],
                    'activeClassId' => null,
                    'canManage' => false,
                    'days' => [],
                ]);
            }

            if ($classId && in_array($classId, $classIds, true)) {
                $query->where('class_id', $classId);
            } else {
                $query->whereIn('class_id', $classIds);
            }

            $childrenData = $children->map(fn ($child) => [
                'id' => $child->id,
                'name' => Student::find($child->id)?->full_name ?? 'Unknown',
                'class_id' => $child->class_id,
                'class_name' => SchoolClass::find($child->class_id)?->name ?? '—',
            ])->all();

            return Inertia::render('Timetable/Index', [
                'timetables' => $query->orderBy('day')->orderBy('start_time')->get()->map(fn ($t) => $this->formatTimetable($t))->all(),
                'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code'])->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                ])->all(),
                'children' => $childrenData,
                'activeClassId' => ($classId && in_array($classId, $classIds, true)) ? $classId : null,
                'canManage' => false,
                'days' => collect(\App\Enums\TimetableDay::cases())->map(fn ($d) => ['value' => $d->value, 'label' => $d->label()])->values()->all(),
            ]);
        }

        if ($user->hasAnyRole([UserRole::Teacher])) {
            $query->where('teacher_id', $user->id);
        }

        if ($classId && !$user->hasAnyRole([UserRole::Teacher])) {
            $query->where('class_id', $classId);
        }

        $timetables = $query->orderBy('day')->orderBy('start_time')->get()->map(fn ($t) => $this->formatTimetable($t))->all();
        $classes = SchoolClass::active()->ordered()->get(['id', 'name', 'code'])->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->code,
        ])->all();

        $canManage = $user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool]);

        return Inertia::render('Timetable/Index', [
            'timetables' => $timetables,
            'classes' => $classes,
            'children' => [],
            'activeClassId' => $classId ?: null,
            'canManage' => $canManage,
            'days' => collect(\App\Enums\TimetableDay::cases())->map(fn ($d) => ['value' => $d->value, 'label' => $d->label()])->values()->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeManage($request->user());

        return Inertia::render('Timetable/Create', [
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code'])->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name . ' (' . $c->code . ')',
            ])->all(),
            'subjects' => Subject::active()->ordered()->get(['id', 'name', 'code'])->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name . ' (' . $s->code . ')',
            ])->all(),
            'teachers' => User::role(UserRole::Teacher)->active()->get(['id', 'name'])->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->all(),
            'days' => collect(\App\Enums\TimetableDay::cases())->map(fn ($d) => ['value' => $d->value, 'label' => $d->label()])->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request->user());

        $validated = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'day' => ['required', Rule::in(\App\Enums\TimetableDay::values())],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:50'],
        ]);

        $this->checkConflict($validated);

        $timetable = Timetable::create($validated);

        return back()->with('success', 'Timetable entry added successfully.');
    }

    public function edit(Request $request, Timetable $timetable): Response
    {
        $this->authorizeManage($request->user());

        return Inertia::render('Timetable/Edit', [
            'timetable' => $this->formatTimetable($timetable),
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code'])->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name . ' (' . $c->code . ')',
            ])->all(),
            'subjects' => Subject::active()->ordered()->get(['id', 'name', 'code'])->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name . ' (' . $s->code . ')',
            ])->all(),
            'teachers' => User::role(UserRole::Teacher)->active()->get(['id', 'name'])->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->all(),
            'days' => collect(\App\Enums\TimetableDay::cases())->map(fn ($d) => ['value' => $d->value, 'label' => $d->label()])->values()->all(),
        ]);
    }

    public function update(Request $request, Timetable $timetable): RedirectResponse
    {
        $this->authorizeManage($request->user());

        $validated = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'day' => ['required', Rule::in(\App\Enums\TimetableDay::values())],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:50'],
        ]);

        $this->checkConflict($validated, $timetable->id);

        $timetable->update($validated);

        return back()->with('success', 'Timetable entry updated successfully.');
    }

    public function destroy(Request $request, Timetable $timetable): RedirectResponse
    {
        $this->authorizeManage($request->user());

        $timetable->delete();

        return back()->with('success', 'Timetable entry removed successfully.');
    }

    /* ---------------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------------- */

    private function authorizeManage(User $user): void
    {
        abort_unless(
            $user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool]),
            403,
            'You do not have permission to manage the timetable.'
        );
    }

    private function checkConflict(array $data, ?int $excludeId = null): void
    {
        $query = Timetable::query()
            ->where('class_id', $data['class_id'])
            ->where('day', $data['day'])
            ->where(function ($q) use ($data) {
                $q->where(function ($q2) use ($data) {
                    $q2->where('start_time', '<', $data['end_time'])
                        ->where('end_time', '>', $data['start_time']);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            abort(422, 'This time slot conflicts with an existing timetable entry for the selected class on that day.');
        }

        if (!empty($data['teacher_id'])) {
            $teacherQuery = Timetable::query()
                ->where('teacher_id', $data['teacher_id'])
                ->where('day', $data['day'])
                ->where(function ($q) use ($data) {
                    $q->where(function ($q2) use ($data) {
                        $q2->where('start_time', '<', $data['end_time'])
                            ->where('end_time', '>', $data['start_time']);
                    });
                });

            if ($excludeId) {
                $teacherQuery->where('id', '!=', $excludeId);
            }

            if ($teacherQuery->exists()) {
                abort(422, 'This teacher is already assigned to another lesson during this time on the same day.');
            }
        }
    }

    private function formatTimetable(Timetable $t): array
    {
        return [
            'id' => $t->id,
            'class_id' => $t->class_id,
            'class_name' => $t->schoolClass?->name ?? '—',
            'class_code' => $t->schoolClass?->code ?? '',
            'subject_id' => $t->subject_id,
            'subject_name' => $t->subject?->name ?? '—',
            'subject_code' => $t->subject?->code ?? '',
            'teacher_id' => $t->teacher_id,
            'teacher_name' => $t->teacher?->name ?? '—',
            'day' => $t->day,
            'day_label' => $t->day?->label() ?? ucfirst($t->day?->value ?? ''),
            'start_time' => $t->start_time,
            'end_time' => $t->end_time,
            'room' => $t->room,
        ];
    }
}

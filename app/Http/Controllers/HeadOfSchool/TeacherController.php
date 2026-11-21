<?php

namespace App\Http\Controllers\HeadOfSchool;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TEACHERS MANAGEMENT — Head of School registers teacher accounts (User +
 * role = teacher), views the roster, toggles status, and manages teaching
 * assignments through the existing `class_subject` pivot.
 */
class TeacherController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $query = User::role(UserRole::Teacher)
            ->withCount([
                'subjectsTaught as classes_taught' => fn ($q) => $q->distinct('class_id'),
            ])
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $teachers = $query->through(fn (User $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'email' => $t->email,
            'phone' => $t->phone,
            'status' => $t->status->value,
            'classes_taught' => (int) $t->classes_taught,
            'subjects' => $t->subjectsTaught->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
            ])->all(),
            'created_at' => $t->created_at?->toDateString(),
        ]);

        return Inertia::render('Teachers/Index', [
            'teachers' => $teachers,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        return Inertia::render('Teachers/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(UserStatus::values())],
        ]);

        $teacher = DB::transaction(function () use ($data, $request) {
            $teacher = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => bcrypt($data['password']),
                'role' => UserRole::Teacher,
                'status' => $data['status'],
            ]);

            AuditLog::record(
                $request->user(),
                'teacher.created',
                "Registered teacher account \"{$teacher->name}\" ({$teacher->email})",
                $teacher
            );

            return $teacher;
        });

        return redirect()->route('teachers.index')
            ->with('success', "Teacher \"{$teacher->name}\" registered.");
    }

    public function show(Request $request, User $teacher): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        abort_if($teacher->role !== UserRole::Teacher, 404);

        $teacher->load(['subjectsTaught:id,name,code', 'classesTaught:id,name,code,teacher_id']);

        return Inertia::render('Teachers/Show', [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'phone' => $teacher->phone,
                'role' => $teacher->role->value,
                'status' => $teacher->status->value,
                'created_at' => $teacher->created_at?->toDateString(),
                'classes' => $teacher->classesTaught->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'is_class_teacher' => (bool) ($c->teacher_id === $teacher->id),
                ])->all(),
                'subjects' => $teacher->subjectsTaught->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'code' => $s->code,
                ])->all(),
            ],
        ]);
    }

    public function edit(Request $request, User $teacher): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        abort_if($teacher->role !== UserRole::Teacher, 404);

        return Inertia::render('Teachers/Edit', [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'phone' => $teacher->phone,
                'status' => $teacher->status->value,
            ],
        ]);
    }

    public function update(Request $request, User $teacher): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        abort_if($teacher->role !== UserRole::Teacher, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($teacher->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(UserStatus::values())],
        ]);

        $teacher->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ]);

        AuditLog::record(
            $request->user(),
            'teacher.updated',
            "Updated teacher \"{$teacher->name}\"",
            $teacher
        );

        return redirect()->route('teachers.index')
            ->with('success', "Teacher \"{$teacher->name}\" updated.");
    }

    public function updateStatus(Request $request, User $teacher): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        abort_if($teacher->role !== UserRole::Teacher, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(UserStatus::values())],
        ]);

        $teacher->update(['status' => $data['status']]);

        AuditLog::record(
            $request->user(),
            'teacher.status_changed',
            "Changed teacher \"{$teacher->name}\" status to {$data['status']}",
            $teacher
        );

        return back()->with('success', "Teacher status updated to {$data['status']}.");
    }

    public function destroy(Request $request, User $teacher): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        abort_if($teacher->role !== UserRole::Teacher, 404);

        // Protect history: soft-delete so assignments and records remain.
        if ($teacher->classesTaught()->exists() || $teacher->subjectsTaught()->exists()) {
            return back()->with('error',
                "Cannot remove \"{$teacher->name}\" — they have teaching assignments. Deactivate the account instead.");
        }

        $name = $teacher->name;
        $teacher->delete();

        AuditLog::record(
            $request->user(),
            'teacher.deleted',
            "Removed teacher \"{$name}\"",
            $teacher
        );

        return redirect()->route('teachers.index')
            ->with('success', "Teacher \"{$name}\" removed.");
    }
}

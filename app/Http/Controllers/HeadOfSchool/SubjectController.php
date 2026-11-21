<?php

namespace App\Http\Controllers\HeadOfSchool;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SUBJECTS MANAGEMENT — Head of School registers subjects, assigns them to
 * classes and keeps them active/inactive. Historical results are protected.
 */
class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $query = Subject::query()
            ->withCount(['classes' => fn ($q) => $q->where('classes.is_active', true)])
            ->with(['classes:id,name,code']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($classId = $request->query('class_id')) {
            $query->whereHas('classes', fn ($q) => $q->where('classes.id', $classId));
        }

        if ($status = $request->query('status')) {
            $query->where('is_active', $status === 'active');
        }

        $subjects = $query->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Subject $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'description' => $s->description,
                'is_active' => $s->is_active,
                'class_count' => $s->classes_count,
                'classes' => $s->classes->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'code' => $c->code,
                ])->all(),
            ]);

        return Inertia::render('Subjects/Index', [
            'subjects' => $subjects,
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code', 'level']),
            'filters' => $request->only('search', 'class_id', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        return Inertia::render('Subjects/Create', [
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code', 'level']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('subjects', 'code')],
            'description' => ['nullable', 'string', 'max:1000'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['required', 'exists:classes,id'],
            'teacher_ids' => ['nullable', 'array'],
            'teacher_ids.*' => ['required', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $subject = DB::transaction(function () use ($data, $request) {
            $subject = Subject::create([
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => (bool) $data['is_active'],
            ]);

            $this->syncAssignments($subject, $data['class_ids'] ?? [], $data['teacher_ids'] ?? []);

            AuditLog::record(
                $request->user(),
                'subject.created',
                "Registered subject \"{$subject->name}\"",
                $subject
            );

            return $subject;
        });

        return redirect()->route('subjects.index')
            ->with('success', "Subject \"{$subject->name}\" registered.");
    }

    public function edit(Request $request, Subject $subject): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        return Inertia::render('Subjects/Edit', [
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'description' => $subject->description,
                'is_active' => $subject->is_active,
            ],
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code', 'level']),
            'selectedClasses' => $subject->classes->pluck('id')->all(),
            'selectedTeachers' => $subject->classes->map(fn ($c) => [
                'class_id' => $c->id,
                'teacher_id' => $c->pivot->teacher_id,
            ])->filter(fn ($t) => $t['teacher_id'])->all(),
        ]);
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('subjects', 'code')->ignore($subject->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['required', 'exists:classes,id'],
            'teacher_ids' => ['nullable', 'array'],
            'teacher_ids.*' => ['required', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($subject, $data, $request) {
            $subject->update([
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => (bool) $data['is_active'],
            ]);

            $this->syncAssignments($subject, $data['class_ids'] ?? [], $data['teacher_ids'] ?? []);

            AuditLog::record(
                $request->user(),
                'subject.updated',
                "Updated subject \"{$subject->name}\"",
                $subject
            );
        });

        return redirect()->route('subjects.index')
            ->with('success', "Subject \"{$subject->name}\" updated.");
    }

    public function destroy(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        // Protect historical academic records: refuse deletion if results exist.
        if ($subject->assessments()->exists()) {
            return back()->with('error',
                "Cannot delete \"{$subject->name}\" — it has historical assessment records. Deactivate it instead.");
        }

        $name = $subject->name;
        $subject->delete();

        AuditLog::record(
            $request->user(),
            'subject.deleted',
            "Deleted subject \"{$name}\""
        );

        return redirect()->route('subjects.index')
            ->with('success', "Subject \"{$name}\" removed.");
    }

    /** Sync class-subject-teacher assignments (creates/updates pivot rows). */
    protected function syncAssignments(Subject $subject, array $classIds, array $teacherIds): void
    {
        $teacherByClass = [];
        foreach ($teacherIds as $assignment) {
            $teacherByClass[(int) $assignment['class_id']] = (int) $assignment['teacher_id'];
        }

        $existing = $subject->classes->keyBy('id');

        foreach ($classIds as $classId) {
            $teacherId = $teacherByClass[$classId] ?? null;

            if (isset($existing[$classId])) {
                // Update teacher for existing class-subject link.
                $existing[$classId]->pivot->update(['teacher_id' => $teacherId]);
            } else {
                $subject->classes()->attach($classId, ['teacher_id' => $teacherId]);
            }
        }

        // Remove class-subject links that are no longer selected.
        foreach ($existing->keys() as $classId) {
            if (! in_array($classId, $classIds, true)) {
                $subject->classes()->detach($classId);
            }
        }
    }
}

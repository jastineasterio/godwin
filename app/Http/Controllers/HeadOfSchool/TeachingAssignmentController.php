<?php

namespace App\Http\Controllers\HeadOfSchool;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TEACHING ASSIGNMENTS — Head of School assigns teachers to subjects within
 * classes. Reuses the existing `class_subject` pivot (class_id, subject_id,
 * teacher_id) — no duplicate table is created.
 *
 * Scope: single-school installation. Users, classes and subjects all belong to
 * the one school, so tenant isolation is by construction. Every lookup below
 * resolves against the current school's records and the controller guards on
 * role + active status + record existence.
 */
class TeachingAssignmentController extends Controller
{
    /** List all teaching assignments with optional search/filter. */
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $query = DB::table('class_subject')
            ->join('users', 'users.id', '=', 'class_subject.teacher_id')
            ->join('classes', 'classes.id', '=', 'class_subject.class_id')
            ->join('subjects', 'subjects.id', '=', 'class_subject.subject_id')
            ->select(
                'class_subject.id',
                'class_subject.teacher_id',
                'class_subject.class_id',
                'class_subject.subject_id',
                'users.name as teacher_name',
                'users.status as teacher_status',
                'classes.name as class_name',
                'classes.code as class_code',
                'classes.is_active as class_active',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
                'subjects.is_active as subject_active',
            );

        if ($teacher = $request->query('teacher')) {
            $query->where('users.name', 'like', "%{$teacher}%");
        }
        if ($class = $request->query('class')) {
            $query->where('classes.name', 'like', "%{$class}%");
        }
        if ($subject = $request->query('subject')) {
            $query->where('subjects.name', 'like', "%{$subject}%");
        }

        $assignments = $query->orderBy('classes.name')->orderBy('subjects.name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn ($row) => [
                'id' => $row->id,
                'teacher' => [
                    'id' => $row->teacher_id,
                    'name' => $row->teacher_name,
                    'status' => $row->teacher_status,
                ],
                'class' => [
                    'id' => $row->class_id,
                    'name' => $row->class_name,
                    'code' => $row->class_code,
                    'is_active' => (bool) $row->class_active,
                ],
                'subject' => [
                    'id' => $row->subject_id,
                    'name' => $row->subject_name,
                    'code' => $row->subject_code,
                    'is_active' => (bool) $row->subject_active,
                ],
                'status' => $row->teacher_id ? 'active' : 'unassigned',
            ]);

        return Inertia::render('TeachingAssignments/Index', [
            'assignments' => $assignments,
            'teachers' => User::role(UserRole::Teacher)->active()->orderBy('name')->get(['id', 'name']),
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code']),
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'classSubjects' => DB::table('class_subject')
                ->select('class_id', 'subject_id', 'teacher_id')
                ->get()
                ->map(fn ($r) => ['class_id' => $r->class_id, 'subject_id' => $r->subject_id, 'teacher_id' => $r->teacher_id])
                ->all(),
            'filters' => $request->only('teacher', 'class', 'subject'),
        ]);
    }

    /** Assign a teacher to a subject within a class (create only). */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:users,id'],
        ]);

        $class = SchoolClass::find($data['class_id']);
        if (! $class || ! $class->is_active) {
            return back()->with('error', 'Select an active class.');
        }

        $subject = Subject::find($data['subject_id']);
        if (! $subject || ! $subject->is_active) {
            return back()->with('error', 'Select an active subject.');
        }

        // Subject must be allocated to the selected class.
        if (! $class->subjects()->where('subjects.id', $data['subject_id'])->exists()) {
            return back()->with('error', 'This subject is not allocated to the selected class.');
        }

        $teacher = User::find($data['teacher_id']);
        if (! $teacher || $teacher->role !== UserRole::Teacher || ! $teacher->isActive()) {
            return back()->with('error', 'Select an active teacher.');
        }

        // One subject in a class has only one assigned teacher; use update to change.
        $already = DB::table('class_subject')
            ->where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->whereNotNull('teacher_id')
            ->first();

        if ($already) {
            return back()->with('error',
                'This subject already has an assigned teacher in this class. Use "Change teacher" to update it.');
        }

        DB::table('class_subject')->updateOrInsert(
            ['class_id' => $data['class_id'], 'subject_id' => $data['subject_id']],
            ['teacher_id' => $data['teacher_id'], 'updated_at' => now()]
        );

        return back()->with('success', "Assigned {$teacher->name} to {$subject->name} in {$class->name}.");
    }

    /** Change the teacher of an existing assignment (update the pivot row). */
    public function update(Request $request, $assignment): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $row = DB::table('class_subject')->where('id', $assignment)->first();
        abort_unless($row, 404, 'Assignment not found.');

        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
        ]);

        $class = SchoolClass::find($row->class_id);
        $subject = Subject::find($row->subject_id);
        if (! $class || ! $class->is_active) {
            return back()->with('error', 'Class is not active.');
        }
        if (! $subject || ! $subject->is_active) {
            return back()->with('error', 'Subject is not active.');
        }
        if (! $class->subjects()->where('subjects.id', $row->subject_id)->exists()) {
            return back()->with('error', 'Subject is not allocated to this class.');
        }

        $teacher = User::find($data['teacher_id']);
        if (! $teacher || $teacher->role !== UserRole::Teacher || ! $teacher->isActive()) {
            return back()->with('error', 'Select an active teacher.');
        }

        DB::table('class_subject')->where('id', $assignment)->update([
            'teacher_id' => $data['teacher_id'],
            'updated_at' => now(),
        ]);

        return back()->with('success', "Updated {$subject->name} in {$class->name} to {$teacher->name}.");
    }

    /** Remove a teacher assignment (unassign the teacher; keeps historical marks). */
    public function destroy($assignment): RedirectResponse
    {
        abort_unless(
            request()->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $row = DB::table('class_subject')->where('id', $assignment)->first();
        abort_unless($row, 404, 'Assignment not found.');

        DB::table('class_subject')->where('id', $assignment)->update([
            'teacher_id' => null,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Teacher assignment removed.');
    }
}

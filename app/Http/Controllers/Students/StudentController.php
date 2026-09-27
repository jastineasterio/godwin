<?php

namespace App\Http\Controllers\Students;

use App\Enums\RelationshipType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * STUDENT MANAGEMENT â€” registration, class assignment, status changes
 * and parent/guardian linking (Admin & Head of School).
 */
class StudentController extends Controller
{
    /** Paginated roster with search + filters. */
    public function index(Request $request): Response
    {
        $students = Student::query()
            ->with('class:id,name,code')
            ->search($request->query('search'))
            ->when($request->query('class_id'), fn ($q, $id) => $q->where('class_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('first_name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Student $s) => [
                'id' => $s->id,
                'reg_no' => $s->reg_no,
                'name' => $s->full_name,
                'gender' => $s->gender->value,
                'class' => $s->class?->name,
                'class_id' => $s->class_id,
                'status' => $s->status->value,
                'dob' => $s->dob?->toDateString(),
            ]);

        return Inertia::render('Students/Index', [
            'students' => $students,
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code']),
            'filters' => $request->only('search', 'class_id', 'status'),
            'statuses' => collect(StudentStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    /** Registration form. */
    public function create(): Response
    {
        return Inertia::render('Students/Create', [
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code', 'level', 'capacity']),
            'statuses' => collect(StudentStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'nextRegNo' => $this->nextRegNumber(),
        ]);
    }

    /** Persist a new student and (optionally) create + link a parent account. */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $student = DB::transaction(function () use ($request) {
            $student = Student::create($request->validated());

            // Optional inline parent creation (account + pivot in one step)
            if ($request->filled('parent_name')) {
                $parent = $this->findOrCreateParent($request);

                $parent->students()->attach($student->id, [
                    'relationship_type' => $request->input('relationship_type', 'guardian'),
                    'is_primary_contact' => true,
                ]);
            }

            return $student;
        });

        AuditLog::record($request->user(), 'student.created', "Registered {$student->full_name}", $student);

        return redirect()
            ->route('students.show', $student)
            ->with('success', "{$student->full_name} has been registered successfully.");
    }

    /** Full student profile. */
    public function show(Student $student): Response
    {
        $student->load(['class:id,name,code,level', 'parents:id,name,email,phone,status']);

        return Inertia::render('Students/Show', [
            'student' => [
                ...$student->only([
                    'id', 'reg_no', 'first_name', 'last_name', 'other_name', 'gender',
                    'dob', 'class_id', 'blood_group', 'medical_notes', 'admission_date',
                    'previous_school', 'status', 'notes', 'photo_path',
                ]),
                'full_name' => $student->full_name,
                'age' => $student->age,
                'class' => $student->class?->name,
                'status_label' => $student->status->label(),
            ],
            'parents' => $student->parents()->get()->map(fn (User $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'email' => $p->email,
                'phone' => $p->phone,
                'relationship' => $p->pivot->relationship_type,
                'is_primary' => (bool) $p->pivot->is_primary_contact,
            ]),
            'availableParents' => User::role(UserRole::Parent)
                ->whereNotIn('users.id', fn ($q) => $q->select('parent_id')->from('parent_student')
                    ->where('student_id', $student->id))
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone'])
                ->map(fn (User $p) => ['id' => $p->id, 'name' => $p->name, 'phone' => $p->phone]),
            'relationships' => collect(RelationshipType::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
            'stats' => [
                'attendance_rate' => $student->attendanceRate(),
                'assessments' => $student->assessments()->count(),
                'invoices' => $student->invoices()->count(),
                'balance' => max(0, (float) $student->invoices()->outstanding()->sum('total_amount')
                    - (float) $student->invoices()->outstanding()->sum('amount_paid')),
            ],
            'recentAssessments' => $student->assessments()
                ->with('subject:id,name')
                ->latest('obtained_at')->take(5)
                ->get(['id', 'title', 'score', 'max_score', 'obtained_at', 'type'])
                ->map(fn (Assessment $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'subject' => $a->subject?->name,
                    'percentage' => $a->percentage,
                    'date' => $a->obtained_at?->toDateString(),
                ]),
        ]);
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('Students/Edit', [
            'student' => [
                ...$student->only([
                    'id', 'reg_no', 'first_name', 'last_name', 'other_name', 'gender',
                    'dob', 'class_id', 'blood_group', 'medical_notes', 'admission_date',
                    'previous_school', 'status', 'notes',
                ]),
                'full_name' => $student->full_name,
            ],
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code', 'level', 'capacity']),
            'statuses' => collect(StudentStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $before = $student->only(['class_id', 'status', 'medical_notes']);

        $student->update($request->validated());

        AuditLog::record($request->user(), 'student.updated', "Updated {$student->full_name}", $student, [
            'before' => $before,
            'after' => $student->only(['class_id', 'status', 'medical_notes']),
        ]);

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Student record updated successfully.');
    }

    /** Soft delete â€” the student's history stays intact for parents and audit. */
    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $student->delete();

        AuditLog::record($request->user(), 'student.deleted', "Removed {$student->full_name}", $student);

        return redirect()->route('students.index')->with('success', 'Student record removed.');
    }

    /* ------------------------------------------------------------------ */
    /* Parent / guardian linking */
    /* ------------------------------------------------------------------ */

    /** Link an existing parent account (or create one) to a student. */
    public function linkParent(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:users,id'],
            'parent_name' => ['nullable', 'required_without:parent_id', 'string', 'max:150'],
            'parent_phone' => ['nullable', 'required_without:parent_id', 'string', 'max:20'],
            'parent_email' => ['nullable', 'email', 'max:150'],
            'relationship_type' => ['required', Rule::in(RelationshipType::values())],
            'is_primary_contact' => ['nullable', 'boolean'],
        ]);

        $parent = $data['parent_id']
            ? User::role(UserRole::Parent)->findOrFail($data['parent_id'])
            : $this->findOrCreateParent($request);

        $isPrimary = (bool) ($data['is_primary_contact'] ?? false);

        $student->parents()->syncWithoutDetaching([$parent->id => [
            'relationship_type' => $data['relationship_type'],
            'is_primary_contact' => $isPrimary,
        ]]);

        // A child can only have ONE primary contact
        if ($isPrimary) {
            DB::table('parent_student')
                ->where('student_id', $student->id)
                ->where('parent_id', '!=', $parent->id)
                ->update(['is_primary_contact' => false]);
        }

        return back()->with('success', "{$parent->name} is now linked to {$student->full_name}.");
    }

    /** Unlink a parent from a student. */
    public function unlinkParent(Student $student, User $parent): RedirectResponse
    {
        abort_unless($parent->role === UserRole::Parent, 404);

        $student->parents()->detach($parent->id);

        return back()->with('success', "{$parent->name} was unlinked from {$student->full_name}.");
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    /** Next registration number: GWD-2026-0001. */
    protected function nextRegNumber(): string
    {
        $year = now()->year;
        $count = Student::withTrashed()->whereYear('created_at', $year)->count() + 1;

        return sprintf('GWD-%d-%04d', $year, $count);
    }

    /** Re-use a parent account with the same phone, otherwise create one. */
    protected function findOrCreateParent(Request $request): User
    {
        $phone = $request->input('parent_phone');

        $existing = User::role(UserRole::Parent)->where('phone', $phone)->first();

        if ($existing) {
            return $existing;
        }

        $name = $request->input('parent_name', 'Parent');

        return User::create([
            'name' => $name,
            'email' => $request->input('parent_email')
                ?: Str::slug($name).'.'.substr(md5($phone), 0, 4).'@godwin.ac.tz',
            'phone' => $phone,
            // Random password â€” the office issues/reset credentials on request
            'password' => Str::random(32),
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
    }
}

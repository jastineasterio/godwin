<?php

namespace App\Http\Controllers\Classes;

use App\Enums\ClassLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchoolClassRequest;
use App\Http\Requests\UpdateSchoolClassRequest;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\FeeStructure;
use App\Models\Homework;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SCHOOL CLASS MANAGEMENT — Admin & Head of School.
 *
 * Create, view, edit and safely archive/delete classes. Permanent deletion
 * is blocked whenever the class has dependent records (students, attendance,
 * timetable, assessments, invoices, homework, fee structures or subjects).
 * Archive/deactivation (is_active) is always offered as the safe alternative.
 */
class SchoolClassController extends Controller
{
    /** Paginated class register with search + level/status filters and KPI stats. */
    public function index(Request $request): Response
    {
        $query = SchoolClass::query()
            ->withCount('students')
            ->with('teacher:id,name')
            ->when($request->filled('search'), fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            }))
            ->when($request->filled('level'), fn ($q, $level) => $q->where('level', $level))
            ->when($request->filled('status'), fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->ordered();

        $classes = $query->paginate(10)->withQueryString()->through(fn (SchoolClass $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->code,
            'level' => $c->level?->label(),
            'teacher' => $c->teacher?->name ?? null,
            'students' => (int) $c->students_count,
            'capacity' => $c->capacity,
            'is_active' => $c->is_active,
            'status' => $c->is_active ? 'Active' : 'Inactive',
            'utilization' => $c->capacity ? (int) round(($c->students_count / $c->capacity) * 100) : 0,
        ]);

        $activeClassIds = SchoolClass::active()->pluck('id');

        $stats = [
            ['label' => 'Total Classes', 'value' => SchoolClass::count(), 'accent' => 'primary'],
            ['label' => 'Active Classes', 'value' => SchoolClass::active()->count(), 'accent' => 'secondary'],
            ['label' => 'Total Students Assigned', 'value' => Student::whereNotNull('class_id')->count(), 'accent' => 'accent'],
            ['label' => 'Available Capacity', 'value' => max(0, SchoolClass::active()->sum('capacity')
                - Student::whereIn('class_id', $activeClassIds)->count()), 'accent' => 'primary'],
        ];

        return Inertia::render('Classes/Index', [
            'classes' => $classes,
            'teachers' => User::role(UserRole::Teacher)->active()->orderBy('name')->get(['id', 'name']),
            'levels' => collect(ClassLevel::cases())->map(fn ($l) => ['value' => $l->value, 'label' => $l->label()]),
            'filters' => $request->only(['search', 'level', 'status']),
            'stats' => $stats,
        ]);
    }

    /** Create class form. */
    public function create(): Response
    {
        return Inertia::render('Classes/Create', [
            'teachers' => User::role(UserRole::Teacher)->active()->orderBy('name')->get(['id', 'name']),
            'levels' => collect(ClassLevel::cases())->map(fn ($l) => ['value' => $l->value, 'label' => $l->label()]),
        ]);
    }

    /** Persist a new class. */
    public function store(StoreSchoolClassRequest $request): RedirectResponse
    {
        $class = DB::transaction(function () use ($request) {
            $class = SchoolClass::create($request->validated());

            AuditLog::record($request->user(), 'class.created', "Created class {$class->name}", $class);

            return $class;
        });

        return redirect()->route('classes.index')
            ->with('success', "Class '{$class->name}' ({$class->code}) created successfully.");
    }

    /** Class details + roster. */
    public function show(SchoolClass $class): Response
    {
        $class->load(['teacher:id,name', 'students' => fn ($q) => $q->orderBy('first_name')->orderBy('last_name')]);

        $studentCount = $class->students->count();
        $capacity = (int) $class->capacity;
        $utilization = $capacity ? (int) round(($studentCount / $capacity) * 100) : 0;

        return Inertia::render('Classes/Show', [
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'level' => $class->level?->label(),
                'teacher' => $class->teacher?->name ?? null,
                'teacher_id' => $class->teacher_id,
                'capacity' => $capacity,
                'description' => $class->description,
                'is_active' => $class->is_active,
                'status' => $class->is_active ? 'Active' : 'Inactive',
                'student_count' => $studentCount,
                'utilization' => $utilization,
                'created_at' => $class->created_at?->toDateString(),
            ],
            'students' => $class->students->map(fn (Student $s) => [
                'id' => $s->id,
                'reg_no' => $s->reg_no,
                'name' => $s->full_name,
                'gender' => $s->gender->value,
                'status' => $s->status->value,
            ]),
        ]);
    }

    /** Edit class form. */
    public function edit(SchoolClass $class): Response
    {
        return Inertia::render('Classes/Edit', [
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'level' => $class->level->value,
                'teacher_id' => $class->teacher_id,
                'capacity' => $class->capacity,
                'description' => $class->description,
                'is_active' => $class->is_active,
            ],
            'teachers' => User::role(UserRole::Teacher)->active()->orderBy('name')->get(['id', 'name']),
            'levels' => collect(ClassLevel::cases())->map(fn ($l) => ['value' => $l->value, 'label' => $l->label()]),
        ]);
    }

    /** Update an existing class. Student/teacher/subject/timetable links are preserved. */
    public function update(UpdateSchoolClassRequest $request, SchoolClass $class): RedirectResponse
    {
        $class->update($request->validated());

        AuditLog::record($request->user(), 'class.updated', "Updated class {$class->name}", $class, [
            'after' => $class->only(['name', 'code', 'level', 'teacher_id', 'capacity', 'description', 'is_active']),
        ]);

        return redirect()->route('classes.show', $class)
            ->with('success', "Class '{$class->name}' updated successfully.");
    }

    /** Permanently delete a class — blocked when dependent records exist. */
    public function destroy(Request $request, SchoolClass $class): RedirectResponse
    {
        $count = $this->dependentCount($class);

        if ($count > 0) {
            return back()->with('error', "Cannot delete '{$class->name}': it has {$count} dependent record(s)"
                . " (students, attendance, timetable, assessments, invoices, homework or fee records)."
                . " Archive the class instead.")
                ->with('archive_class_id', $class->id);
        }

        DB::transaction(function () use ($class, $request) {
            $class->forceDelete();

            AuditLog::record($request->user(), 'class.deleted', "Permanently deleted class {$class->name}", $class);
        });

        return redirect()->route('classes.index')
            ->with('success', "Class '{$class->name}' deleted permanently.");
    }

    /** Archive / deactivate a class (safe alternative to permanent deletion). */
    public function archive(Request $request, SchoolClass $class): RedirectResponse
    {
        $class->update(['is_active' => false]);

        AuditLog::record($request->user(), 'class.archived', "Archived class {$class->name}", $class);

        return redirect()->route('classes.index')
            ->with('success', "Class '{$class->name}' archived (deactivated).");
    }

    /** Count every record that depends on this class existing. */
    private function dependentCount(SchoolClass $class): int
    {
        $studentIds = Student::withTrashed()->where('class_id', $class->id)->pluck('id');

        return (int) (
            $studentIds->count()                                           // enrolled students
            + Timetable::forClass($class->id)->count()                     // timetable slots
            + Assessment::forClass($class->id)->count()                    // assessments
            + Homework::forClass($class->id)->count()                      // homeworks
            + FeeStructure::where('class_id', $class->id)->count()         // class-scoped fees
            + $class->subjects()->count()                                  // subject allocations
            + ($studentIds->count() > 0 ? Attendance::whereIn('student_id', $studentIds)->count() : 0)
            + ($studentIds->count() > 0 ? Invoice::whereIn('student_id', $studentIds)->count() : 0)
        );
    }
}

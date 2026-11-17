<?php

namespace App\Http\Controllers\Academics;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Examination;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * EXAMINATION MANAGEMENT — Head of School creates and configures examinations.
 * Teachers enter results; Head of School reviews, approves or returns them.
 */
class ExaminationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Examination::query()
            ->with(['academicYear', 'term', 'createdBy:id,name'])
            ->withCount(['classes', 'subjects', 'assessments']);

        if ($user->hasAnyRole([UserRole::HeadOfSchool])) {
            // Head of School sees all examinations
        } elseif ($user->isAdmin()) {
            // Admin sees all
        }

        $examinations = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('Examinations/Index', [
            'examinations' => $examinations->through(fn (Examination $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'academic_year' => $e->academicYear?->name,
                'term' => $e->term?->name,
                'status' => $e->status,
                'description' => $e->description,
                'classes_count' => $e->classes_count,
                'subjects_count' => $e->subjects_count,
                'results_count' => $e->assessments_count,
                'created_by' => $e->createdBy?->name,
                'created_at' => $e->created_at?->toDateString(),
            ]),
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Examinations/Create', [
            'academicYears' => AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']),
            'terms' => Term::with('academicYear')->orderBy('start_date')->get(['id', 'name', 'academic_year_id', 'is_current']),
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code', 'level']),
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['required', 'exists:classes,id'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'exists:subjects,id'],
            'subjects.*.max_score' => ['required', 'numeric', 'gt:0'],
        ]);

        // Validate term belongs to academic year
        $term = Term::findOrFail($data['term_id']);
        if ((int) $term->academic_year_id !== (int) $data['academic_year_id']) {
            throw new \Illuminate\Validation\ValidationException(
                ['term_id' => ['The selected term does not belong to the chosen academic year.']]
            );
        }

        DB::transaction(function () use ($data, $request) {
            $examination = Examination::create([
                'name' => $data['name'],
                'academic_year_id' => $data['academic_year_id'],
                'term_id' => $data['term_id'],
                'status' => 'draft',
                'description' => $data['description'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['class_ids'] as $classId) {
                $examination->classes()->attach($classId);
            }

            foreach ($data['subjects'] as $subject) {
                $examination->subjects()->attach($subject['subject_id'], [
                    'max_score' => $subject['max_score'],
                ]);
            }

            AuditLog::record(
                $request->user(),
                'examination.created',
                "Created examination \"{$examination->name}\"",
                $examination
            );
        });

        return redirect()->route('examinations.index')
            ->with('success', 'Examination created successfully.');
    }

    public function edit(Request $request, Examination $examination): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        if ($examination->status !== 'draft') {
            return back()->with('error', 'Only draft examinations can be edited.');
        }

        return Inertia::render('Examinations/Edit', [
            'examination' => [
                'id' => $examination->id,
                'name' => $examination->name,
                'academic_year_id' => $examination->academic_year_id,
                'term_id' => $examination->term_id,
                'status' => $examination->status,
                'description' => $examination->description,
            ],
            'academicYears' => AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']),
            'terms' => Term::with('academicYear')->orderBy('start_date')->get(['id', 'name', 'academic_year_id', 'is_current']),
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code', 'level']),
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'selectedClasses' => $examination->classes->pluck('id')->all(),
            'selectedSubjects' => $examination->subjects->map(fn ($s) => [
                'subject_id' => $s->id,
                'max_score' => (float) $s->pivot->max_score,
            ])->all(),
        ]);
    }

    public function update(Request $request, Examination $examination): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        if ($examination->status !== 'draft') {
            return back()->with('error', 'Only draft examinations can be edited.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['required', 'exists:classes,id'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'exists:subjects,id'],
            'subjects.*.max_score' => ['required', 'numeric', 'gt:0'],
        ]);

        $term = Term::findOrFail($data['term_id']);
        if ((int) $term->academic_year_id !== (int) $data['academic_year_id']) {
            throw new \Illuminate\Validation\ValidationException(
                ['term_id' => ['The selected term does not belong to the chosen academic year.']]
            );
        }

        DB::transaction(function () use ($examination, $data, $request) {
            $examination->update([
                'name' => $data['name'],
                'academic_year_id' => $data['academic_year_id'],
                'term_id' => $data['term_id'],
                'description' => $data['description'] ?? null,
            ]);

            $examination->classes()->sync($data['class_ids']);

            $subjectAttachments = [];
            foreach ($data['subjects'] as $subject) {
                $subjectAttachments[$subject['subject_id']] = ['max_score' => $subject['max_score']];
            }
            $examination->subjects()->sync($subjectAttachments);

            AuditLog::record(
                $request->user(),
                'examination.updated',
                "Updated examination \"{$examination->name}\"",
                $examination
            );
        });

        return redirect()->route('examinations.index')
            ->with('success', 'Examination updated successfully.');
    }

    public function destroy(Request $request, Examination $examination): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        if ($examination->status !== 'draft') {
            return back()->with('error', 'Only draft examinations can be deleted.');
        }

        // Prevent deletion if results have been submitted
        $hasResults = Assessment::forExamination($examination->id)->exists();
        if ($hasResults) {
            return back()->with('error', 'Cannot delete an examination that already has result records.');
        }

        $name = $examination->name;
        $examination->delete();

        AuditLog::record(
            $request->user(),
            'examination.deleted',
            "Deleted examination \"{$name}\""
        );

        return redirect()->route('examinations.index')
            ->with('success', 'Examination deleted.');
    }

    public function progress(Request $request, Examination $examination): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $classIds = $examination->classes->pluck('id')->all();
        $subjectIds = $examination->subjects->pluck('id')->all();

        $totalStudents = Student::whereIn('class_id', $classIds)
            ->where('status', 'active')
            ->count();

        $totalExpected = $totalStudents * count($subjectIds);

        $results = Assessment::forExamination($examination->id)
            ->whereIn('class_id', $classIds)
            ->get();

        $submittedCount = $results->where('status', 'submitted')->count();
        $approvedCount = $results->where('status', 'approved')->count();
        $draftCount = $results->where('status', 'draft')->count();
        $returnedCount = $results->where('status', 'returned')->count();

        $completion = $totalExpected > 0 ? round(($submittedCount + $approvedCount) / $totalExpected * 100, 1) : 0;

        // Per-class progress
        $classProgress = $examination->classes->map(function (SchoolClass $class) use ($examination, $subjectIds) {
            $students = $class->students()->where('status', 'active')->count();
            $expected = $students * count($subjectIds);
            $records = Assessment::forExamination($examination->id)
                ->where('class_id', $class->id)
                ->get();

            $submitted = $records->where('status', 'submitted')->count();
            $approved = $records->where('status', 'approved')->count();

            return [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'students' => $students,
                'expected' => $expected,
                'recorded' => $submitted + $approved,
                'completion' => $expected > 0 ? round(($submitted + $approved) / $expected * 100, 1) : 0,
            ];
        })->values();

        // Missing results: students without results for each subject
        $missingResults = [];
        foreach ($examination->classes as $class) {
            foreach ($class->students()->where('status', 'active')->get() as $student) {
                foreach ($examination->subjects as $subject) {
                    $exists = Assessment::forExamination($examination->id)
                        ->where('student_id', $student->id)
                        ->where('subject_id', $subject->id)
                        ->exists();
                    if (! $exists) {
                        $missingResults[] = [
                            'student_id' => $student->id,
                            'student_name' => $student->full_name,
                            'reg_no' => $student->reg_no,
                            'class_name' => $class->name,
                            'subject_name' => $subject->name,
                            'max_score' => (float) $subject->pivot->max_score,
                        ];
                    }
                }
            }
        }

        return Inertia::render('Examinations/Progress', [
            'examination' => [
                'id' => $examination->id,
                'name' => $examination->name,
                'status' => $examination->status,
                'academic_year' => $examination->academicYear?->name,
                'term' => $examination->term?->name,
            ],
            'stats' => [
                'total_students' => $totalStudents,
                'total_expected' => $totalExpected,
                'submitted' => $submittedCount,
                'approved' => $approvedCount,
                'draft' => $draftCount,
                'returned' => $returnedCount,
                'completion' => $completion,
            ],
            'classProgress' => $classProgress,
            'missingResults' => array_values($missingResults),
        ]);
    }

    public function approve(Request $request, Examination $examination): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $updated = Assessment::forExamination($examination->id)
            ->where('status', 'submitted')
            ->where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->update(['status' => 'approved']);

        AuditLog::record(
            $request->user(),
            'examination.results.approved',
            "Approved {$updated} result(s) for examination \"{$examination->name}\"",
            $examination
        );

        return back()->with('success', "Approved {$updated} result(s).");
    }

    public function returnResults(Request $request, Examination $examination): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $updated = Assessment::forExamination($examination->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->update(['status' => 'returned']);

        AuditLog::record(
            $request->user(),
            'examination.results.returned',
            "Returned {$updated} result(s) for examination \"{$examination->name}\"",
            $examination
        );

        return back()->with('success', "Returned {$updated} result(s) for teacher revision.");
    }

    /** Publish a draft examination (Head of School / Admin only). */
    public function publish(Request $request, Examination $examination): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        if (! $examination->transitionableTo('published')) {
            return back()->with('error', 'Only draft examinations can be published.');
        }

        $examination->update(['status' => 'published']);

        AuditLog::record(
            $request->user(),
            'examination.published',
            "Published examination \"{$examination->name}\"",
            $examination
        );

        return back()->with('success', 'Examination published.');
    }

    /** Close an examination (Head of School / Admin only). No further marks accepted. */
    public function close(Request $request, Examination $examination): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        if (! $examination->transitionableTo('closed')) {
            return back()->with('error', 'Only published examinations can be closed.');
        }

        $examination->update(['status' => 'closed']);

        AuditLog::record(
            $request->user(),
            'examination.closed',
            "Closed examination \"{$examination->name}\"",
            $examination
        );

        return back()->with('success', 'Examination closed.');
    }
}

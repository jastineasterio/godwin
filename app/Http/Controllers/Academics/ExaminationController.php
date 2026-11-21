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

    public function edit(Request $request, Examination $examination): Response|RedirectResponse
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

    /** Performance analytics for an examination (Head of School / Admin). */
    public function analytics(Request $request, Examination $examination): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $classIds = $examination->classes->pluck('id')->all();
        $subjectIds = $examination->subjects->pluck('id')->all();

        $results = Assessment::forExamination($examination->id)
            ->whereIn('class_id', $classIds)
            ->whereNotNull('score')
            ->with(['student:id,first_name,last_name,reg_no,class_id', 'subject:id,name,code', 'schoolClass:id,name,code'])
            ->get();

        $scored = $results->filter(fn ($r) => (float) $r->max_score > 0);

        $overallAverage = $scored->isEmpty()
            ? 0
            : round($scored->avg(fn ($r) => $r->percentage), 1);

        $highest = $scored->isEmpty()
            ? null
            : $scored->sortByDesc(fn ($r) => $r->percentage)->first();

        $lowest = $scored->isEmpty()
            ? null
            : $scored->sortBy(fn ($r) => $r->percentage)->first();

        $passCount = $scored->filter(fn ($r) => $r->percentage >= 50)->count();
        $passRate = $scored->isEmpty() ? 0 : round(($passCount / $scored->count()) * 100, 1);

        // Grade distribution (fixed scheme: A 80+, B 70-79, C 60-69, D 50-59, F <50)
        $grades = [
            ['label' => 'A (80–100)', 'min' => 80, 'max' => 100],
            ['label' => 'B (70–79)', 'min' => 70, 'max' => 79],
            ['label' => 'C (60–69)', 'min' => 60, 'max' => 69],
            ['label' => 'D (50–59)', 'min' => 50, 'max' => 59],
            ['label' => 'F (0–49)', 'min' => 0, 'max' => 49],
        ];
        $gradeDistribution = collect($grades)->map(function ($g) use ($scored) {
            $count = $scored->filter(fn ($r) => $r->percentage >= $g['min'] && $r->percentage <= $g['max'])->count();
            return [
                'label' => $g['label'],
                'min' => $g['min'],
                'max' => $g['max'],
                'count' => $count,
                'percentage' => $scored->isEmpty() ? 0 : round(($count / $scored->count()) * 100, 1),
            ];
        })->all();

        $needsSupport = $scored->filter(fn ($r) => $r->percentage < 50)->map(fn ($r) => [
            'student_id' => $r->student_id,
            'student_name' => $r->student?->full_name,
            'reg_no' => $r->student?->reg_no,
            'class' => $r->schoolClass?->name,
            'subject' => $r->subject?->name,
            'percentage' => $r->percentage,
        ])->all();

        // Performance by class
        $byClass = $examination->classes->map(function (SchoolClass $class) use ($scored, $subjectIds) {
            $classResults = $scored->filter(fn ($r) => $r->class_id === $class->id);
            return [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'students' => $class->students()->where('status', 'active')->count(),
                'average' => $classResults->isEmpty() ? 0 : round($classResults->avg(fn ($r) => $r->percentage), 1),
                'pass_rate' => $classResults->isEmpty() ? 0 : round(
                    ($classResults->filter(fn ($r) => $r->percentage >= 50)->count() / $classResults->count()) * 100,
                    1
                ),
                'highest' => $classResults->isEmpty() ? null : round($classResults->max(fn ($r) => $r->percentage), 1),
                'lowest' => $classResults->isEmpty() ? null : round($classResults->min(fn ($r) => $r->percentage), 1),
            ];
        })->all();

        // Performance by subject
        $bySubject = $examination->subjects->map(function (Subject $subject) use ($scored) {
            $subjectResults = $scored->filter(fn ($r) => $r->subject_id === $subject->id);
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'max_score' => (float) ($subject->pivot->max_score ?? 0),
                'average' => $subjectResults->isEmpty() ? 0 : round($subjectResults->avg(fn ($r) => $r->percentage), 1),
                'pass_rate' => $subjectResults->isEmpty() ? 0 : round(
                    ($subjectResults->filter(fn ($r) => $r->percentage >= 50)->count() / $subjectResults->count()) * 100,
                    1
                ),
                'students' => $subjectResults->count(),
            ];
        })->all();

        // Individual student results
        $studentResults = $results->groupBy('student_id')->map(function ($group, $studentId) {
            $first = $group->first();
            return [
                'student_id' => $studentId,
                'student_name' => $first->student?->full_name,
                'reg_no' => $first->student?->reg_no,
                'class' => $first->schoolClass?->name,
                'subjects' => $group->map(fn ($r) => [
                    'subject' => $r->subject?->name,
                    'score' => (float) $r->score,
                    'max_score' => (float) $r->max_score,
                    'percentage' => $r->percentage,
                    'status' => $r->status,
                ])->all(),
                'average' => $group->isEmpty() ? 0 : round($group->avg(fn ($r) => $r->percentage), 1),
            ];
        })->values()->all();

        return Inertia::render('Examinations/Analytics', [
            'examination' => [
                'id' => $examination->id,
                'name' => $examination->name,
                'academic_year' => $examination->academicYear?->name,
                'term' => $examination->term?->name,
                'status' => $examination->status,
            ],
            'summary' => [
                'total_students' => Student::whereIn('class_id', $classIds)->where('status', 'active')->count(),
                'results_recorded' => $scored->count(),
                'overall_average' => $overallAverage,
                'pass_rate' => $passRate,
                'highest' => $highest ? [
                    'student' => $highest->student?->full_name,
                    'subject' => $highest->subject?->name,
                    'percentage' => $highest->percentage,
                ] : null,
                'lowest' => $lowest ? [
                    'student' => $lowest->student?->full_name,
                    'subject' => $lowest->subject?->name,
                    'percentage' => $lowest->percentage,
                ] : null,
            ],
            'byClass' => $byClass,
            'bySubject' => $bySubject,
            'gradeDistribution' => $gradeDistribution,
            'needsSupport' => $needsSupport,
            'studentResults' => $studentResults,
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

        // Validation: a publishable examination must be complete and well-formed.
        if ($examination->classes->isEmpty()) {
            return back()->with('error', 'Cannot publish: assign at least one class.');
        }

        if ($examination->subjects->isEmpty()) {
            return back()->with('error', 'Cannot publish: assign at least one subject.');
        }

        foreach ($examination->subjects as $subject) {
            $maxScore = (float) ($subject->pivot->max_score ?? 0);
            if (! is_numeric($maxScore) || $maxScore <= 0) {
                return back()->with('error', 'Cannot publish: every subject must have a valid maximum score.');
            }
        }

        $term = $examination->term;
        $year = $examination->academicYear;
        if (! $year || ! $term || (int) $term->academic_year_id !== (int) $year->id) {
            return back()->with('error', 'Cannot publish: the term must belong to the selected academic year.');
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

    /** Reopen a published examination, returning it to draft (Head of School / Admin only). */
    public function reopen(Request $request, Examination $examination): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        if (! $examination->reopenable()) {
            return back()->with('error', 'Only published examinations without submitted or approved results can be reopened.');
        }

        $examination->update(['status' => 'draft']);

        AuditLog::record(
            $request->user(),
            'examination.reopened',
            "Reopened examination \"{$examination->name}\" to draft",
            $examination
        );

        return back()->with('success', 'Examination returned to draft.');
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

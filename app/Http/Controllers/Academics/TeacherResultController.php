<?php

namespace App\Http\Controllers\Academics;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Examination;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TEACHER RESULT ENTRY — Teachers view examinations assigned to their classes
 * and subjects, enter student marks, save drafts, and submit for review.
 */
class TeacherResultController extends Controller
{
    /** Examinations assigned to the teacher's classes and subjects. */
    public function index(Request $request): Response
    {
        $teacher = $request->user();

        $classIds = $teacher->assignedClassIds();

        $examinations = Examination::published()
            ->whereHas('classes', function ($q) use ($classIds) {
                $q->whereIn('classes.id', $classIds);
            })
            ->with(['academicYear', 'term', 'classes', 'subjects'])
            ->latest()
            ->get()
            ->map(function (Examination $exam) use ($teacher, $classIds) {
                // Filter classes to only those the teacher teaches
                $teacherClasses = $exam->classes->whereIn('id', $classIds);

                // Get subjects the teacher teaches in those classes
                $teacherSubjects = collect();
                foreach ($teacherClasses as $class) {
                    $pivotSubjects = $class->pivot ?? null;
                    // class_subject pivot has teacher_id; check teacher teaches this subject in this class
                    $classSubjects = \App\Models\Subject::where('is_active', true)
                        ->join('class_subject', 'class_subject.subject_id', '=', 'subjects.id')
                        ->where('class_subject.class_id', $class->id)
                        ->where('class_subject.teacher_id', $teacher->id)
                        ->get(['subjects.id', 'subjects.name', 'subjects.code', 'class_subject.teacher_id']);
                    $teacherSubjects = $teacherSubjects->merge($classSubjects);
                }
                $teacherSubjects = $teacherSubjects->unique('id');

                return [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'academic_year' => $exam->academicYear?->name,
                    'term' => $exam->term?->name,
                    'status' => $exam->status,
                    'classes' => $teacherClasses->map(fn ($c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'code' => $c->code,
                    ])->all(),
                    'subjects' => $teacherSubjects->map(fn ($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'code' => $s->code,
                        'max_score' => (float) ($exam->subjects->first(fn ($es) => $es->id === $s->id)?->pivot->max_score ?? 0),
                    ])->all(),
                ];
            });

        return Inertia::render('Teacher/AssignedExaminations', [
            'examinations' => $examinations,
        ]);
    }

    /** Step 1: classes the teacher is authorized to record marks for. */
    public function classes(Request $request): Response
    {
        $teacher = $request->user();

        $classIds = $teacher->assignedClassIds();

        $classes = SchoolClass::whereIn('id', $classIds)
            ->active()
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->ordered()
            ->get(['id', 'name', 'code', 'level', 'capacity']);

        return Inertia::render('Teacher/Marks/SelectClass', [
            'classes' => $classes->map(fn (SchoolClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'level' => $c->level?->value,
                'students_count' => (int) $c->students_count,
            ])->all(),
        ]);
    }

    /** Step 2: subjects the teacher is assigned to teach in the given class. */
    public function subjects(Request $request): Response
    {
        $teacher = $request->user();

        $classIds = $teacher->assignedClassIds();
        $classId = (int) $request->query('class_id');

        abort_unless(
            in_array($classId, $classIds, true),
            403,
            'You are not assigned to this class.'
        );

        $class = SchoolClass::find($classId);
        abort_unless($class, 404, 'Class not found.');

        $subjects = Subject::where('is_active', true)
            ->join('class_subject', 'class_subject.subject_id', '=', 'subjects.id')
            ->where('class_subject.class_id', $classId)
            ->where('class_subject.teacher_id', $teacher->id)
            ->orderBy('subjects.name')
            ->get(['subjects.id', 'subjects.name', 'subjects.code']);

        return Inertia::render('Teacher/Marks/SelectSubject', [
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
            ],
            'subjects' => $subjects->all(),
        ]);
    }

    /** Step 3: published examinations for the selected class and subject. */
    public function examinations(Request $request): Response
    {
        $teacher = $request->user();

        $classIds = $teacher->assignedClassIds();
        $classId = (int) $request->query('class_id');
        $subjectId = (int) $request->query('subject_id');

        abort_unless(
            in_array($classId, $classIds, true),
            403,
            'You are not assigned to this class.'
        );

        abort_unless(
            $this->teachesSubjectInClass($teacher, $classId, $subjectId),
            403,
            'You are not assigned to teach this subject in this class.'
        );

        $class = SchoolClass::find($classId);
        $subject = Subject::find($subjectId);

        abort_unless($class && $subject, 404, 'Class or subject not found.');

        $examinations = Examination::published()
            ->whereHas('classes', fn ($q) => $q->where('classes.id', $classId))
            ->whereHas('subjects', fn ($q) => $q->where('subjects.id', $subjectId))
            ->with(['academicYear', 'term'])
            ->latest()
            ->get()
            ->map(fn (Examination $exam) => [
                'id' => $exam->id,
                'name' => $exam->name,
                'academic_year' => $exam->academicYear?->name,
                'term' => $exam->term?->name,
                'status' => $exam->status,
            ]);

        return Inertia::render('Teacher/Marks/SelectExamination', [
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
            ],
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
            ],
            'examinations' => $examinations->all(),
        ]);
    }

    /** Load eligible students and subjects for result entry. */
    public function results(Request $request, Examination $examination): Response
    {
        $teacher = $request->user();

        $classIds = $teacher->assignedClassIds();

        // Only published, accessible examinations may be recorded against.
        abort_unless(
            $examination->status === 'published',
            403,
            'Results can only be recorded for a published examination.'
        );

        // Validate teacher has access to this examination's classes
        abort_unless(
            $examination->classes->whereIn('id', $classIds)->isNotEmpty(),
            403,
            'You are not assigned to any class in this examination.'
        );

        $classId = (int) $request->query('class_id', $examination->classes->whereIn('id', $classIds)->first()?->id ?? 0);
        $subjectId = (int) $request->query('subject_id', 0);

        abort_unless(
            in_array($classId, $classIds, true),
            403,
            'You are not assigned to this class.'
        );

        // The teacher must be assigned to teach this subject in this class.
        abort_unless(
            $subjectId > 0 && $this->teachesSubjectInClass($teacher, $classId, $subjectId),
            403,
            'You are not assigned to teach this subject in this class.'
        );

        // Get max_score for this subject in this examination
        $subjectConfig = $examination->subjects->first(fn ($s) => $s->id === $subjectId);
        abort_unless($subjectConfig, 403, 'This subject is not part of the examination.');
        $maxScore = (float) $subjectConfig->pivot->max_score;

        // Load students in the class
        $students = Student::where('class_id', $classId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'reg_no', 'first_name', 'last_name', 'other_name']);

        // Load existing results
        $existingResults = Assessment::forExamination($examination->id)
            ->where('class_id', $classId)
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->get();

        return Inertia::render('Teacher/ResultEntry', [
            'examination' => [
                'id' => $examination->id,
                'name' => $examination->name,
                'academic_year' => $examination->academicYear?->name,
                'term' => $examination->term?->name,
                'status' => $examination->status,
            ],
            'class' => [
                'id' => $classId,
                'name' => SchoolClass::find($classId)?->name,
                'code' => SchoolClass::find($classId)?->code,
            ],
            'subject' => $subjectId ? Subject::find($subjectId) : null,
            'max_score' => $maxScore,
            'students' => $students->map(fn (Student $s) => [
                'id' => $s->id,
                'reg_no' => $s->reg_no,
                'name' => $s->full_name,
                'age' => $s->age,
            ])->all(),
            'existingResults' => $existingResults->map(fn (Assessment $a) => [
                'student_id' => $a->student_id,
                'subject_id' => $a->subject_id,
                'score' => (float) $a->score,
                'status' => $a->status,
                'remarks' => $a->remarks,
                'id' => $a->id,
            ])->all(),
            'classes' => $examination->classes->whereIn('id', $classIds)->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
            ])->all(),
            'subjects' => $examination->subjects->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'max_score' => (float) $s->pivot->max_score,
            ])->all(),
        ]);
    }

    /** Save (draft) or update student marks in bulk within a transaction. */
    public function saveResults(Request $request, Examination $examination): RedirectResponse
    {
        $teacher = $request->user();

        $classIds = $teacher->assignedClassIds();

        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'results' => ['required', 'array', 'min:1'],
            'results.*.student_id' => ['required', 'exists:students,id'],
            'results.*.score' => ['required', 'numeric', 'min:0'],
            'results.*.remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless(
            in_array($data['class_id'], $classIds, true),
            403,
            'You are not assigned to this class.'
        );

        // The teacher must be assigned to teach this subject in this class.
        abort_unless(
            $this->teachesSubjectInClass($teacher, $data['class_id'], $data['subject_id']),
            403,
            'You are not assigned to teach this subject in this class.'
        );

        if ($examination->status === 'closed') {
            abort(403, 'Results cannot be saved for a closed examination.');
        }

        // The subject must be part of this examination.
        $subjectConfig = $examination->subjects->first(fn ($s) => $s->id === $data['subject_id']);
        abort_unless($subjectConfig, 403, 'This subject is not part of the examination.');
        $maxScore = (float) $subjectConfig->pivot->max_score;

        // Validate student is in the class
        $studentIds = Student::where('class_id', $data['class_id'])
            ->where('status', 'active')
            ->pluck('id')->all();

        foreach ($data['results'] as $result) {
            abort_unless(
                in_array($result['student_id'], $studentIds, true),
                403,
                "Student {$result['student_id']} is not in class {$data['class_id']}."
            );
        }

        foreach ($data['results'] as $result) {
            if ((float) $result['score'] > $maxScore) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'results' => ["Marks for student {$result['student_id']} cannot exceed maximum score of {$maxScore}."],
                ]);
            }
        }

        DB::transaction(function () use ($examination, $data, $teacher, $maxScore, $subjectConfig) {
            foreach ($data['results'] as $result) {
                $existing = Assessment::forExamination($examination->id)
                    ->where('student_id', $result['student_id'])
                    ->where('class_id', $data['class_id'])
                    ->where('subject_id', $data['subject_id'])
                    ->first();

                // Submitted or approved results are locked; only draft/returned may be revised.
                if ($existing && in_array($existing->status, ['submitted', 'approved'], true)) {
                    abort(403, "Results for student {$result['student_id']} are {$existing->status} and cannot be modified.");
                }

                Assessment::updateOrCreate(
                    [
                        'examination_id' => $examination->id,
                        'student_id' => $result['student_id'],
                        'subject_id' => $data['subject_id'],
                    ],
                    [
                        'class_id' => $data['class_id'],
                        'term_id' => $examination->term_id,
                        'title' => $examination->name . ' - ' . $subjectConfig->name,
                        'type' => 'exam',
                        'score' => $result['score'],
                        'max_score' => $maxScore,
                        'remarks' => $result['remarks'] ?? null,
                        'recorded_by' => $teacher->id,
                        'status' => 'draft',
                        'obtained_at' => now()->toDateString(),
                    ]
                );
            }

            AuditLog::record(
                $teacher,
                'examination.results.draft_saved',
                "Saved draft results for examination \"{$examination->name}\"",
                $examination
            );
        });

        return back()->with('success', 'Results saved as draft.');
    }

    /** Submit draft results for Head of School review. */
    public function submitResults(Request $request, Examination $examination): RedirectResponse
    {
        $teacher = $request->user();

        $classIds = $teacher->assignedClassIds();

        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        abort_unless(
            in_array($data['class_id'], $classIds, true),
            403,
            'You are not assigned to this class.'
        );

        abort_unless(
            $this->teachesSubjectInClass($teacher, $data['class_id'], $data['subject_id']),
            403,
            'You are not assigned to teach this subject in this class.'
        );

        if ($examination->status === 'closed') {
            abort(403, 'Results cannot be submitted for a closed examination.');
        }

        $subjectConfig = $examination->subjects->first(fn ($s) => $s->id === $data['subject_id']);
        abort_unless($subjectConfig, 403, 'This subject is not part of the examination.');

        $updated = Assessment::forExamination($examination->id)
            ->where('status', 'draft')
            ->where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('recorded_by', $teacher->id)
            ->update(['status' => 'submitted', 'submitted_at' => now()]);

        if ($updated === 0) {
            return back()->with('error', 'No draft results to submit (they may already be submitted).');
        }

        AuditLog::record(
            $teacher,
            'examination.results.submitted',
            "Submitted {$updated} result(s) for examination \"{$examination->name}\"",
            $examination
        );

        return back()->with('success', "Submitted {$updated} result(s) for review.");
    }

    /** True when the teacher is assigned to teach the subject in the given class. */
    protected function teachesSubjectInClass(User $teacher, int $classId, int $subjectId): bool
    {
        return \App\Models\Subject::where('is_active', true)
            ->join('class_subject', 'class_subject.subject_id', '=', 'subjects.id')
            ->where('class_subject.class_id', $classId)
            ->where('class_subject.teacher_id', $teacher->id)
            ->where('subjects.id', $subjectId)
            ->exists();
    }
}

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

        $classIds = $teacher->classesTaught()->pluck('classes.id')->all();

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

    /** Load eligible students and subjects for result entry. */
    public function results(Request $request, Examination $examination): Response
    {
        $teacher = $request->user();

        $classIds = $teacher->classesTaught()->pluck('classes.id')->all();

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

        // Get max_score for this subject in this examination
        $subjectConfig = $examination->subjects->first(fn ($s) => $s->id === $subjectId);
        $maxScore = $subjectConfig ? (float) $subjectConfig->pivot->max_score : 0;

        // Load students in the class
        $students = Student::where('class_id', $classId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'reg_no', 'first_name', 'last_name', 'other_name']);

        // Load existing results
        $existingResults = Assessment::forExamination($examination->id)
            ->where('class_id', $classId)
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->get()
            ->keyBy(fn (Assessment $a) => "{$a->student_id}-{$a->subject_id}");

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

        $classIds = $teacher->classesTaught()->pluck('classes.id')->all();

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

        if ($examination->status === 'closed') {
            abort(403, 'Results cannot be saved for a closed examination.');
        }

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

        // Validate max score
        $subjectConfig = $examination->subjects->first(fn ($s) => $s->id === $data['subject_id']);
        $maxScore = $subjectConfig ? (float) $subjectConfig->pivot->max_score : 0;

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

                if ($existing && $existing->status === 'approved') {
                    abort(403, "Approved results for student {$result['student_id']} cannot be modified.");
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

        $classIds = $teacher->classesTaught()->pluck('classes.id')->all();

        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        abort_unless(
            in_array($data['class_id'], $classIds, true),
            403,
            'You are not assigned to this class.'
        );

        if ($examination->status === 'closed') {
            abort(403, 'Results cannot be submitted for a closed examination.');
        }

        $updated = Assessment::forExamination($examination->id)
            ->where('status', 'draft')
            ->where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('recorded_by', $teacher->id)
            ->update(['status' => 'submitted']);

        AuditLog::record(
            $teacher,
            'examination.results.submitted',
            "Submitted {$updated} result(s) for examination \"{$examination->name}\"",
            $examination
        );

        return back()->with('success', "Submitted {$updated} result(s) for review.");
    }
}

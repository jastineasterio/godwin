<?php

namespace App\Http\Controllers\Academics;

use App\Enums\AssessmentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ASSESSMENTS & MARKS — entry, listing and editing of test/exam/
 * milestone scores. Teachers are scoped to their own classes.
 */
class AssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        $classIds = $this->allowedClassIds($request->user());

        $assessments = Assessment::query()
            ->with(['student:id,first_name,last_name,reg_no', 'subject:id,name'])
            ->whereIn('class_id', $classIds)
            ->when($request->query('class_id'), fn ($q, $id) => $q->where('class_id', $id))
            ->when($request->query('student_id'), fn ($q, $id) => $q->where('student_id', $id))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->latest('obtained_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Assessment $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'student' => $a->student?->full_name,
                'subject' => $a->subject?->name,
                'type' => $a->type->value,
                'score' => (float) $a->score,
                'max_score' => (float) $a->max_score,
                'percentage' => $a->percentage,
                'obtained_at' => $a->obtained_at?->toDateString(),
            ]);

        return Inertia::render('Assessments/Index', [
            'assessments' => $assessments,
            'classes' => SchoolClass::whereIn('id', $classIds)->ordered()->get(['id', 'name', 'code']),
            'filters' => $request->only('class_id', 'student_id', 'type'),
            'types' => collect(AssessmentType::cases())->map(fn ($t) => [
                'value' => $t->value, 'label' => $t->label(),
            ]),
        ]);
    }

    /** Entry form (also used for editing). */
    public function create(Request $request): Response
    {
        return $this->form($request, (int) $request->query('class_id', $this->allowedClassIds($request->user())[0] ?? 0));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['recorded_by'] = $request->user()->id;
        $data['class_id'] ??= Student::findOrFail($data['student_id'])->class_id;

        $assessment = Assessment::create($data);

        AuditLog::record($request->user(), 'assessment.created', "Recorded \"{$assessment->title}\"", $assessment);

        return redirect()->route('assessments.index')
            ->with('success', "Assessment recorded for {$assessment->student?->full_name}.");
    }

    public function edit(Request $request, Assessment $assessment): Response
    {
        abort_unless(
            in_array($assessment->class_id, $this->allowedClassIds($request->user()), true),
            403
        );

        $props = $this->form($request, $assessment->class_id);
        $props['assessment'] = [
            ...$assessment->only([
                'id', 'student_id', 'subject_id', 'term_id', 'title',
                'type', 'remarks', 'obtained_at',
            ]),
            'score' => (float) $assessment->score,
            'max_score' => (float) $assessment->max_score,
        ];

        return Inertia::render('Assessments/Create', $props);
    }

    public function update(Request $request, Assessment $assessment): RedirectResponse
    {
        abort_unless(
            in_array($assessment->class_id, $this->allowedClassIds($request->user()), true),
            403
        );

        $assessment->update($this->validated($request));

        return redirect()->route('assessments.index')->with('success', 'Assessment updated.');
    }

    public function destroy(Assessment $assessment): RedirectResponse
    {
        $assessment->delete();

        return back()->with('success', 'Assessment removed.');
    }

    /* ------------------------------------------------------------------ */

    /** Shared form payload (classes, roster, subjects, terms, types). */
    protected function form(Request $request, int $classId): array
    {
        $classIds = $this->allowedClassIds($request->user());

        return [
            'classes' => SchoolClass::whereIn('id', $classIds)->ordered()->get(['id', 'name', 'code']),
            'activeClassId' => $classId,
            'students' => $classId
                ? Student::where('class_id', $classId)->where('status', 'active')
                    ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'reg_no'])
                    ->map(fn (Student $s) => ['id' => $s->id, 'name' => $s->full_name, 'reg_no' => $s->reg_no])
                : [],
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'terms' => Term::orderBy('start_date')->get(['id', 'name', 'is_current']),
            'types' => collect(AssessmentType::cases())->map(fn ($t) => [
                'value' => $t->value, 'label' => $t->label(),
            ]),
        ];
    }

    /** Validation + "marks cannot exceed maximum" guard. */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'class_id' => ['nullable', 'exists:classes,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'term_id' => ['nullable', 'exists:terms,id'],
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(AssessmentType::values())],
            'score' => ['required', 'numeric', 'min:0'],
            'max_score' => ['required', 'numeric', 'gt:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'obtained_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        if ((float) $data['score'] > (float) $data['max_score']) {
            throw ValidationException::withMessages([
                'score' => 'Marks obtained cannot be greater than the maximum score.',
            ]);
        }

        return $data;
    }

    /** Class ids the user may write assessments for. */
    protected function allowedClassIds(User $user): array
    {
        if ($user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool])) {
            return SchoolClass::pluck('id')->all();
        }

        return $user->classesTaught()->pluck('classes.id')->all();
    }
}

<?php

namespace App\Http\Controllers\Results;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Examination;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Services\GradingScale;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RESULTS & ANALYTICS.
 *
 * Teacher: results and analytics for their assigned classes and subjects only
 * (existing behaviour, unchanged).
 *
 * Head of School & Senior Pastor: school-wide, strictly READ-ONLY access to the
 * same data sources. No marks-editing actions are exposed through this page.
 * The separate approval/return workflow (examinations.approve / examinations.return
 * / teacher result entry) is preserved untouched.
 */
class ResultsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isReadOnly = $user->hasAnyRole([UserRole::HeadOfSchool, UserRole::SeniorPastor]);

        // Backend enforcement: this endpoint is read-only for every caller.
        abort_unless($request->isMethod('GET'), 405);

        [$classIds, $empty] = $this->classScope($user, $isReadOnly);

        if ($empty) {
            return Inertia::render('Results/Index', array_merge($this->payload($user, $isReadOnly), $this->emptyData()));
        }

        $data = $this->dataFor($request, $classIds, $isReadOnly);

        return Inertia::render('Results/Index', array_merge($this->payload($user, $isReadOnly), $data));
    }

    /** Printable, read-only academic report (reuses the existing Blade print pattern). */
    public function print(Request $request)
    {
        $user = $request->user();
        $isReadOnly = $user->hasAnyRole([UserRole::HeadOfSchool, UserRole::SeniorPastor]);

        abort_unless($request->isMethod('GET'), 405);

        [$classIds, $empty] = $this->classScope($user, $isReadOnly);

        $data = $empty
            ? $this->emptyData()
            : $this->dataFor($request, $classIds, $isReadOnly);

        $selected = $data['filters']['selected'];

        return view('print.results-analytics', [
            'school' => config('school'),
            'user_role' => $user->role->value,
            'user_role_label' => $user->role->label(),
            'is_read_only' => $isReadOnly,
            'overview' => $data['overview'],
            'grade_distribution' => $data['grade_distribution'],
            'studentPerformance' => $data['studentPerformance'],
            'classAnalytics' => $data['classAnalytics'],
            'subjectAnalytics' => $data['subjectAnalytics'],
            'examinationAnalytics' => $data['examinationAnalytics'],
            'filters' => $data['filters'],
            'selected' => $selected,
            'generated_at' => now()->format('d M Y, h:i A'),
        ]);
    }

    /** Scope classes by role and return the class ids plus an empty-data flag. */
    private function classScope($user, bool $isReadOnly): array
    {
        if ($isReadOnly) {
            // Head of School and Senior Pastor: every class in the school.
            return [SchoolClass::ordered()->pluck('id')->all(), false];
        }

        // Teacher: assigned classes only (existing behaviour).
        $classIds = $user->classesTaught()->pluck('classes.id')->all();

        return [empty($classIds) ? [] : $classIds, empty($classIds)];
    }

    /** Gather all analytics data — shared by the Inertia index and the print view. */
    private function dataFor(Request $request, array $classIds, bool $isReadOnly): array
    {
        // ------------------------------------------------------------------
        // Filters (academic year, term, examination, class, subject)
        // ------------------------------------------------------------------
        $academicYearId = $request->query('academic_year_id');
        $termId = $request->query('term_id');
        $examinationId = $request->query('examination_id');
        $classId = $request->query('class_id');
        $subjectId = $request->query('subject_id');

        $query = Assessment::examinationResults()
            ->whereIn('class_id', $classIds);

        if ($academicYearId) {
            $query->whereHas('examination', fn ($q) => $q->where('academic_year_id', $academicYearId));
        }
        if ($termId) {
            $query->whereHas('examination', fn ($q) => $q->where('term_id', $termId));
        }
        if ($examinationId) {
            $query->where('examination_id', $examinationId);
        }
        if ($classId) {
            // Leadership may pick any class; teachers are confined to their own.
            if (! $isReadOnly) {
                abort_unless(in_array((int) $classId, $classIds, true), 403);
            }
            $query->where('class_id', $classId);
        }
        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        $results = $query->with(['student', 'subject', 'examination', 'schoolClass'])
            ->get();

        // --- OVERVIEW ---
        $totalExaminations = Examination::whereHas('classes', fn ($q) => $q->whereIn('classes.id', $classIds))->count();
        $resultsRecorded = $results->count();
        $pendingSubmissions = $results->where('status', 'draft')->count();
        $submittedAwaitingApproval = $results->where('status', 'submitted')->count();
        $approvedResults = $results->where('status', 'approved')->count();

        $scoredResults = $results->filter(fn ($r) => (float) $r->max_score > 0);
        $classAverage = $scoredResults->isEmpty()
            ? 0.0
            : round($scoredResults->sum(fn ($r) => (float) $r->score / (float) $r->max_score * 100) / $scoredResults->count(), 1);

        $overview = [
            'total_examinations' => $totalExaminations,
            'results_recorded' => $resultsRecorded,
            'pending_submissions' => $pendingSubmissions,
            'submitted_awaiting_approval' => $submittedAwaitingApproval,
            'approved_results' => $approvedResults,
            'class_average' => $classAverage,
        ];

        // --- GRADE DISTRIBUTION (school's grading scheme, single source of truth) ---
        $gradeDistribution = $this->gradeDistribution($scoredResults);

        // --- STUDENT PERFORMANCE ---
        $studentPerformance = $results->map(fn (Assessment $r) => [
            'student_name' => $r->student?->full_name ?? 'Unknown',
            'reg_no' => $r->student?->reg_no ?? '—',
            'class' => $r->schoolClass?->name ?? '—',
            'subject' => $r->subject?->name ?? 'General',
            'examination' => $r->examination?->name ?? 'Standalone',
            'marks_obtained' => (float) $r->score,
            'max_marks' => (float) $r->max_score,
            'percentage' => $r->percentage,
            'grade' => $this->grade($r->percentage),
            'status' => $r->status,
        ])->all();

        // --- CLASS ANALYTICS ---
        $classAnalytics = [];
        foreach ($classIds as $cid) {
            $classResults = $results->where('class_id', $cid);
            if ($classResults->isEmpty()) continue;

            $scored = $classResults->filter(fn ($r) => (float) $r->max_score > 0);
            $percentages = $scored->map(fn ($r) => (float) $r->score / (float) $r->max_score * 100);
            $class = SchoolClass::find($cid);

            $classAnalytics[] = [
                'id' => $cid,
                'name' => $class?->name ?? 'Unknown',
                'code' => $class?->code ?? '—',
                'average' => $percentages->isEmpty() ? 0.0 : round($percentages->avg(), 1),
                'highest' => $percentages->isEmpty() ? 0.0 : round($percentages->max(), 1),
                'lowest' => $percentages->isEmpty() ? 0.0 : round($percentages->min(), 1),
                'students_count' => $classResults->pluck('student_id')->unique()->count(),
                'results_recorded' => $classResults->count(),
            ];
        }

        // --- SUBJECT ANALYTICS ---
        $subjectAnalytics = [];
        $subjectIds = $results->pluck('subject_id')->unique()->filter()->all();
        foreach ($subjectIds as $sid) {
            $subjectResults = $results->where('subject_id', $sid);
            $scored = $subjectResults->filter(fn ($r) => (float) $r->max_score > 0);
            $percentages = $scored->map(fn ($r) => (float) $r->score / (float) $r->max_score * 100);
            $subject = Subject::find($sid);

            $subjectAnalytics[] = [
                'id' => $sid,
                'name' => $subject?->name ?? 'General',
                'code' => $subject?->code ?? '—',
                'average' => $percentages->isEmpty() ? 0.0 : round($percentages->avg(), 1),
                'highest' => $percentages->isEmpty() ? 0.0 : round($percentages->max(), 1),
                'lowest' => $percentages->isEmpty() ? 0.0 : round($percentages->min(), 1),
                'results_count' => $subjectResults->count(),
            ];
        }

        // --- EXAMINATION ANALYTICS ---
        $examinationAnalytics = [];
        $examIds = $results->pluck('examination_id')->unique()->filter()->all();
        foreach ($examIds as $eid) {
            $examResults = $results->where('examination_id', $eid);
            $scored = $examResults->filter(fn ($r) => (float) $r->max_score > 0);
            $percentages = $scored->map(fn ($r) => (float) $r->score / (float) $r->max_score * 100);
            $exam = Examination::find($eid);

            $examinationAnalytics[] = [
                'id' => $eid,
                'name' => $exam?->name ?? 'Unknown',
                'average' => $percentages->isEmpty() ? 0.0 : round($percentages->avg(), 1),
                'highest' => $percentages->isEmpty() ? 0.0 : round($percentages->max(), 1),
                'lowest' => $percentages->isEmpty() ? 0.0 : round($percentages->min(), 1),
                'results_recorded' => $examResults->count(),
                'submission_progress' => $examResults->whereIn('status', ['submitted', 'approved'])->count(),
            ];
        }

        // --- FILTER OPTIONS ---
        $academicYears = AcademicYear::orderByDesc('name')->get(['id', 'name']);
        $terms = Term::orderBy('start_date')->get(['id', 'name', 'academic_year_id']);

        if ($isReadOnly) {
            // Leadership: every examination, class and subject in the school.
            $examinations = Examination::orderBy('name')->get(['id', 'name']);
            $classes = SchoolClass::ordered()->get(['id', 'name', 'code']);
            $subjects = Subject::orderBy('name')->get(['id', 'name', 'code']);
        } else {
            $examinations = Examination::whereHas('classes', fn ($q) => $q->whereIn('classes.id', $classIds))
                ->get(['id', 'name']);
            $classes = SchoolClass::whereIn('id', $classIds)->ordered()->get(['id', 'name', 'code']);
            $subjects = Subject::whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name', 'code']);
        }

        return [
            'overview' => $overview,
            'grade_distribution' => $gradeDistribution,
            'studentPerformance' => $studentPerformance,
            'classAnalytics' => $classAnalytics,
            'subjectAnalytics' => $subjectAnalytics,
            'examinationAnalytics' => $examinationAnalytics,
            'filters' => [
                'academic_years' => $academicYears->all(),
                'terms' => $terms->all(),
                'examinations' => $examinations->all(),
                'classes' => $classes->all(),
                'subjects' => $subjects->all(),
                'selected' => [
                    'academic_year_id' => $academicYearId,
                    'term_id' => $termId,
                    'examination_id' => $examinationId,
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                ],
            ],
        ];
    }

    /** Inertia payload header shared between roles. */
    private function payload($user, bool $isReadOnly): array
    {
        return [
            'is_read_only' => $isReadOnly,
            'user_role' => $user->role->value,
        ];
    }

    /** Fully empty payload used when there are no applicable classes. */
    private function emptyData(): array
    {
        return [
            'overview' => [
                'total_examinations' => 0,
                'results_recorded' => 0,
                'pending_submissions' => 0,
                'submitted_awaiting_approval' => 0,
                'approved_results' => 0,
                'class_average' => 0.0,
            ],
            'grade_distribution' => [],
            'studentPerformance' => [],
            'classAnalytics' => [],
            'subjectAnalytics' => [],
            'examinationAnalytics' => [],
            'filters' => [
                'academic_years' => [],
                'terms' => [],
                'examinations' => [],
                'classes' => [],
                'subjects' => [],
                'selected' => [
                    'academic_year_id' => null,
                    'term_id' => null,
                    'examination_id' => null,
                    'class_id' => null,
                    'subject_id' => null,
                ],
            ],
        ];
    }

    /** Grade band for a percentage using the school's grading scheme. */
    private function grade(?float $percentage): string
    {
        if ($percentage === null) return '—';
        return GradingScale::grade($percentage);
    }

    /** Tally scored results into the school's grade bands (A — E). */
    private function gradeDistribution(Collection $results): array
    {
        if ($results->isEmpty()) {
            return [];
        }

        $tally = [];
        foreach (GradingScale::BANDS as $label) {
            $tally[$label] = 0;
        }

        $results->each(fn ($r) => $tally[GradingScale::grade($r->percentage)]++);

        $total = $results->count();

        return array_map(fn ($label, $count) => [
            'label' => $label,
            'count' => $count,
            'percentage' => $total === 0 ? 0.0 : round(($count / $total) * 100, 1),
        ], array_keys($tally), array_values($tally));
    }
}

<?php

namespace App\Http\Controllers\Results;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Examination;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\GradingScale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RESULTS & ANALYTICS — Teacher views results and analytics for their
 * assigned classes and subjects only.
 */
class ResultsController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $classIds = $teacher->classesTaught()->pluck('classes.id')->all();

        if (empty($classIds)) {
            return Inertia::render('Results/Index', [
                'overview' => [
                    'total_examinations' => 0,
                    'results_recorded' => 0,
                    'pending_submissions' => 0,
                    'approved_results' => 0,
                    'class_average' => 0.0,
                ],
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
                ],
            ]);
        }

        // Filters
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
            abort_unless(in_array((int) $classId, $classIds, true), 403);
            $query->where('class_id', $classId);
        }
        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        $results = $query->with(['student', 'subject', 'examination'])
            ->get();

        // --- OVERVIEW ---
        $totalExaminations = Examination::whereHas('classes', fn ($q) => $q->whereIn('classes.id', $classIds))->count();
        $resultsRecorded = $results->count();
        $pendingSubmissions = $results->where('status', 'draft')->count();
        $approvedResults = $results->where('status', 'approved')->count();

        $scoredResults = $results->filter(fn ($r) => (float) $r->max_score > 0);
        $classAverage = $scoredResults->isEmpty()
            ? 0.0
            : round($scoredResults->sum(fn ($r) => (float) $r->score / (float) $r->max_score * 100) / $scoredResults->count(), 1);

        $overview = [
            'total_examinations' => $totalExaminations,
            'results_recorded' => $resultsRecorded,
            'pending_submissions' => $pendingSubmissions,
            'approved_results' => $approvedResults,
            'class_average' => $classAverage,
        ];

        // --- STUDENT PERFORMANCE ---
        $studentPerformance = $results->map(fn (Assessment $r) => [
            'student_name' => $r->student?->full_name ?? 'Unknown',
            'reg_no' => $r->student?->reg_no ?? '—',
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
        $examinations = Examination::whereHas('classes', fn ($q) => $q->whereIn('classes.id', $classIds))
            ->get(['id', 'name']);
        $classes = SchoolClass::whereIn('id', $classIds)->ordered()->get(['id', 'name', 'code']);
        $subjects = Subject::whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Results/Index', [
            'overview' => $overview,
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
        ]);
    }

    private function grade(?float $percentage): string
    {
        if ($percentage === null) return '—';
        return GradingScale::grade($percentage);
    }
}

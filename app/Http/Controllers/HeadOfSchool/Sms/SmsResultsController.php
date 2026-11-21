<?php

namespace App\Http\Controllers\HeadOfSchool\Sms;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\SchoolClass;
use App\Models\SmsMessage;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Sms\SmsResultsService;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HEAD OF SCHOOL — Results SMS management.
 *
 * Secure workflow: submitted and approved results are eligible,
 * student-parent relationships are strictly enforced, and duplicate
 * notifications are prevented via idempotency keys.
 */
class SmsResultsController extends Controller
{
    protected SmsResultsService $resultsService;

    protected SmsTemplateService $templateService;

    public function __construct(
        SmsResultsService $resultsService,
        SmsTemplateService $templateService
    ) {
        $this->resultsService = $resultsService;
        $this->templateService = $templateService;
    }

    public function index(): Response
    {
        $examinations = $this->resultsService->getEligibleExaminations(auth()->user());
        $defaultTemplate = $this->templateService->getDefaultResultsTemplate();

        return Inertia::render('Sms/Results', [
            'examinations' => $examinations,
            'default_template' => $defaultTemplate,
            'templates' => $this->templateService->all()->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'category' => $t->category,
                'template' => $t->template,
            ])->toArray(),
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code', 'level'])->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'level' => $c->level,
            ])->toArray(),
        ]);
    }

    public function students(Request $request)
    {
        $validated = $request->validate([
            'examination_id' => ['required', 'integer', 'exists:examinations,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
        ]);

        $students = $this->resultsService->getEligibleStudents($validated['examination_id'], $validated['class_id'] ?? null);

        // Attach the latest SMS delivery status per student for this examination.
        $statuses = SmsMessage::where('category', 'results')
            ->where('metadata->examination_id', $validated['examination_id'])
            ->get()
            ->groupBy(fn ($m) => $m->metadata['student_id'] ?? null)
            ->map(function ($group) {
                return $group->sortByDesc('created_at')->first()->status;
            })->toArray();

        foreach ($students['students'] as &$student) {
            $student['sms_status'] = $statuses[$student['student_id']] ?? null;
        }
        unset($student);

        foreach ($students['missing_phone_students'] as &$student) {
            $student['sms_status'] = $statuses[$student['student_id']] ?? null;
        }
        unset($student);

        return response()->json($students);
    }

    public function preview(Request $request): Response
    {
        $validated = $request->validate([
            'examination_id' => ['required', 'integer', 'exists:examinations,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'template' => ['required', 'string'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $preview = $this->resultsService->preview(
            $validated['examination_id'],
            $validated['template'],
            auth()->user(),
            $validated['class_id'] ?? null,
            $validated['student_ids'] ?? [],
        );

        return Inertia::render('Sms/ResultsPreview', $preview);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'examination_id' => ['required', 'integer', 'exists:examinations,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
            'template' => ['nullable', 'string'],
            'message' => ['nullable', 'string', 'max:1600'],
            'sender_id' => ['nullable', 'string', 'max:50'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'is_resend' => ['boolean'],
            'resend_reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->resultsService->sendResultsNotifications(
                $validated['examination_id'],
                $validated['student_ids'],
                auth()->user(),
                $validated['template'] ?? null,
                $validated['template_id'] ?? null,
                $validated['class_id'] ?? null,
                [
                    'is_resend' => $validated['is_resend'] ?? false,
                    'resend_reason' => $validated['resend_reason'] ?? null,
                    'sender_id' => $validated['sender_id'] ?? null,
                    'message' => $validated['message'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Results SMS submission failed', [
                'examination_id' => $validated['examination_id'],
                'student_count' => count($validated['student_ids']),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'sent' => 0,
                'failed' => count($validated['student_ids']),
                'skipped' => 0,
                'duplicates_prevented' => 0,
                'error' => 'Results SMS submission failed. No messages were accepted by the provider.',
                'details' => [],
            ], 500);
        }

        $totalFailure = $result['sent'] === 0 && $result['failed'] > 0;

        return response()->json([
            'success' => $result['failed'] === 0,
            'sent' => $result['sent'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
            'duplicates_prevented' => $result['duplicates_prevented'],
            'error' => $totalFailure ? ($result['details'][0]['error'] ?? 'No messages were accepted by the provider.') : null,
            'details' => $result['details'],
        ], $totalFailure ? 422 : 200);
    }

    public function history(Request $request): Response
    {
        $examinationId = $request->query('examination_id');

        $query = SmsMessage::where('category', 'results')
            ->with('user')
            ->orderByDesc('created_at');

        if ($examinationId) {
            $query->where('metadata->examination_id', (int) $examinationId);
        }

        $messages = $query->paginate(20)->withQueryString();

        // Resolve friendly student/parent names recorded in the message metadata
        // so the history table identifies who each message was about.
        $studentIds = [];
        $parentIds = [];
        foreach ($messages->items() as $message) {
            $metadata = $message->metadata ?? [];
            if (isset($metadata['student_id'])) {
                $studentIds[] = $metadata['student_id'];
            }
            if (isset($metadata['parent_id'])) {
                $parentIds[] = $metadata['parent_id'];
            }
        }

        $students = Student::whereIn('id', array_unique($studentIds))
            ->get()->keyBy('id')->map(fn ($s) => $s->full_name);
        $parents = User::whereIn('id', array_unique($parentIds))
            ->get()->keyBy('id')->map(fn ($u) => $u->name);

        $messages = $messages->through(fn ($message) => [
            'id' => $message->id,
            'student_id' => $message->metadata['student_id'] ?? null,
            'parent_id' => $message->metadata['parent_id'] ?? null,
            'student_name' => $students[$message->metadata['student_id'] ?? null] ?? null,
            'parent_name' => $parents[$message->metadata['parent_id'] ?? null] ?? null,
            'to' => $message->to,
            'message' => $message->message,
            'category' => $message->category,
            'status' => $message->status,
            'provider_status' => $message->provider_status,
            'sent_at' => $message->sent_at,
            'delivered_at' => $message->delivered_at,
            'error_message' => $message->error_message,
            'sent_by' => $message->user?->name,
            'sent_at_formatted' => $message->created_at?->format('Y-m-d H:i:s'),
            'resend_of' => $message->metadata['resend_of'] ?? null,
            'is_resend' => (bool) ($message->metadata['resend_of'] ?? false),
        ]);

        $examinations = Examination::published()->get(['id', 'name']);

        return Inertia::render('Sms/ResultsHistory', [
            'messages' => $messages,
            'examinations' => $examinations,
            'selected_examination' => $examinationId,
        ]);
    }

    public function academicYears(): Response
    {
        return Inertia::render('Sms/ResultsSelect', [
            'academic_years' => AcademicYear::all(['id', 'name', 'start_date', 'end_date']),
            'terms' => Term::all(['id', 'name', 'academic_year_id']),
        ]);
    }
}

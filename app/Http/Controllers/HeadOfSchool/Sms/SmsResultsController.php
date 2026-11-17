<?php

namespace App\Http\Controllers\HeadOfSchool\Sms;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\Term;
use App\Services\Sms\SmsResultsService;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HEAD OF SCHOOL — Results SMS management.
 *
 * Secure workflow: only approved/published results are eligible,
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
        ]);
    }

    public function preview(Request $request): Response
    {
        $validated = $request->validate([
            'examination_id' => ['required', 'integer', 'exists:examinations,id'],
            'template' => ['required', 'string'],
        ]);

        $preview = $this->resultsService->preview(
            $validated['examination_id'],
            $validated['template'],
            auth()->user()
        );

        return Inertia::render('Sms/ResultsPreview', $preview);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'examination_id' => ['required', 'integer', 'exists:examinations,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'message' => ['required', 'string', 'max:1600'],
            'template_id' => ['nullable', 'integer', 'exists:sms_templates,id'],
            'sender_id' => ['nullable', 'string', 'max:50'],
            'is_resend' => ['boolean'],
            'resend_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->resultsService->sendResultsNotifications(
            $validated['examination_id'],
            $validated['student_ids'],
            $validated['message'],
            auth()->user(),
            $validated['template_id'] ?? null,
            [
                'is_resend' => $validated['is_resend'] ?? false,
                'resend_reason' => $validated['resend_reason'] ?? null,
                'sender_id' => $validated['sender_id'] ?? null,
            ]
        );

        if ($result['failed'] > 0) {
            return back()->with('error', "Results SMS completed with {$result['failed']} failures. {$result['sent']} sent, {$result['duplicates_prevented']} duplicates prevented.");
        }

        return back()->with('success', "Results SMS sent to {$result['sent']} recipients. {$result['duplicates_prevented']} duplicates prevented.");
    }

    public function history(Request $request): Response
    {
        $examinationId = $request->query('examination_id');

        $query = \App\Models\SmsMessage::where('category', 'results')
            ->with('user')
            ->orderByDesc('created_at');

        if ($examinationId) {
            $query->where('metadata->examination_id', $examinationId);
        }

        $messages = $query->paginate(20)->withQueryString();

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

<?php

namespace App\Http\Controllers\HeadOfSchool\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use App\Enums\UserRole;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\ValidationException;

/**
 * HEAD OF SCHOOL — SMS template management.
 */
class SmsTemplateController extends Controller
{
    protected SmsTemplateService $templateService;

    public function __construct(SmsTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    public function index(): Response
    {
        $templates = $this->templateService->all(false)->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'category' => $t->category,
            'template' => $t->template,
            'is_active' => $t->is_active,
            'created_by' => $t->creator?->name,
            'created_at' => $t->created_at,
        ])->toArray();

        $categories = ['announcements', 'notices', 'fee_reminders', 'attendance', 'results', 'general'];

        return Inertia::render('Sms/Templates', [
            'templates' => $templates,
            'categories' => $categories,
            'can_manage' => true,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:sms_templates,name,NULL,id,deleted_at,NULL'],
            'category' => ['required', 'string', 'max:50'],
            'template' => ['required', 'string', 'max:1600'],
            'is_active' => ['boolean'],
        ]);

        try {
            $template = $this->templateService->create([
                'name' => $validated['name'],
                'category' => $validated['category'],
                'template' => $validated['template'],
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template created.',
                'template' => $this->templateArray($template),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function update(Request $request, SmsTemplate $template)
    {
        $this->authorizeManage($request->user());

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', 'unique:sms_templates,name,'.$template->id],
            'category' => ['sometimes', 'required', 'string', 'max:50'],
            'template' => ['sometimes', 'required', 'string', 'max:1600'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        try {
            $this->templateService->update($template, $validated);
            $template->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Template updated.',
                'template' => $this->templateArray($template),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function toggle(SmsTemplate $template)
    {
        $this->authorizeManage(request()->user());

        $this->templateService->toggle($template);
        $template->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Template status updated.',
            'template' => $this->templateArray($template),
        ]);
    }

    public function destroy(SmsTemplate $template)
    {
        $this->authorizeManage(request()->user());

        $this->templateService->delete($template);

        return response()->json([
            'success' => true,
            'message' => 'Template deleted.',
            'template' => ['id' => $template->id],
        ]);
    }

    /**
     * Authorize template management (Head of School or Admin).
     *
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function authorizeManage(?\App\Models\User $user): void
    {
        if (! $user || ! $user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool])) {
            abort(403, 'Only administrators and heads of school can manage SMS templates.');
        }
    }

    /**
     * Build a frontend-safe template array (no credentials, no secrets).
     *
     * @return array<string, mixed>
     */
    protected function templateArray(SmsTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'category' => $template->category,
            'template' => $template->template,
            'is_active' => (bool) $template->is_active,
            'created_by' => $template->creator?->name,
            'created_at' => $template->created_at,
        ];
    }

    public function preview(Request $request): Response
    {
        $validated = $request->validate([
            'template' => ['required', 'string'],
        ]);

        $sampleVars = [
            'school_name' => 'God-Win Daycare & Nursery School',
            'parent_name' => 'John Doe',
            'student_name' => 'Jane Doe',
            'class_name' => 'KG1 Sunshine',
            'term_name' => 'Term 1',
            'exam_name' => 'Midterm Exam',
            'amount' => '50,000',
            'date' => now()->format('Y-m-d'),
        ];

        $rendered = $this->templateService->render($validated['template'], $sampleVars);
        $variables = $this->templateService->extractVariables($validated['template']);

        return Inertia::render('Sms/TemplatePreview', [
            'rendered' => $rendered,
            'variables' => $variables,
        ]);
    }
}

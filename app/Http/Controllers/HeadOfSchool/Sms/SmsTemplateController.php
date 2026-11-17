<?php

namespace App\Http\Controllers\HeadOfSchool\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:50'],
            'template' => ['required', 'string', 'max:1600'],
            'is_active' => ['boolean'],
        ]);

        try {
            $this->templateService->create([
                'name' => $validated['name'],
                'category' => $validated['category'],
                'template' => $validated['template'],
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => auth()->id(),
            ]);

            return back()->with('success', 'Template created.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    public function update(Request $request, SmsTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'category' => ['sometimes', 'required', 'string', 'max:50'],
            'template' => ['sometimes', 'required', 'string', 'max:1600'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        try {
            $this->templateService->update($template, $validated);

            return back()->with('success', 'Template updated.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    public function toggle(SmsTemplate $template): RedirectResponse
    {
        $this->templateService->toggle($template);

        return back()->with('success', 'Template status updated.');
    }

    public function destroy(SmsTemplate $template): RedirectResponse
    {
        $this->templateService->delete($template);

        return back()->with('success', 'Template deleted.');
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

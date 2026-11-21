<?php

namespace App\Services\Sms;

use App\Models\SmsTemplate;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * SMS template management with safe variable substitution.
 *
 * Only whitelisted variables are allowed — no arbitrary PHP execution.
 *
 * Supports both {{var}} (double-brace) and {var} (single-brace) placeholder
 * syntax. Double-brace placeholders are replaced first so that single-brace
 * substitution never corrupts a double-brace token.
 */
class SmsTemplateService
{
    /** Whitelisted template variables that may appear in templates. */
    public const ALLOWED_VARIABLES = [
        'school_name',
        'parent_name',
        'student_name',
        'admission_number',
        'class_name',
        'academic_year',
        'term',
        'term_name',
        'examination_name',
        'exam_name',
        'subject_summary',
        'total_marks',
        'total_max_marks',
        'average_percentage',
        'overall_grade',
        'amount',
        'date',
    ];

    /** Default results SMS template. */
    public const DEFAULT_RESULTS_TEMPLATE = "Dear {{parent_name}}, results for {{student_name}} ({{admission_number}}), Class {{class_name}}, {{examination_name}}:\n\n{{subject_summary}}\n\nTotal: {{total_marks}}/{{total_max_marks}}\nAverage: {{average_percentage}}%\nGrade: {{overall_grade}}\n\nCongratulations on your child's progress.\n\nGod-Win Daycare & Nursery School.";

    /** @var array<string, SmsTemplate> */
    protected array $cache = [];

    public function all(?bool $activeOnly = true): Collection
    {
        $query = SmsTemplate::query()->orderBy('category')->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function find(int $id): ?SmsTemplate
    {
        return SmsTemplate::find($id);
    }

    public function create(array $data): SmsTemplate
    {
        $this->validateVariables($data['template'] ?? '');

        return SmsTemplate::create([
            'name' => $data['name'],
            'category' => $data['category'],
            'template' => $data['template'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    public function update(SmsTemplate $template, array $data): SmsTemplate
    {
        if (isset($data['template'])) {
            $this->validateVariables($data['template']);
        }

        $template->update(array_filter($data, fn ($v) => $v !== null));

        return $template;
    }

    public function delete(SmsTemplate $template): bool
    {
        return $template->delete();
    }

    public function toggle(SmsTemplate $template): SmsTemplate
    {
        $template->update(['is_active' => ! $template->is_active]);

        return $template;
    }

    /**
     * Render a template with the given variables.
     *
     * Supports both {{var}} and {var} placeholder syntax. Only whitelisted
     * variables are substituted. Unknown placeholders are left as-is rather
     * than causing errors.
     */
    public function render(string $template, array $variables = []): string
    {
        $safe = [];

        foreach (self::ALLOWED_VARIABLES as $var) {
            if (array_key_exists($var, $variables)) {
                $safe[$var] = (string) $variables[$var];
            }
        }

        return preg_replace_callback('/\{\{?([a-z_]+)\}?\}/', function ($m) use ($safe) {
            return $safe[$m[1]] ?? $m[0];
        }, $template);
    }

    /**
     * Extract variable placeholders from a template string.
     *
     * Supports both {{var}} and {var} syntax.
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{\{?([a-z_]+)\}?\}/', $template, $matches);

        return array_unique($matches[1]);
    }

    /**
     * Validate that a template only uses allowed variables.
     *
     * @throws ValidationException
     */
    public function validateVariables(string $template): void
    {
        $used = $this->extractVariables($template);
        $invalid = array_values(array_diff($used, self::ALLOWED_VARIABLES));

        if (! empty($invalid)) {
            throw ValidationException::withMessages([
                'template' => 'Template uses invalid variables: '.implode(', ', $invalid).'. Allowed: '.implode(', ', self::ALLOWED_VARIABLES).'.',
            ]);
        }
    }

    /**
     * Get the default results notification template.
     */
    public function getDefaultResultsTemplate(): string
    {
        return self::DEFAULT_RESULTS_TEMPLATE;
    }

    /**
     * Get templates grouped by category for UI select boxes.
     */
    public function groupedByCategory(): array
    {
        return $this->all(false)->groupBy('category')->toArray();
    }
}

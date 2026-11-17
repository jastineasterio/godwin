<?php

namespace App\Services\Sms;

use App\Models\SmsTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;

/**
 * SMS template management with safe variable substitution.
 *
 * Only whitelisted variables are allowed — no arbitrary PHP execution.
 */
class SmsTemplateService
{
    /** Whitelisted template variables that may appear in templates. */
    public const ALLOWED_VARIABLES = [
        'school_name',
        'parent_name',
        'student_name',
        'class_name',
        'term_name',
        'exam_name',
        'amount',
        'date',
    ];

    /** Default results SMS template. */
    public const DEFAULT_RESULTS_TEMPLATE = 'Dear {parent_name}, {student_name}\'s results for {exam_name}, {term_name} are available. Please contact the school for further details.';

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
     * Only whitelisted variables are substituted. Unknown placeholders
     * are left as-is rather than causing errors.
     */
    public function render(string $template, array $variables = []): string
    {
        $safe = [];

        foreach (self::ALLOWED_VARIABLES as $var) {
            if (array_key_exists($var, $variables)) {
                $safe['{'.$var.'}'] = (string) $variables[$var];
            }
        }

        return str_replace(array_keys($safe), array_values($safe), $template);
    }

    /**
     * Extract variable placeholders from a template string.
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $template, $matches);

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
            throw new ValidationException(
                MessageBag::make(['template' => 'Template uses invalid variables: '.implode(', ', $invalid).'. Allowed: '.implode(', ', self::ALLOWED_VARIABLES).'.'])
            );
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

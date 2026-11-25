<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SETTINGS — DB-backed school configuration (Administrator only).
 */
class SettingsController extends Controller
{
    /** Display settings page. Auto-creates sensible defaults when the table is empty. */
    public function index(): Response
    {
        $this->ensureDefaults();

        $groups = ['general', 'contact', 'admissions', 'branding'];

        $settings = Setting::query()
            ->whereIn('group', $groups)
            ->get()
            ->map(fn (Setting $s) => [
                'key' => $s->key,
                'value' => $s->value,
                'group' => $s->group,
            ])
            ->groupBy('group');

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'groups' => $groups,
        ]);
    }

    /** Persist all settings for a given group. */
    public function update(Request $request, string $group): RedirectResponse
    {
        $validated = $this->validatedFieldsForGroup($request, $group);

        foreach ($validated as $key => $value) {
            Setting::put($key, $value, $group);
        }

        return back()->with('success', ucfirst($group) . ' settings saved successfully.');
    }

    /** ------------------------------------------------------------------ */
    /** Ensure required default settings exist in the database.             */
    /** ------------------------------------------------------------------ */

    private function ensureDefaults(): void
    {
        $defaults = [
            'general' => [
                'general.school_name' => 'God-Win Daycare & Nursery School',
                'general.school_motto' => '',
                'general.school_email' => '',
                'general.school_phone' => '',
                'general.school_address' => '',
            ],
            'contact' => [
                'contact.contact_email' => '',
                'contact.contact_phone' => '',
                'contact.whatsapp' => '',
                'contact.address' => '',
                'contact.google_maps_url' => '',
            ],
            'admissions' => [
                'admissions.admissions_open' => 'true',
                'admissions.application_message' => 'Admissions are currently open.',
                'admissions.application_deadline' => '',
                'admissions.application_submitted_sms_enabled' => 'true',
                'admissions.application_accepted_sms_enabled' => 'true',
            ],
            'branding' => [
                'branding.primary_color' => '#01579B',
                'branding.secondary_color' => '#1E88E5',
                'branding.logo_url' => '',
                'branding.favicon_url' => '',
            ],
        ];

        foreach ($defaults as $group => $items) {
            foreach ($items as $key => $value) {
                Setting::firstOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'group' => $group]
                );
            }
        }
    }

    /** ------------------------------------------------------------------ */
    /** Validation rules per settings group.                                */
    /** ------------------------------------------------------------------ */

    private function validatedFieldsForGroup(Request $request, string $group): array
    {
        $rules = match ($group) {
            'general' => [
                'school_name' => ['required', 'string', 'max:150'],
                'school_motto' => ['nullable', 'string', 'max:300'],
                'school_email' => ['nullable', 'email', 'max:150'],
                'school_phone' => ['nullable', 'string', 'max:20'],
                'school_address' => ['nullable', 'string', 'max:300'],
            ],
            'contact' => [
                'contact_email' => ['nullable', 'email', 'max:150'],
                'contact_phone' => ['nullable', 'string', 'max:20'],
                'whatsapp' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:300'],
                'google_maps_url' => ['nullable', 'url', 'max:500'],
            ],
            'admissions' => [
                'admissions_open' => ['required', 'boolean'],
                'application_message' => ['nullable', 'string', 'max:500'],
                'application_deadline' => ['nullable', 'date'],
                'application_submitted_sms_enabled' => ['required', 'boolean'],
                'application_accepted_sms_enabled' => ['required', 'boolean'],
            ],
            'branding' => [
                'primary_color' => ['required', 'string', 'max:20'],
                'secondary_color' => ['required', 'string', 'max:20'],
                'logo_url' => ['nullable', 'url', 'max:500'],
                'favicon_url' => ['nullable', 'url', 'max:500'],
            ],
            default => [],
        };

        return $request->validate($rules);
    }
}

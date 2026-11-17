<?php

namespace App\Http\Controllers\Admin\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsProviderConfig;
use App\Services\Sms\SmsCreditService;
use App\Services\Sms\SmsMessageService;
use App\Services\Sms\SmsProviderResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ADMIN — SMS provider technical configuration.
 *
 * Head of School never sees these pages. Only administrators can
 * configure providers, manage credentials, test connections, and
 * purchase credits.
 */
class SmsProviderController extends Controller
{
    protected SmsProviderResolver $resolver;
    protected SmsMessageService $messageService;

    public function __construct(
        SmsProviderResolver $resolver,
        SmsMessageService $messageService
    ) {
        $this->resolver = $resolver;
        $this->messageService = $messageService;
    }

    /** Display the SMS provider settings page. */
    public function index(): Response
    {
        $configs = SmsProviderConfig::orderByDesc('is_active')->orderBy('created_at')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'provider_type' => $c->provider_type,
            'base_url' => $c->base_url,
            'sender_id' => $c->sender_id,
            'is_active' => $c->is_active,
            'has_credentials' => $c->hasCredentials(),
            'masked_api_key' => $c->maskedApiKey(),
            'last_tested_at' => $c->last_tested_at,
            'last_test_result' => $c->last_test_result,
            'last_test_message' => $c->last_test_message,
            'capabilities' => $c->capabilities,
            'created_at' => $c->created_at,
        ]);

        $activeProvider = $this->resolver->resolve();
        $capabilities = $activeProvider ? $this->messageService->getCapabilities() : [];

        return Inertia::render('Admin/Sms/ProviderSettings', [
            'configs' => $configs,
            'active_provider' => $activeProvider ? [
                'type' => $activeProvider->type(),
                'name' => $activeProvider->name(),
                'is_active' => $activeProvider->isActive(),
                'base_url' => $activeProvider->baseUrl(),
            ] : null,
            'capabilities' => $capabilities,
            'available_providers' => $this->resolver->availableProviders(),
        ]);
    }

    /** Create or update a provider configuration. */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManageSettings($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'provider_type' => ['required', 'string', 'in:darsms'],
            'base_url' => ['required', 'url', 'max:500'],
            'auth_header_name' => ['nullable', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_secret' => ['nullable', 'string', 'max:500'],
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:100'],
            'sender_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'timeout_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'capabilities' => ['nullable', 'array'],
        ]);

        // Deactivate existing active provider if activating this one.
        if ($validated['is_active'] ?? false) {
            SmsProviderConfig::where('is_active', true)->update(['is_active' => false]);
        }

        $config = SmsProviderConfig::create([
            'name' => $validated['name'],
            'provider_type' => $validated['provider_type'],
            'base_url' => $validated['base_url'],
            'auth_header_name' => $validated['auth_header_name'] ?? 'X-API-Key',
            'api_key' => $validated['api_key'] ?? null,
            'api_secret' => $validated['api_secret'] ?? null,
            'username' => $validated['username'] ?? null,
            'password' => $validated['password'] ?? null,
            'sender_id' => $validated['sender_id'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
            'timeout_seconds' => $validated['timeout_seconds'] ?? 30,
            'capabilities' => $validated['capabilities'] ?? null,
        ]);

        return back()->with('success', 'SMS provider configuration saved.');
    }

    /** Update an existing provider configuration. */
    public function update(Request $request, SmsProviderConfig $config): RedirectResponse
    {
        $this->authorizeManageSettings($request->user());

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'base_url' => ['sometimes', 'required', 'url', 'max:500'],
            'auth_header_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'api_secret' => ['sometimes', 'nullable', 'string', 'max:500'],
            'username' => ['sometimes', 'nullable', 'string', 'max:100'],
            'password' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sender_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'timeout_seconds' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:300'],
            'capabilities' => ['sometimes', 'nullable', 'array'],
        ]);

        if (($validated['is_active'] ?? false) && ! $config->is_active) {
            SmsProviderConfig::where('is_active', true)->update(['is_active' => false]);
        }

        $config->update($this->sanitizeCredentialUpdate($validated, $config));

        return back()->with('success', 'SMS provider configuration updated.');
    }

    /** Activate or deactivate a provider configuration. */
    public function toggleStatus(Request $request, SmsProviderConfig $config): RedirectResponse
    {
        $this->authorizeManageSettings($request->user());

        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if ($request->boolean('is_active') && ! $config->is_active) {
            SmsProviderConfig::where('is_active', true)->update(['is_active' => false]);
        }

        $config->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Provider status updated.');
    }

    /** Delete a provider configuration. */
    public function destroy(Request $request, SmsProviderConfig $config): RedirectResponse
    {
        $this->authorizeManageSettings($request->user());

        if ($config->is_active) {
            return back()->with('error', 'Cannot delete the active provider. Deactivate it first.');
        }

        $config->delete();

        return back()->with('success', 'SMS provider configuration deleted.');
    }

    /** Test the active provider connection. */
    public function testConnection(Request $request): RedirectResponse
    {
        $this->authorizeManageSettings($request->user());

        $result = $this->messageService->testConnection();

        $config = $this->resolver->getConfig();

        if ($config) {
            $config->update([
                'last_tested_at' => now(),
                'last_test_result' => $result['success'] ? 'success' : 'failed',
                'last_test_message' => $result['message'] ?? '',
            ]);
        }

        if ($result['success']) {
            return back()->with('success', $result['message'] . ($result['balance'] !== null ? ' · Balance: ' . $result['balance'] : ''));
        }

        return back()->with('error', $result['message'] ?? 'Connection test failed.');
    }

    /** Retrieve sender IDs from the active provider. */
    public function senderIds(): Response
    {
        $result = $this->messageService->getSenderIds();

        return Inertia::render('Admin/Sms/SenderIds', [
            'sender_ids' => $result['senderIds'] ?? [],
            'error' => $result['error'] ?? null,
        ]);
    }

    /** Check SMS balance. */
    public function balance(): Response
    {
        $result = $this->messageService->checkBalance();

        return Inertia::render('Admin/Sms/Balance', [
            'balance' => $result['balance'] ?? null,
            'currency' => $result['currency'] ?? null,
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Strip credential values that must not overwrite an existing encrypted key.
     *
     * Empty values mean "keep the existing credential". A masked value
     * (e.g. "****1234") submitted by the frontend is never written back,
     * so it cannot accidentally destroy the real key.
     */
    protected function sanitizeCredentialUpdate(array $validated, SmsProviderConfig $config): array
    {
        $credentialFields = ['api_key', 'api_secret', 'password'];

        foreach ($credentialFields as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = $validated[$field];

            if ($value === null || $value === '') {
                unset($validated[$field]);

                continue;
            }

            if (is_string($value) && str_contains($value, '*')) {
                unset($validated[$field]);
            }
        }

        return $validated;
    }

    protected function authorizeManageSettings(?\App\Models\User $user): void
    {
        if (! $user || ! $this->messageService->canManageSettings($user)) {
            abort(403, 'Only administrators can manage SMS provider settings.');
        }
    }
}

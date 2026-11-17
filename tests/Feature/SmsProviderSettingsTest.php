<?php

namespace Tests\Feature;

use App\Models\SmsProviderConfig;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin SMS provider settings create/update tests.
 *
 * No live SMS is sent. Credential masking and preservation are verified at
 * the HTTP layer.
 */
class SmsProviderSettingsTest extends TestCase
{
    use RefreshDatabase;
    public function test_admin_can_create_provider_configuration(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $response = $this->actingAs($admin)
            ->post('/sms/admin/providers', [
                'name' => 'DARSMS Production',
                'provider_type' => 'darsms',
                'base_url' => 'https://dev.darsms.co.tz/api/v1',
                'auth_header_name' => 'X-API-Key',
                'api_key' => 'live-api-key-123',
                'api_secret' => 'live-secret',
                'sender_id' => 'DARSMS',
                'is_active' => true,
                'timeout_seconds' => 30,
            ]);

        $response->assertSessionHas('success');
        $response->assertRedirect('/');

        $this->assertDatabaseHas('sms_provider_configs', [
            'name' => 'DARSMS Production',
            'provider_type' => 'darsms',
            'sender_id' => 'DARSMS',
            'is_active' => true,
        ]);

        $config = SmsProviderConfig::where('name', 'DARSMS Production')->first();
        $this->assertNotNull($config);
        $this->assertEquals('live-api-key-123', $config->decryptedApiKey());
        $this->assertEquals('live-secret', $config->decryptedApiSecret());
        $this->assertTrue($config->hasCredentials());
    }

    public function test_admin_can_edit_existing_provider_without_losing_api_key(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $config = SmsProviderConfig::create([
            'name' => 'Existing Provider',
            'provider_type' => 'darsms',
            'base_url' => 'https://dev.darsms.co.tz/api/v1',
            'sender_id' => 'DARSMS',
            'is_active' => false,
        ]);
        $config->setApiKeyAttribute('original-api-key-keep-me');
        $config->setApiSecretAttribute('original-secret');
        $config->save();

        $response = $this->actingAs($admin)
            ->put('/sms/admin/providers/'.$config->id, [
                'name' => 'Existing Provider (Renamed)',
                'base_url' => 'https://dev.darsms.co.tz/api/v1',
                'auth_header_name' => 'X-API-Key',
                // api_key left empty: must preserve the existing encrypted key.
                'api_key' => '',
                'api_secret' => '',
                'sender_id' => 'DARSMS',
                'is_active' => true,
                'timeout_seconds' => 30,
            ]);

        $response->assertSessionHas('success');

        $config->refresh();

        // Name and sender updated.
        $this->assertEquals('Existing Provider (Renamed)', $config->name);
        $this->assertTrue($config->is_active);

        // Original credentials preserved when fields were empty.
        $this->assertEquals('original-api-key-keep-me', $config->decryptedApiKey());
        $this->assertEquals('original-secret', $config->decryptedApiSecret());
    }

    public function test_masked_api_key_never_overwrites_real_credential(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $config = SmsProviderConfig::create([
            'name' => 'Masked Provider',
            'provider_type' => 'darsms',
            'base_url' => 'https://dev.darsms.co.tz/api/v1',
            'sender_id' => 'DARSMS',
            'is_active' => false,
        ]);
        $config->setApiKeyAttribute('real-api-key-do-not-overwrite');
        $config->save();

        $this->actingAs($admin)
            ->put('/sms/admin/providers/'.$config->id, [
                'name' => 'Masked Provider',
                'base_url' => 'https://dev.darsms.co.tz/api/v1',
                'api_key' => '****do-not-overwrite', // masked value submitted by frontend
                'sender_id' => 'DARSMS',
                'is_active' => false,
            ]);

        $config->refresh();

        $this->assertEquals('real-api-key-do-not-overwrite', $config->decryptedApiKey());
    }

    public function test_updating_api_key_replaces_existing_key(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $config = SmsProviderConfig::create([
            'name' => 'Key Replacement Provider',
            'provider_type' => 'darsms',
            'base_url' => 'https://dev.darsms.co.tz/api/v1',
            'sender_id' => 'DARSMS',
            'is_active' => false,
        ]);
        $config->setApiKeyAttribute('old-key');
        $config->save();

        $this->actingAs($admin)
            ->put('/sms/admin/providers/'.$config->id, [
                'name' => 'Key Replacement Provider',
                'base_url' => 'https://dev.darsms.co.tz/api/v1',
                'api_key' => 'new-key-replaces-old',
                'sender_id' => 'DARSMS',
                'is_active' => false,
            ]);

        $config->refresh();

        $this->assertEquals('new-key-replaces-old', $config->decryptedApiKey());
    }

    public function test_head_of_school_cannot_create_provider_configuration(): void
    {
        $hos = $this->createUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($hos)
            ->post('/sms/admin/providers', [
                'name' => 'HoS Provider',
                'provider_type' => 'darsms',
                'base_url' => 'https://dev.darsms.co.tz/api/v1',
                'api_key' => 'some-key',
                'sender_id' => 'DARSMS',
            ]);

        $response->assertStatus(302);
    }

    public function test_validation_errors_are_returned_for_invalid_provider(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $response = $this->actingAs($admin)
            ->post('/sms/admin/providers', [
                'name' => '',
                'provider_type' => 'invalid',
                'base_url' => 'not-a-url',
            ]);

        $response->assertSessionHasErrors(['name', 'provider_type', 'base_url']);
    }

    public function test_switching_active_provider_deactivates_previous(): void
    {
        $admin = $this->createUser(UserRole::Admin);

        $previous = SmsProviderConfig::create([
            'name' => 'Previous Active',
            'provider_type' => 'darsms',
            'base_url' => 'https://dev.darsms.co.tz/api/v1',
            'sender_id' => 'PREV',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post('/sms/admin/providers', [
                'name' => 'New Active',
                'provider_type' => 'darsms',
                'base_url' => 'https://dev.darsms.co.tz/api/v1',
                'api_key' => 'new-active-key',
                'sender_id' => 'NEW',
                'is_active' => true,
            ]);

        $response->assertSessionHas('success');

        $previous->refresh();
        $this->assertFalse($previous->is_active);

        $new = SmsProviderConfig::where('name', 'New Active')->first();
        $this->assertTrue($new->is_active);
    }

    protected function createUser(UserRole $role): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'test-'.strtolower($role->value).'@godwin.ac.tz',
            'password' => Hash::make('password'),
            'role' => $role->value,
            'status' => 'active',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SmsTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the SmsTemplateService::all() array/Collection bug.
 *
 * Covers GET /sms/templates and GET /sms/results for head_of_school and admin,
 * empty template lists, and active/inactive filtering.
 */
class SmsTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $userSeq = 0;

    private function makeUser(UserRole $role): User
    {
        $this->userSeq++;

        return User::create([
            'name' => 'Test User',
            'email' => "test.{$this->userSeq}.{$role->value}@godwin.ac.tz",
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function makeTemplate(array $attributes = []): SmsTemplate
    {
        return SmsTemplate::create(array_merge([
            'name' => 'Test Template',
            'category' => 'announcements',
            'template' => 'Hello {parent_name}',
            'is_active' => true,
            'created_by' => null,
        ], $attributes));
    }

    /** @test */
    public function head_of_school_can_list_templates(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);
        $this->makeTemplate(['name' => 'Announcement Template']);
        $this->makeTemplate(['name' => 'Fee Reminder', 'category' => 'fee_reminders']);

        $response = $this->actingAs($user)->get('/sms/templates');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert
            ->component('Sms/Templates')
            ->where('can_manage', true)
        );

        $templates = $response->inertiaProps('templates');
        $this->assertCount(2, $templates);
        $this->assertEquals('Announcement Template', $templates[0]['name']);
    }

    /** @test */
    public function admin_can_list_templates(): void
    {
        $user = $this->makeUser(UserRole::Admin);
        $this->makeTemplate(['name' => 'Admin Template']);

        $response = $this->actingAs($user)->get('/sms/templates');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert
            ->component('Sms/Templates')
        );

        $templates = $response->inertiaProps('templates');
        $this->assertCount(1, $templates);
    }

    /** @test */
    public function templates_endpoint_returns_empty_list_when_no_templates(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)->get('/sms/templates');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert
            ->component('Sms/Templates')
        );

        $this->assertCount(0, $response->inertiaProps('templates'));
    }

    /** @test */
    public function head_of_school_can_list_results(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);
        $this->makeTemplate(['name' => 'Results Template', 'category' => 'results']);

        $response = $this->actingAs($user)->get('/sms/results');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert
            ->component('Sms/Results')
            ->has('default_template')
        );

        $templates = $response->inertiaProps('templates');
        $this->assertCount(1, $templates);
    }

    /** @test */
    public function admin_can_list_results(): void
    {
        $user = $this->makeUser(UserRole::Admin);
        $this->makeTemplate(['name' => 'Admin Results Template', 'category' => 'results']);

        $response = $this->actingAs($user)->get('/sms/results');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert
            ->component('Sms/Results')
        );

        $this->assertCount(1, $response->inertiaProps('templates'));
    }

    /** @test */
    public function results_endpoint_returns_empty_templates_when_none_exist(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)->get('/sms/results');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert
            ->component('Sms/Results')
        );

        $this->assertCount(0, $response->inertiaProps('templates'));
    }

    /** @test */
    public function templates_endpoint_includes_active_and_inactive_templates(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);
        $this->makeTemplate(['name' => 'Active Template', 'is_active' => true]);
        $this->makeTemplate(['name' => 'Inactive Template', 'is_active' => false]);

        // /sms/templates calls all(false), so it must include inactive templates.
        $response = $this->actingAs($user)->get('/sms/templates');

        $response->assertStatus(200);
        $this->assertCount(2, $response->inertiaProps('templates'));
    }

    /** @test */
    public function templates_have_expected_fields_including_creator(): void
    {
        $creator = $this->makeUser(UserRole::HeadOfSchool);
        $this->makeTemplate([
            'name' => 'Full Template',
            'category' => 'attendance',
            'template' => 'Dear {parent_name}',
            'is_active' => true,
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))->get('/sms/templates');

        $response->assertStatus(200);
        $templates = $response->inertiaProps('templates');

        $this->assertCount(1, $templates);
        $this->assertEquals('Full Template', $templates[0]['name']);
        $this->assertEquals('attendance', $templates[0]['category']);
        $this->assertEquals('Dear {parent_name}', $templates[0]['template']);
        $this->assertTrue($templates[0]['is_active']);
        $this->assertEquals($creator->name, $templates[0]['created_by']);
        $this->assertNotNull($templates[0]['created_at']);
    }
}

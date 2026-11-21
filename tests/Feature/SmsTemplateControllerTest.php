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

    /** @test */
    public function head_of_school_can_create_template(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)
            ->postJson('/sms/templates', [
                'name' => 'Fee Reminder',
                'category' => 'fee_reminders',
                'template' => 'Dear {parent_name}, {student_name} has a balance of {amount}.',
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Fee Reminder', $response->json('template.name'));
        $this->assertTrue($response->json('template.is_active'));

        $this->assertDatabaseHas('sms_templates', [
            'name' => 'Fee Reminder',
            'category' => 'fee_reminders',
            'template' => 'Dear {parent_name}, {student_name} has a balance of {amount}.',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function create_template_validates_required_fields(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)
            ->postJson('/sms/templates', [
                'name' => '',
                'category' => '',
                'template' => '',
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response->json());
        $this->assertArrayHasKey('name', $response->json('errors'));
        $this->assertArrayHasKey('category', $response->json('errors'));
        $this->assertArrayHasKey('template', $response->json('errors'));
    }

    /** @test */
    public function create_template_rejects_invalid_variables(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)
            ->postJson('/sms/templates', [
                'name' => 'Bad Template',
                'category' => 'general',
                'template' => 'Hello {unknown_var} and {parent_name}',
            ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertArrayHasKey('template', $response->json('errors'));
    }

    /** @test */
    public function head_of_school_can_update_template(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);
        $template = $this->makeTemplate(['name' => 'Original', 'created_by' => $user->id]);

        $response = $this->actingAs($user)
            ->putJson('/sms/templates/'.$template->id, [
                'name' => 'Renamed',
                'category' => 'announcements',
                'template' => 'Updated {school_name}',
                'is_active' => false,
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Renamed', $response->json('template.name'));
        $this->assertFalse($response->json('template.is_active'));

        $this->assertDatabaseHas('sms_templates', [
            'id' => $template->id,
            'name' => 'Renamed',
            'is_active' => false,
        ]);
    }

    /** @test */
    public function head_of_school_can_toggle_template(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);
        $template = $this->makeTemplate(['is_active' => true]);

        $response = $this->actingAs($user)
            ->patchJson('/sms/templates/'.$template->id.'/toggle');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertFalse($response->json('template.is_active'));

        // Toggle back on.
        $this->actingAs($user)
            ->patchJson('/sms/templates/'.$template->id.'/toggle');
        $this->assertDatabaseHas('sms_templates', [
            'id' => $template->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function head_of_school_can_delete_template(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);
        $template = $this->makeTemplate();

        $response = $this->actingAs($user)
            ->deleteJson('/sms/templates/'.$template->id);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertDatabaseHas('sms_templates', [
            'id' => $template->id,
        ]);
        $this->assertNotNull($template->refresh()->deleted_at);
    }

    /** @test */
    public function teacher_cannot_manage_templates(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        // The role middleware redirects unauthorised roles (existing behaviour).
        $this->actingAs($teacher)
            ->post('/sms/templates', [
                'name' => 'X',
                'category' => 'general',
                'template' => 'Hi',
            ])
            ->assertStatus(302);

        $template = $this->makeTemplate();
        $this->actingAs($teacher)
            ->patch('/sms/templates/'.$template->id.'/toggle')
            ->assertStatus(302);

        $this->actingAs($teacher)
            ->delete('/sms/templates/'.$template->id)
            ->assertStatus(302);
    }
}

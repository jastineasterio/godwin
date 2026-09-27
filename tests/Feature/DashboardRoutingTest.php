<?php

namespace Tests\Feature;

use App\Enums\ClassLevel;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 2 — frontend/routing coverage:
 *  • the public website renders (Home, Apply)
 *  • guests cannot reach the dashboard
 *  • each of the six RBAC roles gets ITS OWN dashboard page + data
 *  • the parent portal switches children without re-login
 */
class DashboardRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function userFor(UserRole $role, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Test '.ucfirst($role->value),
            'email' => $role->value.'@godwin.ac.tz',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ], $extra));
    }

    /* ------------------------------------------------------------------ */
    /* Public website */
    /* ------------------------------------------------------------------ */

    public function test_public_home_page_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Home')
                ->has('banners')
                ->has('news')
                ->has('events')
            );
    }

    public function test_apply_form_renders_with_classes(): void
    {
        SchoolClass::create([
            'name' => 'KG1 Sunshine', 'code' => 'KG1-A',
            'level' => ClassLevel::KG1, 'is_active' => true,
        ]);

        $this->get('/apply')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Apply')
                ->has('classes', 1)
            );
    }

    public function test_application_form_stores_submission(): void
    {
        $class = SchoolClass::create([
            'name' => 'Nursery B', 'code' => 'NUR-B',
            'level' => ClassLevel::Nursery, 'is_active' => true,
        ]);

        $this->post('/apply', [
            'parent_name' => 'Mama Salma', 'parent_phone' => '0757333444',
            'relationship' => 'mother', 'address' => 'Medeli, Dodoma',
            'child_first_name' => 'Zainabu', 'child_last_name' => 'Said',
            'gender' => 'female', 'class_id' => $class->id,
        ])->assertRedirect('/apply')->assertSessionHas('success');

        $this->assertDatabaseHas('applications', [
            'child_first_name' => 'Zainabu',
            'parent_phone' => '0757333444',
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    /* ------------------------------------------------------------------ */
    /* The six role dashboards */
    /* ------------------------------------------------------------------ */

    public static function roleProvider(): array
    {
        return [
            'administrator' => [UserRole::Admin, 'Dashboards/Admin'],
            'senior pastor' => [UserRole::SeniorPastor, 'Dashboards/SeniorPastor'],
            'head of school' => [UserRole::HeadOfSchool, 'Dashboards/HeadOfSchool'],
            'teacher' => [UserRole::Teacher, 'Dashboards/Teacher'],
            'accountant' => [UserRole::Accountant, 'Dashboards/Accountant'],
            'parent' => [UserRole::Parent, 'Dashboards/Parent'],
        ];
    }

    /**
     * @dataProvider roleProvider
     */
    public function test_each_role_receives_its_own_dashboard(UserRole $role, string $component): void
    {
        $user = $this->userFor($role);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->has('stats')
                ->has('charts')
            );
    }

    /* ------------------------------------------------------------------ */
    /* Parent multi-child portal */
    /* ------------------------------------------------------------------ */

    public function test_parent_sees_and_switches_between_children(): void
    {
        $parent = $this->userFor(UserRole::Parent);

        $kg2 = SchoolClass::create([
            'name' => 'KG2 Stars', 'code' => 'KG2-A', 'level' => ClassLevel::KG2, 'is_active' => true,
        ]);
        $nursery = SchoolClass::create([
            'name' => 'Nursery B', 'code' => 'NUR-B', 'level' => ClassLevel::Nursery, 'is_active' => true,
        ]);

        $john = Student::create([
            'reg_no' => 'GWD-2026-0001', 'first_name' => 'John', 'last_name' => 'Juma',
            'gender' => Gender::Male, 'class_id' => $kg2->id, 'status' => 'active',
        ]);
        $sarah = Student::create([
            'reg_no' => 'GWD-2026-0002', 'first_name' => 'Sarah', 'last_name' => 'Juma',
            'gender' => Gender::Female, 'class_id' => $nursery->id, 'status' => 'active',
        ]);

        $parent->students()->attach($john->id, ['relationship_type' => 'father', 'is_primary_contact' => true]);
        $parent->students()->attach($sarah->id, ['relationship_type' => 'father', 'is_primary_contact' => true]);

        // Both children are listed in the switcher
        $this->actingAs($parent)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Parent')
                ->has('children', 2)
                ->has('profile')
                ->has('invoices')
            );

        // Switching via ?child= re-renders for the other child (no re-login)
        $this->actingAs($parent)
            ->get('/dashboard?child='.$sarah->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Parent')
                ->where('activeChildId', $sarah->id)
                ->where('profile.name', 'Sarah Juma')
            );
    }

    /* ------------------------------------------------------------------ */
    /* Authentication */
    /* ------------------------------------------------------------------ */

    public function test_login_page_renders_and_login_redirects_to_dashboard(): void
    {
        $this->get('/login')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));

        $admin = $this->userFor(UserRole::Admin, ['password' => 'secret123']);

        $this->post('/login', [
            'email' => $admin->email, 'password' => 'secret123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $admin = $this->userFor(UserRole::Admin, ['password' => 'secret123']);

        $this->post('/login', [
            'email' => $admin->email, 'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        $user = $this->userFor(UserRole::Teacher, [
            'password' => 'secret123', 'status' => 'suspended',
        ]);

        $this->post('/login', [
            'email' => $user->email, 'password' => 'secret123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FacilitiesTest extends TestCase
{
    use RefreshDatabase;

    private int $userSeq = 0;

    private function userFor(UserRole $role, array $extra = []): User
    {
        $this->userSeq++;

        return User::create(array_merge([
            'name' => 'Facilities '.ucfirst($role->value),
            'email' => 'facilities_'.$this->userSeq.'_'.$role->value.'@godwin.ac.tz',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ], $extra));
    }

    /* ------------------------------------------------------------------ */
    /* Access                                                             */
    /* ------------------------------------------------------------------ */

    public function test_facilities_loads_for_head_of_school_and_admin(): void
    {
        foreach ([UserRole::HeadOfSchool, UserRole::Admin] as $role) {
            $user = $this->userFor($role);
            $this->actingAs($user)
                ->get('/facilities')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Facilities/Index')
                    ->has('assets')
                    ->has('summary')
                    ->has('filters')
                    ->has('categories')
                    ->has('conditions')
                    ->has('statuses'));
        }
    }

    public function test_unauthorized_roles_cannot_access_facilities(): void
    {
        foreach ([UserRole::Teacher, UserRole::Accountant, UserRole::Parent] as $role) {
            $user = $this->userFor($role);
            $this->actingAs($user)->get('/facilities')->assertRedirect('/dashboard');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Empty + populated states                                           */
    /* ------------------------------------------------------------------ */

    public function test_empty_asset_list_renders(): void
    {
        $head = $this->userFor(UserRole::HeadOfSchool);

        $this->actingAs($head)
            ->get('/facilities')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facilities/Index')
                ->where('assets.data', [])
                ->where('summary.total_assets', 0));
    }

    public function test_existing_assets_render(): void
    {
        $head = $this->userFor(UserRole::HeadOfSchool);
        Asset::create([
            'name' => 'Desk', 'category' => 'furniture', 'quantity' => 2,
            'condition' => 'good', 'status' => 'active', 'location' => 'Class A',
            'recorded_by' => $head->id,
        ]);

        $this->actingAs($head)
            ->get('/facilities')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facilities/Index')
                ->has('assets.data', 1)
                ->where('summary.total_assets', 1));
    }
}

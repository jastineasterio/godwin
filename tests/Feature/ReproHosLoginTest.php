<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReproHosLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_of_school_full_flow(): void
    {
        $user = User::create([
            'name' => 'Mrs. Grace Mushi',
            'email' => 'head@godwin.ac.tz',
            'password' => bcrypt('password'),
            'role' => UserRole::HeadOfSchool,
            'status' => 'active',
        ]);

        $this->get('/login');

        $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'head@godwin.ac.tz',
            'password' => 'password',
        ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/HeadOfSchool')
                ->has('stats')
                ->has('charts')
            );
    }
}

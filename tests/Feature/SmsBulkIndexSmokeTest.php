<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\SmsProviderConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Temporary smoke test for the /sms/bulk index route. */
class SmsBulkIndexSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_index_returns_correct_component_and_props(): void
    {
        $user = User::create([
            'name' => 'HoS',
            'email' => 'hos-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::HeadOfSchool,
            'status' => 'active',
        ]);

        SchoolClass::create([
            'name' => 'KG1',
            'code' => 'KG1',
            'level' => \App\Enums\ClassLevel::KG1,
            'is_active' => true,
            'capacity' => 25,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->get('/sms/bulk');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert
            ->component('Sms/Bulk')
            ->where('can_send', true)
            ->has('classes', 1)
            ->has('templates', 0)
        );
    }
}

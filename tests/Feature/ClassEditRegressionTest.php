<?php

namespace Tests\Feature;

use App\Enums\ClassLevel;
use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the /classes/{class}/edit page (Issue 1).
 */
class ClassEditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::Admin,
            'status' => 'active',
        ]);
    }

    private function teacher(): User
    {
        return User::create([
            'name' => 'Teacher',
            'email' => 'teacher-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::Teacher,
            'status' => 'active',
        ]);
    }

    /** The edit page returns the Classes/Edit component with the class + reference props. */
    public function test_class_edit_returns_correct_component_and_props(): void
    {
        $class = SchoolClass::create([
            'name' => 'Daycare Little Stars',
            'code' => 'DC-A',
            'level' => ClassLevel::Daycare,
            'teacher_id' => null,
            'capacity' => 25,
            'description' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->get('/classes/'.$class->id.'/edit');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert
            ->component('Classes/Edit')
            ->where('class.id', $class->id)
            ->where('class.name', 'Daycare Little Stars')
            ->where('class.code', 'DC-A')
            ->where('class.level', 'daycare')
            ->where('class.teacher_id', null)
            ->where('class.capacity', 25)
            ->where('class.description', null)
            ->where('class.is_active', true)
            ->has('teachers')
            ->has('levels')
        );
    }

    /** Updating a class persists changes without creating a duplicate record. */
    public function test_class_update_saves_changes_without_duplicating(): void
    {
        $class = SchoolClass::create([
            'name' => 'Daycare Little Stars',
            'code' => 'DC-A',
            'level' => ClassLevel::Daycare,
            'teacher_id' => null,
            'capacity' => 25,
            'description' => null,
            'is_active' => true,
        ]);

        $before = SchoolClass::count();

        $response = $this->actingAs($this->admin())
            ->put('/classes/'.$class->id, [
                'name' => 'Updated Class',
                'code' => 'DC-B',
                'level' => 'nursery',
                'teacher_id' => '',
                'capacity' => 30,
                'description' => 'Updated description.',
            ]);

        $response->assertRedirect('/classes/'.$class->id);
        $this->assertSame($before, SchoolClass::count(), 'No duplicate class should be created.');
        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'name' => 'Updated Class',
            'code' => 'DC-B',
            'level' => 'nursery',
            'capacity' => 30,
            'description' => 'Updated description.',
        ]);
        $this->assertDatabaseMissing('classes', [
            'id' => $class->id,
            'name' => 'Daycare Little Stars',
        ]);
    }

    /** Invalid input is rejected on the backend with clear validation errors. */
    public function test_class_update_rejects_invalid_input(): void
    {
        $class = SchoolClass::create([
            'name' => 'Daycare Little Stars',
            'code' => 'DC-A',
            'level' => ClassLevel::Daycare,
            'capacity' => 25,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->put('/classes/'.$class->id, [
                'name' => '',
                'code' => '',
                'level' => 'not-a-level',
                'capacity' => 0,
            ]);

        $response->assertSessionHasErrors(['name', 'code', 'level', 'capacity']);
    }

    /** A teacher is not permitted to access the class edit page. */
    public function test_teacher_cannot_access_class_edit(): void
    {
        $class = SchoolClass::create([
            'name' => 'Daycare Little Stars',
            'code' => 'DC-A',
            'level' => ClassLevel::Daycare,
            'capacity' => 25,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->teacher())
            ->get('/classes/'.$class->id.'/edit');

        $response->assertStatus(302);
    }
}

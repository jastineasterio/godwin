<?php

namespace Tests\Feature;

use App\Enums\ClassLevel;
use App\Enums\Gender;
use App\Enums\TimetableDay;
use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timetable role-based access tests.
 *
 * Covers:
 *  - Teacher sees only their own timetable entries
 *  - Teacher cannot see another teacher's entries
 *  - Teacher cannot bypass teacher scoping via class_id
 *  - Head of School and Admin see all timetable entries
 *  - Parent sees timetable for linked children only
 *  - Unauthorized roles are blocked
 */
class TimetableTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------- Fixtures ---------------------------- */

    private function makeUser(UserRole $role, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst($role->value) . ' User',
            'email' => $role->value . '-' . uniqid() . '@godwin.ac.tz',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ], $extra));
    }

    private function makeClass(?User $teacher = null, string $code = 'KG1-A'): SchoolClass
    {
        return SchoolClass::create([
            'name' => 'KG1 Sunshine', 'code' => $code, 'level' => ClassLevel::KG1,
            'teacher_id' => $teacher?->id, 'is_active' => true, 'capacity' => 25,
        ]);
    }

    private function makeStudent(SchoolClass $class, string $name = 'Amina'): Student
    {
        return Student::create([
            'reg_no' => 'GWD-' . uniqid(), 'first_name' => $name, 'last_name' => 'Juma',
            'gender' => Gender::Female, 'class_id' => $class->id, 'status' => 'active',
            'admission_date' => now()->subMonths(2),
        ]);
    }

    private function makeTimetable(SchoolClass $class, ?User $teacher = null, string $day = 'monday'): Timetable
    {
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON-' . uniqid(), 'is_active' => true]);

        return Timetable::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher?->id,
            'day' => $day,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => 'A1',
        ]);
    }

    /* ---------------------- Teacher-scoped viewing ---------------------- */

    public function test_teacher_sees_only_own_timetable_entries(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $otherTeacher = $this->makeUser(UserRole::Teacher, ['email' => 'other@godwin.ac.tz']);

        $ownClass = $this->makeClass($teacher, 'KG1-A');
        $otherClass = $this->makeClass($otherTeacher, 'NUR-B');

        $ownEntry = $this->makeTimetable($ownClass, $teacher);
        $otherEntry = $this->makeTimetable($otherClass, $otherTeacher);

        $response = $this->actingAs($teacher)
            ->get('/timetable')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('timetables.0.id', $ownEntry->id)
            );

        $this->actingAs($teacher)
            ->get('/timetable')
            ->assertInertia(fn ($page) => $page
                ->where('timetables', fn ($entries) => !collect($entries)->contains('id', $otherEntry->id))
            );
    }

    public function test_teacher_cannot_bypass_teacher_scoping_via_class_id(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $otherTeacher = $this->makeUser(UserRole::Teacher, ['email' => 'other@godwin.ac.tz']);

        $ownClass = $this->makeClass($teacher, 'KG1-A');
        $otherClass = $this->makeClass($otherTeacher, 'NUR-B');

        $ownEntry = $this->makeTimetable($ownClass, $teacher);
        $otherEntry = $this->makeTimetable($otherClass, $otherTeacher);

        // Supplying the other teacher's class ID must not surface that entry.
        $this->actingAs($teacher)
            ->get('/timetable?class_id=' . $otherClass->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('timetables', fn ($entries) => !collect($entries)->contains('id', $otherEntry->id))
                ->where('timetables', fn ($entries) => collect($entries)->contains('id', $ownEntry->id))
            );
    }

    /* ------------------ Head of School / Admin viewing ------------------ */

    public function test_head_of_school_sees_all_timetable_entries(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $teacher = $this->makeUser(UserRole::Teacher);

        $class = $this->makeClass($teacher, 'KG1-A');

        $entry1 = $this->makeTimetable($class, $teacher);
        $entry2 = $this->makeTimetable($class, null);

        $this->actingAs($hos)
            ->get('/timetable')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('timetables.0.id', $entry1->id)
                ->where('timetables.1.id', $entry2->id)
                ->where('canManage', true)
            );
    }

    public function test_admin_sees_all_timetable_entries(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $teacher = $this->makeUser(UserRole::Teacher);

        $class = $this->makeClass($teacher, 'KG1-A');

        $entry = $this->makeTimetable($class, $teacher);

        $this->actingAs($admin)
            ->get('/timetable')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('timetables.0.id', $entry->id)
                ->where('canManage', true)
            );
    }

    public function test_head_of_school_can_filter_by_class_id(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $teacher = $this->makeUser(UserRole::Teacher);

        $classA = $this->makeClass($teacher, 'KG1-A');
        $classB = $this->makeClass(null, 'NUR-B');

        $entryA = $this->makeTimetable($classA, $teacher);
        $this->makeTimetable($classB, null);

        $this->actingAs($hos)
            ->get('/timetable?class_id=' . $classA->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('timetables', fn ($entries) => collect($entries)->pluck('id')->contains($entryA->id))
                ->where('timetables', fn ($entries) => count($entries) === 1)
                ->where('activeClassId', $classA->id)
            );
    }

    /* --------------------------- Parent viewing ------------------------- */

    public function test_parent_sees_timetable_for_linked_children_only(): void
    {
        $parent = $this->makeUser(UserRole::Parent);
        $teacher = $this->makeUser(UserRole::Teacher);

        $childClass = $this->makeClass($teacher, 'KG1-A');
        $otherClass = $this->makeClass(null, 'NUR-B');

        $child = $this->makeStudent($childClass, 'Amina');
        $this->makeStudent($otherClass, 'Foreign');

        $parent->students()->attach($child->id);

        $ownEntry = $this->makeTimetable($childClass, $teacher);
        $otherEntry = $this->makeTimetable($otherClass, null);

        $this->actingAs($parent)
            ->get('/timetable')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('timetables.0.id', $ownEntry->id)
                ->where('timetables', fn ($entries) => !collect($entries)->contains('id', $otherEntry->id))
                ->where('canManage', false)
            );
    }

    public function test_parent_can_filter_by_own_childs_class(): void
    {
        $parent = $this->makeUser(UserRole::Parent);
        $teacher = $this->makeUser(UserRole::Teacher);

        $childClass = $this->makeClass($teacher, 'KG1-A');

        $child = $this->makeStudent($childClass, 'Amina');

        $parent->students()->attach($child->id);

        $this->makeTimetable($childClass, $teacher);

        $this->actingAs($parent)
            ->get('/timetable?class_id=' . $childClass->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeClassId', $childClass->id)
            );
    }

    /* ------------------------ Unauthorized roles ------------------------ */

    public function test_senior_pastor_cannot_access_timetable(): void
    {
        $pastor = $this->makeUser(UserRole::SeniorPastor);

        $this->actingAs($pastor)
            ->get('/timetable')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');
    }

    public function test_accountant_cannot_access_timetable(): void
    {
        $accountant = $this->makeUser(UserRole::Accountant);

        $this->actingAs($accountant)
            ->get('/timetable')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');
    }

    public function test_unauthenticated_user_cannot_access_timetable(): void
    {
        $this->get('/timetable')->assertRedirect('/login');
    }
}

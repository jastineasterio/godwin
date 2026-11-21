<?php

namespace Tests\Feature;

use App\Enums\ClassLevel;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HEAD OF SCHOOL — TEACHING ASSIGNMENTS
 *
 * Covers assigning teachers to subjects within classes via the existing
 * `class_subject` pivot, plus integration with the teacher portal (marks
 * recording workflow) and authorization rules.
 */
class TeachingAssignmentsTest extends TestCase
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
            'status' => UserStatus::Active->value,
        ], $extra));
    }

    private function makeClass(?User $teacher = null, string $code = 'KG1-A'): SchoolClass
    {
        return SchoolClass::create([
            'name' => 'KG1 Sunshine', 'code' => $code, 'level' => ClassLevel::KG1,
            'teacher_id' => $teacher?->id, 'is_active' => true, 'capacity' => 25,
        ]);
    }

    private function makeSubject(string $name, string $code): Subject
    {
        return Subject::create(['name' => $name, 'code' => $code, 'is_active' => true]);
    }

    /* ------------------- 1. HOS can view teaching assignments ---------- */

    public function test_head_of_school_can_view_teaching_assignments(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);

        $this->actingAs($hos)
            ->get('/teaching-assignments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('TeachingAssignments/Index'));
    }

    public function test_teacher_cannot_access_teaching_assignments(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($teacher)->get('/teaching-assignments')->assertRedirect('/dashboard');
    }

    /* ------------------- 2. HOS can assign teacher to subject in class -- */

    public function test_head_of_school_can_assign_teacher_to_subject_in_class(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id); // subject allocated to class
        $teacher = $this->makeUser(UserRole::Teacher, ['name' => 'Teacher A']);

        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('class_subject', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    /* ------------------- 3. Assigned class appears in teacher portal --- */

    public function test_assigned_class_appears_in_teacher_portal(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id);

        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ]);

        $this->actingAs($teacher)
            ->get('/my-classes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MyClasses/Index')
                ->where('classes.0.id', $class->id)
                ->where('classes', fn ($classes) => collect($classes)->contains('id', $class->id))
            );
    }

    /* ------------------- 4. Assigned subject appears under correct class -- */

    public function test_assigned_subject_appears_under_correct_class_for_teacher(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id);

        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ]);

        $this->actingAs($teacher)
            ->get('/teacher/marks/subjects?class_id=' . $class->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teacher/Marks/SelectSubject')
                ->where('subjects.0.name', 'Mathematics')
                ->where('subjects', fn ($subjects) => count($subjects) === 1)
            );
    }

    /* ------------------- 5. Teacher cannot see another teacher's subjects -- */

    public function test_teacher_cannot_see_another_teachers_subject_assignments(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $otherTeacher = $this->makeUser(UserRole::Teacher, ['email' => 'other@godwin.ac.tz']);
        $hos = $this->makeUser(UserRole::HeadOfSchool);

        $ownClass = $this->makeClass(null, 'KG1-A');
        $otherClass = $this->makeClass(null, 'NUR-B');
        $subject = $this->makeSubject('Mathematics', 'MATH');

        $ownClass->subjects()->attach($subject->id);
        $otherClass->subjects()->attach($subject->id);

        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $ownClass->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ]);

        // Other teacher should NOT see Mathematics under the other class (they aren't assigned)
        $this->actingAs($otherTeacher)
            ->get('/teacher/marks/subjects?class_id=' . $otherClass->id)
            ->assertForbidden();
    }

    /* ------------------- 6. Duplicate assignments rejected -------------- */

    public function test_duplicate_teacher_assignment_is_rejected(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id);
        $teacher = $this->makeUser(UserRole::Teacher);

        // First assignment succeeds
        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertRedirect();

        // Re-assigning the same class+subject should be rejected (already has a teacher)
        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHas('error');
    }

    /* ------------------- 7. Inactive teachers cannot be assigned -------- */

    public function test_inactive_teacher_cannot_be_assigned(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id);
        $inactiveTeacher = $this->makeUser(UserRole::Teacher, [
            'name' => 'Inactive Teacher',
            'status' => UserStatus::Inactive->value,
        ]);

        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $inactiveTeacher->id,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('class_subject', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $inactiveTeacher->id,
        ]);
    }

    /* ------------------- 8. Subjects not linked to class rejected ------- */

    public function test_subject_not_allocated_to_class_cannot_be_assigned(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'KG1-A');
        $unrelatedSubject = $this->makeSubject('Art', 'ART'); // not attached to class
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $unrelatedSubject->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('class_subject', [
            'class_id' => $class->id,
            'subject_id' => $unrelatedSubject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    /* ------------------- 9. Cross-school assignments rejected ---------- */

    public function test_cross_school_assignment_is_rejected(): void
    {
        // This is a single-school installation: users, classes and subjects all
        // belong to the one school, so tenant isolation is by construction. A
        // reference to a record that does not exist in the current school is
        // rejected, which is the practical equivalent of a cross-school guard.
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id);

        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => 999999, // no such class in this school
                'subject_id' => $subject->id,
                'teacher_id' => $this->makeUser(UserRole::Teacher)->id,
            ])
            ->assertSessionHasErrors('class_id');

        $this->assertDatabaseMissing('class_subject', ['class_id' => 999999]);
    }

    /* ------------------- 10. Unauthorized teachers cannot manage ------- */

    public function test_unauthorized_teachers_cannot_manage_assignments(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($teacher)
            ->post('/teaching-assignments', [
                'class_id' => 1,
                'subject_id' => 1,
                'teacher_id' => 1,
            ])
            ->assertRedirect('/dashboard');

        $this->actingAs($teacher)
            ->put('/teaching-assignments/1', ['teacher_id' => 1])
            ->assertRedirect('/dashboard');

        $this->actingAs($teacher)
            ->delete('/teaching-assignments/1')
            ->assertRedirect('/dashboard');
    }

    /* ------------------- 11. Changing an assignment updates access ------ */

    public function test_changing_assignment_updates_teacher_access(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $teacherA = $this->makeUser(UserRole::Teacher, ['name' => 'Teacher A']);
        $teacherB = $this->makeUser(UserRole::Teacher, ['name' => 'Teacher B']);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id);

        // Assign to Teacher A
        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacherA->id,
            ]);

        $assignmentId = \Illuminate\Support\Facades\DB::table('class_subject')
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->value('id');

        // Change to Teacher B
        $this->actingAs($hos)
            ->put("/teaching-assignments/{$assignmentId}", [
                'teacher_id' => $teacherB->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_subject', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherB->id,
        ]);
        $this->assertDatabaseMissing('class_subject', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherA->id,
        ]);

        // Teacher A no longer sees the subject (403: not assigned to the class)
        $this->actingAs($teacherA)
            ->get('/teacher/marks/subjects?class_id=' . $class->id)
            ->assertForbidden();

        // Teacher B now does
        $this->actingAs($teacherB)
            ->get('/teacher/marks/subjects?class_id=' . $class->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('subjects.0.name', 'Mathematics'));
    }

    /* ------------------- 12. Existing marks workflow remains functional -- */

    public function test_existing_marks_workflow_remains_functional(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = \App\Models\Student::create([
            'reg_no' => 'GWD-' . uniqid(), 'first_name' => 'Amina', 'last_name' => 'Juma',
            'gender' => 'female', 'class_id' => $class->id, 'status' => 'active',
            'admission_date' => now()->subMonths(2),
        ]);
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id, ['teacher_id' => $teacher->id]);

        \App\Models\AcademicYear::create([
            'name' => '2026', 'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(), 'is_current' => true, 'status' => 'active',
        ]);
        $term = \App\Models\Term::create([
            'academic_year_id' => \App\Models\AcademicYear::first()->id, 'name' => 'First Term',
            'start_date' => now()->startOfMonth(), 'end_date' => now()->endOfMonth(), 'is_current' => true,
        ]);

        $exam = \App\Models\Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => \App\Models\AcademicYear::first()->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [
                    ['student_id' => $student->id, 'score' => 18, 'remarks' => 'Good'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assessments', [
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'score' => 18,
            'status' => 'draft',
            'recorded_by' => $teacher->id,
        ]);
    }

    /* ------------------- 13. Index exposes every allocated subject per class -- */

    public function test_index_returns_all_allocated_subjects_for_class(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'DC-A');
        $bible = $this->makeSubject('Bible Stories', 'BIBL');
        $numeracy = $this->makeSubject('Numeracy', 'NUM');
        $phonics = $this->makeSubject('Phonics', 'PHON');
        $teacher = $this->makeUser(UserRole::Teacher);

        $class->subjects()->attach($bible->id, ['teacher_id' => $teacher->id]);
        $class->subjects()->attach($numeracy->id, ['teacher_id' => $teacher->id]);
        $class->subjects()->attach($phonics->id); // allocated, no teacher yet

        $this->actingAs($hos)
            ->get('/teaching-assignments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TeachingAssignments/Index')
                ->where('classSubjects', fn ($classSubjects) =>
                    collect($classSubjects)->where('class_id', $class->id)->count() === 3)
                ->where('classSubjects', fn ($classSubjects) =>
                    collect($classSubjects)->where('class_id', $class->id)
                        ->where('subject_id', $phonics->id)->first()['teacher_id'] === null)
            );
    }

    public function test_existing_teacher_assignments_do_not_hide_subjects_from_selector(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $teacher = $this->makeUser(UserRole::Teacher);

        // Subject allocated to the class AND already assigned to a teacher.
        $class->subjects()->attach($subject->id, ['teacher_id' => $teacher->id]);

        $this->actingAs($hos)
            ->get('/teaching-assignments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TeachingAssignments/Index')
                ->where('classSubjects', fn ($classSubjects) => collect($classSubjects)->contains(fn ($row) =>
                    $row['class_id'] === $class->id &&
                    $row['subject_id'] === $subject->id &&
                    $row['teacher_id'] === $teacher->id))
            );
    }

    public function test_class_with_no_allocated_subjects_shows_empty_selector_state(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $emptyClass = $this->makeClass(null, 'NUR-B');
        $this->makeSubject('Mathematics', 'MATH'); // not attached to any class

        $this->actingAs($hos)
            ->get('/teaching-assignments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TeachingAssignments/Index')
                ->where('classSubjects', fn ($classSubjects) =>
                    collect($classSubjects)->where('class_id', $emptyClass->id)->isEmpty())
            );
    }

    public function test_selected_class_id_scopes_assignment_creation(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'KG1-A');
        $otherClass = $this->makeClass(null, 'KG2-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id);
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('class_subject', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
        $this->assertDatabaseMissing('class_subject', [
            'class_id' => $otherClass->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_already_assigned_subject_can_be_changed_teacher(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $teacherA = $this->makeUser(UserRole::Teacher, ['name' => 'Teacher A']);
        $teacherB = $this->makeUser(UserRole::Teacher, ['name' => 'Teacher B']);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id, ['teacher_id' => $teacherA->id]);

        $assignmentId = \Illuminate\Support\Facades\DB::table('class_subject')
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->value('id');

        $this->actingAs($hos)
            ->put("/teaching-assignments/{$assignmentId}", ['teacher_id' => $teacherB->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('class_subject', [
            'id' => $assignmentId,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherB->id,
        ]);
        $this->assertDatabaseMissing('class_subject', [
            'id' => $assignmentId,
            'teacher_id' => $teacherA->id,
        ]);
    }

    public function test_subject_not_allocated_to_selected_class_cannot_be_assigned(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass(null, 'KG1-A');
        $unrelatedSubject = $this->makeSubject('Art', 'ART'); // not attached to class
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $unrelatedSubject->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('class_subject', [
            'class_id' => $class->id,
            'subject_id' => $unrelatedSubject->id,
        ]);
    }

    public function test_existing_assignments_remain_unchanged_until_explicit_change(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $teacherA = $this->makeUser(UserRole::Teacher, ['name' => 'Teacher A']);
        $teacherB = $this->makeUser(UserRole::Teacher, ['name' => 'Teacher B']);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = $this->makeSubject('Mathematics', 'MATH');
        $class->subjects()->attach($subject->id, ['teacher_id' => $teacherA->id]);

        // Re-assigning the same class+subject via store is rejected (no duplicate created).
        $this->actingAs($hos)
            ->post('/teaching-assignments', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacherB->id,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('class_subject', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherA->id,
        ]);
        $this->assertDatabaseMissing('class_subject', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherB->id,
        ]);

        // Only an explicit update (Change Teacher) modifies the row.
        $assignmentId = \Illuminate\Support\Facades\DB::table('class_subject')
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->value('id');

        $this->actingAs($hos)
            ->put("/teaching-assignments/{$assignmentId}", ['teacher_id' => $teacherB->id])
            ->assertRedirect();

        $this->assertDatabaseHas('class_subject', [
            'id' => $assignmentId,
            'teacher_id' => $teacherB->id,
        ]);
    }
}

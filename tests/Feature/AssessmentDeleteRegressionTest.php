<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Examination;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for assessment update (PUT) and delete (DELETE) (Issue 3).
 */
class AssessmentDeleteRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function hos(): User
    {
        return User::create([
            'name' => 'HoS',
            'email' => 'hos-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::HeadOfSchool,
            'status' => 'active',
        ]);
    }

    private function teacherOfClass(SchoolClass $class): User
    {
        return User::create([
            'name' => 'Teacher',
            'email' => 'teacher-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::Teacher,
            'status' => 'active',
        ])->tap(fn ($u) => $class->update(['teacher_id' => $u->id]));
    }

    private function draftAssessment(array $overrides = []): Assessment
    {
        $class = SchoolClass::create([
            'name' => 'KG1',
            'code' => 'KG1',
            'level' => \App\Enums\ClassLevel::KG1,
            'is_active' => true,
            'capacity' => 25,
        ]);
        $student = Student::create([
            'reg_no' => 'GWD-'.uniqid(),
            'first_name' => 'Amina',
            'last_name' => 'Juma',
            'gender' => 'female',
            'class_id' => $class->id,
            'status' => 'active',
        ]);
        $subject = Subject::create([
            'name' => 'Phonics',
            'code' => 'PHON',
            'is_active' => true,
        ]);
        $academicYear = AcademicYear::create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
            'status' => 'active',
        ]);
        $term = Term::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Term 1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-30',
            'is_current' => true,
        ]);

        return Assessment::create(array_merge([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'title' => 'Phonics Continuous Assessment',
            'type' => AssessmentType::Test,
            'score' => 15.00,
            'max_score' => 20.00,
            'status' => 'draft',
            'recorded_by' => $this->hos()->id,
            'obtained_at' => '2026-02-10',
        ], $overrides));
    }

    /** PUT update still works for a draft assessment. */
    public function test_assessment_update_uses_put_and_succeeds(): void
    {
        $assessment = $this->draftAssessment();
        $user = $this->hos();

        $response = $this->actingAs($user)
            ->put('/assessments/'.$assessment->id, [
                'student_id' => $assessment->student_id,
                'title' => 'Updated Title',
                'type' => $assessment->type->value,
                'score' => 17,
                'max_score' => 20,
                'remarks' => 'Improved.',
            ]);

        $response->assertRedirect('/assessments');
        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'title' => 'Updated Title',
            'score' => 17.00,
            'remarks' => 'Improved.',
        ]);
    }

    /** DELETE removes a draft assessment when the user is assigned to its class. */
    public function test_assessment_delete_uses_delete_and_succeeds_when_permitted(): void
    {
        $assessment = $this->draftAssessment();
        $user = $this->hos();

        $response = $this->actingAs($user)
            ->delete('/assessments/'.$assessment->id);

        $response->assertStatus(302);
        $this->assertSoftDeleted('assessments', ['id' => $assessment->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assessment.deleted',
            'auditable_type' => Assessment::class,
        ]);
    }

    /** DELETE is blocked when the assessment is approved. */
    public function test_assessment_delete_is_blocked_when_approved(): void
    {
        $assessment = $this->draftAssessment(['status' => 'approved']);
        $user = $this->hos();

        $response = $this->actingAs($user)
            ->delete('/assessments/'.$assessment->id);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertNotSoftDeleted('assessments', ['id' => $assessment->id]);
    }

    /** DELETE is blocked when the assessment belongs to a closed examination. */
    public function test_assessment_delete_is_blocked_when_examination_is_closed(): void
    {
        $assessment = $this->draftAssessment();

        $academicYear = AcademicYear::create([
            'name' => 'Closed Exam Year '.uniqid(),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
            'status' => 'active',
        ]);
        $term = Term::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Term 1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-30',
            'is_current' => true,
        ]);
        $exam = Examination::create([
            'name' => 'Midterm Exam',
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'status' => 'closed',
        ]);

        $assessment->update(['examination_id' => $exam->id, 'status' => 'draft']);

        $response = $this->actingAs($this->hos())
            ->delete('/assessments/'.$assessment->id);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertNotSoftDeleted('assessments', ['id' => $assessment->id]);
    }

    /** A teacher not assigned to the class cannot delete the assessment. */
    public function test_teacher_not_assigned_to_class_cannot_delete_assessment(): void
    {
        $assessment = $this->draftAssessment();
        $otherTeacher = User::create([
            'name' => 'Other Teacher',
            'email' => 'other-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::Teacher,
            'status' => 'active',
        ]);

        $response = $this->actingAs($otherTeacher)
            ->delete('/assessments/'.$assessment->id);

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('assessments', ['id' => $assessment->id]);
    }
}

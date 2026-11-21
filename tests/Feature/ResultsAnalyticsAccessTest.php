<?php

namespace Tests\Feature;

use App\Enums\ClassLevel;
use App\Enums\Gender;
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
 * Feature coverage for school-wide, read-only Results & Analytics access by
 * Head of School and Senior Pastor, plus teacher restriction and auth guards.
 */
class ResultsAnalyticsAccessTest extends TestCase
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

    private function makeAcademicYear(string $name = '2026', bool $current = true): AcademicYear
    {
        return AcademicYear::create([
            'name' => $name, 'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(), 'is_current' => $current,
            'status' => 'active',
        ]);
    }

    private function makeTerm(AcademicYear $year, string $name = 'First Term'): Term
    {
        return Term::create([
            'academic_year_id' => $year->id, 'name' => $name,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(), 'is_current' => true,
        ]);
    }

    private function makeExamination(AcademicYear $year, Term $term, SchoolClass $class, Subject $subject, string $name = 'Mid Term'): Examination
    {
        $exam = Examination::create([
            'name' => $name, 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        return $exam;
    }

    private function makeResult(Examination $exam, SchoolClass $class, Subject $subject, Student $student, string $status = 'approved', float $score = 15): void
    {
        Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student->id,
            'class_id' => $class->id, 'subject_id' => $subject->id,
            'term_id' => $exam->term_id,
            'title' => $exam->name . ' - ' . $subject->name,
            'type' => 'exam', 'score' => $score, 'max_score' => 20,
            'recorded_by' => null,
            'status' => $status,
        ]);
    }

    /* ---------------- Head of School -------------------------------- */

    public function test_head_of_school_can_access_school_wide_analytics(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $classA = $this->makeClass();
        $classB = $this->makeClass(null, 'KG1-B');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = $this->makeExamination($year, $term, $classA, $subject);
        $exam->classes()->attach($classB->id);

        $studentA = $this->makeStudent($classA, 'Amina');
        $studentB = $this->makeStudent($classB, 'John');

        $this->makeResult($exam, $classA, $subject, $studentA, 'approved', 18);
        $this->makeResult($exam, $classB, $subject, $studentB, 'submitted', 12);

        $response = $this->actingAs($hos)->get('/results-analytics');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('is_read_only', true)
                ->where('user_role', 'head_of_school')
                ->where('overview.total_examinations', 1)
                ->where('overview.results_recorded', 2)
                ->where('overview.approved_results', 1)
                ->where('overview.submitted_awaiting_approval', 1)
                ->where('overview.class_average', 75) // (18/20 + 12/20) / 2 * 100
            );
    }

    public function test_senior_pastor_can_access_school_wide_analytics(): void
    {
        $pastor = $this->makeUser(UserRole::SeniorPastor);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $classA = $this->makeClass();
        $subject = Subject::create(['name' => 'Numeracy', 'code' => 'NUM', 'is_active' => true]);
        $exam = $this->makeExamination($year, $term, $classA, $subject);
        $student = $this->makeStudent($classA, 'Amina');
        $this->makeResult($exam, $classA, $subject, $student, 'approved', 20);

        $response = $this->actingAs($pastor)->get('/results-analytics');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('is_read_only', true)
                ->where('user_role', 'senior_pastor')
                ->where('overview.results_recorded', 1)
                ->where('overview.approved_results', 1)
            );
    }

    public function test_head_of_school_and_pastor_see_all_classes_and_subjects_in_filters(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $classA = $this->makeClass(null, 'KG1-A');
        $classB = $this->makeClass(null, 'KG1-B');
        $subjectA = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);
        $subjectB = Subject::create(['name' => 'Numeracy', 'code' => 'NUM', 'is_active' => true]);

        $response = $this->actingAs($user)->get('/results-analytics');

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.classes', fn ($classes) => count($classes) === 2
                && collect($classes)->pluck('name')->contains('KG1 Sunshine'))
            ->where('filters.subjects', fn ($subjects) => count($subjects) === 2
                && collect($subjects)->pluck('name')->contains('Phonics')
                && collect($subjects)->pluck('name')->contains('Numeracy'))
        );
    }

    public function test_leadership_can_filter_by_class_and_subject(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $classA = $this->makeClass(null, 'KG1-A');
        $classB = $this->makeClass(null, 'KG1-B');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = $this->makeExamination($year, $term, $classA, $subject);
        $exam->classes()->attach($classB->id);

        $studentA = $this->makeStudent($classA, 'Amina');
        $studentB = $this->makeStudent($classB, 'John');
        $this->makeResult($exam, $classA, $subject, $studentA, 'approved', 18);
        $this->makeResult($exam, $classB, $subject, $studentB, 'approved', 12);

        $response = $this->actingAs($hos)
            ->get('/results-analytics?class_id=' . $classB->id);

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->where('overview.results_recorded', 1)
            ->where('filters.selected.class_id', (string) $classB->id)
        );
    }

    /* ---------------- Read-only enforcement ------------------------- */

    public function test_head_of_school_cannot_edit_or_submit_results_through_teacher_routes(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);
        $exam = $this->makeExamination($year, $term, $class, $subject);

        // Leadership is not a teacher, so the teacher mark-entry routes reject them.
        $this->actingAs($hos)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [['student_id' => 1, 'score' => 90]],
            ])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($hos)
            ->post("/teacher/examinations/{$exam->id}/results/submit", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
            ])
            ->assertRedirect(route('dashboard'));
    }

    public function test_senior_pastor_cannot_edit_submit_approve_or_return_results(): void
    {
        $pastor = $this->makeUser(UserRole::SeniorPastor);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);
        $exam = $this->makeExamination($year, $term, $class, $subject);

        $this->actingAs($pastor)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [['student_id' => 1, 'score' => 90]],
            ])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($pastor)
            ->post("/teacher/examinations/{$exam->id}/results/submit", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
            ])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($pastor)
            ->post("/examinations/{$exam->id}/approve", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
            ])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($pastor)
            ->post("/examinations/{$exam->id}/return", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
            ])
            ->assertRedirect(route('dashboard'));
    }

    /* ---------------- Teacher restriction --------------------------- */

    public function test_teacher_sees_only_assigned_classes_results(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $classA = $this->makeClass($teacher, 'KG1-A'); // teacher assigned
        $classB = $this->makeClass(null, 'KG1-B');     // teacher NOT assigned
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $examA = $this->makeExamination($year, $term, $classA, $subject);
        $examB = $this->makeExamination($year, $term, $classB, $subject, 'Mid Term B');

        $studentA = $this->makeStudent($classA, 'Amina');
        $studentB = $this->makeStudent($classB, 'John');
        $this->makeResult($examA, $classA, $subject, $studentA, 'approved', 18);
        $this->makeResult($examB, $classB, $subject, $studentB, 'approved', 12);

        $response = $this->actingAs($teacher)->get('/results-analytics');

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->where('is_read_only', false)
            ->where('user_role', 'teacher')
            ->where('overview.results_recorded', 1)
            ->where('filters.classes.0.name', 'KG1 Sunshine')
            ->where('filters.classes', [
                ['id' => $classA->id, 'name' => 'KG1 Sunshine', 'code' => 'KG1-A'],
            ])
        );

        // Teacher cannot force-view another class via the class filter.
        $this->actingAs($teacher)
            ->get('/results-analytics?class_id=' . $classB->id)
            ->assertForbidden();
    }

    /* ---------------- Auth & role guards ---------------------------- */

    public function test_unauthenticated_user_cannot_access_analytics(): void
    {
        $this->get('/results-analytics')->assertRedirect(route('login'));
    }

    public function test_other_roles_cannot_access_analytics(): void
    {
        $accountant = $this->makeUser(UserRole::Accountant);
        $parent = $this->makeUser(UserRole::Parent);

        $this->actingAs($accountant)->get('/results-analytics')->assertRedirect(route('dashboard'));
        $this->actingAs($parent)->get('/results-analytics')->assertRedirect(route('dashboard'));
    }

    /* ---------------- Print ----------------------------------------- */

    public function test_leadership_can_print_read_only_report(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);
        $exam = $this->makeExamination($year, $term, $class, $subject);
        $student = $this->makeStudent($class, 'Amina');
        $this->makeResult($exam, $class, $subject, $student, 'approved', 18);

        $this->actingAs($hos)
            ->get('/results-analytics/print')
            ->assertOk()
            ->assertSee('God-Win Daycare & Nursery School')
            ->assertSee('Read-Only Report')
            ->assertSee('Amina');
    }

    /* ---------------- Empty dataset --------------------------------- */

    public function test_empty_dataset_does_not_error_or_mislead(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $this->makeAcademicYear();
        $this->makeTerm($this->makeAcademicYear('2025'));

        $this->actingAs($hos)->get('/results-analytics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overview.total_examinations', 0)
                ->where('overview.results_recorded', 0)
                ->where('overview.submitted_awaiting_approval', 0)
                ->where('overview.approved_results', 0)
                ->where('overview.class_average', 0)
                ->where('studentPerformance', [])
                ->where('classAnalytics', [])
                ->where('subjectAnalytics', [])
                ->where('grade_distribution', [])
            );

        // Teacher with no assigned classes.
        $teacher = $this->makeUser(UserRole::Teacher);
        $this->actingAs($teacher)->get('/results-analytics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overview.results_recorded', 0)
                ->where('studentPerformance', [])
            );
    }
}

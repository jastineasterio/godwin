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
 * PHASE 2-10 — Teacher Academic Module implementation tests.
 *
 * Covers:
 *  - Examination creation/management (Head of School only)
 *  - Teacher result entry (draft, submit, validation)
 *  - My Classes (teacher-scoped)
 *  - Homework (teacher-scoped)
 *  - Results & Analytics (teacher-scoped)
 *  - Teacher dashboard fix
 *  - Permissions enforcement
 */
class TeacherAcademicModuleTest extends TestCase
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

    /* ------------------- PHASE 2: Examination Permissions ---------------- */

    public function test_head_of_school_can_create_examination(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $this->actingAs($hos)
            ->post('/examinations', [
                'name' => 'Mid Term Exam 2026',
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'description' => 'Mid term assessments',
                'class_ids' => [$class->id],
                'subjects' => [
                    ['subject_id' => $subject->id, 'max_score' => 20],
                ],
            ])
            ->assertRedirect(route('examinations.index'));

        $this->assertDatabaseHas('examinations', [
            'name' => 'Mid Term Exam 2026',
            'academic_year_id' => $year->id,
            'term_id' => $term->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('examination_classes', [
            'examination_id' => Examination::first()->id,
            'class_id' => $class->id,
        ]);

        $this->assertDatabaseHas('examination_subjects', [
            'examination_id' => Examination::first()->id,
            'subject_id' => $subject->id,
            'max_score' => 20,
        ]);
    }

    public function test_teacher_cannot_create_examination(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $this->actingAs($teacher)
            ->post('/examinations', [
                'name' => 'Mid Term Exam 2026',
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'class_ids' => [1],
                'subjects' => [],
            ])
            ->assertRedirect('/dashboard');
    }

    public function test_teacher_cannot_access_examination_management(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($teacher)->get('/examinations')->assertRedirect('/dashboard');
        $this->actingAs($teacher)->get('/examinations/create')->assertRedirect('/dashboard');
    }

    public function test_head_of_school_can_edit_draft_examination(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term Exam', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'draft', 'created_by' => $hos->id,
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $this->actingAs($hos)
            ->put("/examinations/{$exam->id}", [
                'name' => 'Updated Mid Term Exam',
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'class_ids' => [$class->id],
                'subjects' => [
                    ['subject_id' => $subject->id, 'max_score' => 25],
                ],
            ])
            ->assertRedirect(route('examinations.index'));

        $this->assertDatabaseHas('examinations', ['id' => $exam->id, 'name' => 'Updated Mid Term Exam']);
    }

    public function test_head_of_school_cannot_publish_then_edit_examination(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $exam = Examination::create([
            'name' => 'Mid Term Exam', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published', 'created_by' => $hos->id,
        ]);

        $this->actingAs($hos)
            ->put("/examinations/{$exam->id}", ['name' => 'Should Not Change'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    /* ---------------- PHASE 3: Teacher Sees Assigned Examinations ------- */

    public function test_teacher_sees_only_assigned_examinations(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $otherTeacher = $this->makeUser(UserRole::Teacher, ['email' => 'other@godwin.ac.tz']);

        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $ownClass = $this->makeClass($teacher, 'KG1-A');
        $otherClass = $this->makeClass($otherTeacher, 'NUR-B');

        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $ownExam = Examination::create([
            'name' => 'Own Exam', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $ownExam->classes()->attach($ownClass->id);
        $ownExam->subjects()->attach($subject->id, ['max_score' => 20]);

        $otherExam = Examination::create([
            'name' => 'Other Exam', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $otherExam->classes()->attach($otherClass->id);
        $otherExam->subjects()->attach($subject->id, ['max_score' => 20]);

        $this->actingAs($teacher)
            ->get('/teacher/examinations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('examinations.0.name', 'Own Exam')
            );

        // Teacher should NOT see the other teacher's examination
        $this->actingAs($teacher)
            ->get('/teacher/examinations')
            ->assertInertia(fn ($page) => $page
                ->where('examinations', fn ($exams) => !collect($exams)->contains('name', 'Other Exam'))
            );
    }

    /* ---------------- PHASE 3: Teacher Sees Assigned Classes ------------ */

    public function test_teacher_sees_only_assigned_classes(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $otherTeacher = $this->makeUser(UserRole::Teacher, ['email' => 'other@godwin.ac.tz']);

        $ownClass = $this->makeClass($teacher, 'KG1-A');
        $otherClass = $this->makeClass($otherTeacher, 'NUR-B');

        $this->actingAs($teacher)
            ->get('/my-classes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('classes.0.name', 'KG1 Sunshine')
                ->where('classes', fn ($classes) => count($classes) === 1)
            );
    }

    /* ---------------- PHASE 5: Teacher Enters Marks --------------------- */

    public function test_teacher_can_enter_valid_student_marks(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [
                    [
                        'student_id' => $student->id,
                        'score' => 18,
                        'remarks' => 'Good work',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assessments', [
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'score' => 18,
            'max_score' => 20,
            'status' => 'draft',
            'recorded_by' => $teacher->id,
        ]);
    }

    public function test_invalid_marks_above_maximum_are_rejected(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [
                    ['student_id' => $student->id, 'score' => 25], // above max 20
                ],
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('assessments', [
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'score' => 25,
        ]);
    }

    public function test_negative_marks_are_rejected(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [
                    ['student_id' => $student->id, 'score' => -5],
                ],
            ])
            ->assertSessionHasErrors();
    }

    public function test_duplicate_results_are_prevented(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        // First save should succeed
        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [
                    ['student_id' => $student->id, 'score' => 15],
                ],
            ])
            ->assertRedirect();

        // Second save for same student/subject should update, not duplicate
        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [
                    ['student_id' => $student->id, 'score' => 18],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, Assessment::where('examination_id', $exam->id)
            ->where('student_id', $student->id)
            ->where('subject_id', $subject->id)->count());
        $this->assertSame(18.00, (float) Assessment::where('examination_id', $exam->id)
            ->where('student_id', $student->id)
            ->first()->score);
    }

    public function test_teacher_cannot_enter_marks_for_student_not_in_class(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $otherTeacher = $this->makeUser(UserRole::Teacher, ['email' => 'other@godwin.ac.tz']);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $ownClass = $this->makeClass($teacher, 'KG1-A');
        $otherClass = $this->makeClass($otherTeacher, 'NUR-B');
        $foreignStudent = $this->makeStudent($otherClass, 'Foreign');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($ownClass->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $ownClass->id,
                'subject_id' => $subject->id,
                'results' => [
                    ['student_id' => $foreignStudent->id, 'score' => 15],
                ],
            ])
            ->assertForbidden();
    }

    public function test_approved_results_cannot_be_changed_by_teacher(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        // Create an approved result directly (simulating HoS approval)
        Assessment::create([
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'title' => 'Mid Term - Phonics',
            'type' => 'exam',
            'score' => 15,
            'max_score' => 20,
            'recorded_by' => $teacher->id,
            'status' => 'approved',
        ]);

        // Teacher tries to update the approved result through bulk entry
        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [
                    ['student_id' => $student->id, 'score' => 20],
                ],
            ])
            ->assertForbidden();

        // The approved result must remain unchanged
        $this->assertSame(15.00, (float) Assessment::where('examination_id', $exam->id)
            ->where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->first()->score);
    }

    public function test_head_of_school_can_approve_submitted_results(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        // Teacher submits results
        $assessment = Assessment::create([
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'title' => 'Mid Term - Phonics',
            'type' => 'exam',
            'score' => 15,
            'max_score' => 20,
            'recorded_by' => $teacher->id,
            'status' => 'submitted',
        ]);

        // HoS approves
        $this->actingAs($hos)
            ->post("/examinations/{$exam->id}/approve", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
            ])
            ->assertRedirect();

        $this->assertSame('approved', Assessment::find($assessment->id)->refresh()->status);
    }

    public function test_head_of_school_can_return_submitted_results(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $assessment = Assessment::create([
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'title' => 'Mid Term - Phonics',
            'type' => 'exam',
            'score' => 15,
            'max_score' => 20,
            'recorded_by' => $teacher->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($hos)
            ->post("/examinations/{$exam->id}/return", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
            ])
            ->assertRedirect();

        $this->assertSame('returned', Assessment::find($assessment->id)->refresh()->status);
    }

    public function test_teacher_can_submit_draft_results(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        // Create a draft result
        $assessment = Assessment::create([
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'title' => 'Mid Term - Phonics',
            'type' => 'exam',
            'score' => 15,
            'max_score' => 20,
            'recorded_by' => $teacher->id,
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)
            ->post("/teacher/examinations/{$exam->id}/results/submit", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
            ])
            ->assertRedirect();

        $this->assertSame('submitted', Assessment::find($assessment->id)->refresh()->status);
    }

    /* ---------------- PHASE 6: Analytics -------------------------------- */

    public function test_analytics_calculations_are_correct(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student1 = $this->makeStudent($class, 'Amina');
        $student2 = $this->makeStudent($class, 'Joshua');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        // Create results
        Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student1->id,
            'class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Mid Term - Phonics', 'type' => 'exam', 'score' => 18,
            'max_score' => 20, 'recorded_by' => $teacher->id, 'status' => 'approved',
        ]);
        Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student2->id,
            'class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Mid Term - Phonics', 'type' => 'exam', 'score' => 12,
            'max_score' => 20, 'recorded_by' => $teacher->id, 'status' => 'approved',
        ]);

        $this->actingAs($teacher)
            ->get('/results-analytics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overview.total_examinations', 1)
                ->where('overview.results_recorded', 2)
                ->where('overview.approved_results', 2)
                ->where('overview.class_average', 75) // (18/20 + 12/20) / 2 * 100 = 75
            );
    }

    /* ---------------- PHASE 12: Homework -------------------------------- */

    public function test_teacher_can_create_homework(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher, 'KG1-A');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $this->actingAs($teacher)
            ->post('/homework', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'title' => 'Phonics Practice',
                'description' => 'Practice letter sounds A-J',
                'assigned_on' => now()->toDateString(),
                'due_on' => now()->addDays(3)->toDateString(),
                'status' => 'published',
            ])
            ->assertRedirect(route('homework.index'));

        $this->assertDatabaseHas('homeworks', [
            'title' => 'Phonics Practice',
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_teacher_cannot_create_homework_for_another_class(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $otherTeacher = $this->makeUser(UserRole::Teacher, ['email' => 'other@godwin.ac.tz']);
        $class = $this->makeClass($otherTeacher, 'NUR-B');

        $this->actingAs($teacher)
            ->post('/homework', [
                'class_id' => $class->id,
                'title' => 'Unauthorized Homework',
                'description' => 'Should not be allowed',
                'assigned_on' => now()->toDateString(),
                'status' => 'published',
            ])
            ->assertForbidden();
    }

    public function test_homework_index_shows_teacher_own_homework(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher, 'KG1-A');

        $this->actingAs($teacher)
            ->post('/homework', [
                'class_id' => $class->id,
                'title' => 'My Homework',
                'description' => 'Test',
                'assigned_on' => now()->toDateString(),
                'status' => 'published',
            ]);

        $this->actingAs($teacher)
            ->get('/homework')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('homeworks.data.0.title', 'My Homework')
            );
    }

    /* ---------------- PHASE 13: Teacher Dashboard Fix ------------------ */

    public function test_teacher_dashboard_loads_without_permission_error(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher, 'KG1-A');

        $response = $this->actingAs($teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboards/Teacher')
                ->has('stats')
                ->has('classes')
            );

        // No error flash should be present
        $response->assertSessionMissing('error');
    }

    /* ---------------- PHASE 12: Existing Report Cards ------------------- */

    public function test_existing_report_card_still_works(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        Assessment::create([
            'student_id' => $student->id, 'class_id' => $class->id,
            'subject_id' => $subject->id, 'title' => 'Test',
            'type' => 'test', 'score' => 15, 'max_score' => 20,
            'recorded_by' => $teacher->id,
        ]);

        $this->actingAs($teacher)
            ->get("/students/{$student->id}/report-card")
            ->assertOk();
    }
}

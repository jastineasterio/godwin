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
use App\Services\GradingScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role, string $email = ''): User
    {
        return User::create([
            'name' => ucfirst($role->value) . ' User',
            'email' => $email ?: $role->value . '-' . uniqid() . '@godwin.ac.tz',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
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
            'start_date' => now()->startOfMonth(), 'end_date' => now()->endOfMonth(),
            'is_current' => true,
        ]);
    }

    private function makeExam(array $overrides = []): Examination
    {
        return Examination::create(array_merge([
            'name' => 'Mid Term Exam', 'status' => 'published',
        ], $overrides));
    }

    /* ---------------- TASK 1: Approved results are immutable ---------------- */

    public function test_teacher_cannot_update_approved_result_via_assessments_endpoint(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = $this->makeExam([
            'academic_year_id' => $year->id, 'term_id' => $term->id, 'created_by' => $hos->id,
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $assessment = Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student->id,
            'class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Mid Term - Phonics', 'type' => 'exam', 'score' => 15,
            'max_score' => 20, 'recorded_by' => $teacher->id, 'status' => 'approved',
        ]);

        $this->actingAs($teacher)
            ->put("/assessments/{$assessment->id}", [
                'student_id' => $student->id,
                'title' => 'Mid Term - Phonics',
                'score' => 20,
                'remarks' => 'tampered',
            ])
            ->assertForbidden();

        $this->assertSame(15.00, (float) Assessment::find($assessment->id)->score);
        $this->assertSame('approved', Assessment::find($assessment->id)->status);
    }

    public function test_teacher_cannot_update_approved_result_via_bulk_entry(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = $this->makeExam([
            'academic_year_id' => $year->id, 'term_id' => $term->id, 'created_by' => $hos->id,
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student->id,
            'class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Mid Term - Phonics', 'type' => 'exam', 'score' => 15,
            'max_score' => 20, 'recorded_by' => $teacher->id, 'status' => 'approved',
        ]);

        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [['student_id' => $student->id, 'score' => 20]],
            ])
            ->assertForbidden();

        $this->assertSame(15.00, (float) Assessment::where('examination_id', $exam->id)
            ->where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->first()->score);
    }

    public function test_teacher_cannot_modify_another_teachers_results(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $other = $this->makeUser(UserRole::Teacher, 'other-teacher@godwin.ac.tz');
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $otherClass = $this->makeClass($other, 'NUR-B');
        $student = $this->makeStudent($otherClass, 'Foreign');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $assessment = Assessment::create([
            'student_id' => $student->id, 'class_id' => $otherClass->id,
            'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Test', 'type' => 'test', 'score' => 15, 'max_score' => 20,
            'recorded_by' => $other->id, 'status' => 'draft',
        ]);

        $this->actingAs($teacher)
            ->put("/assessments/{$assessment->id}", ['score' => 20])
            ->assertForbidden();

        $this->assertSame(15.00, (float) Assessment::find($assessment->id)->score);
    }

    /* ---------------- TASK 1: Examination configuration is fixed ---------------- */

    public function test_teacher_cannot_change_examination_configuration(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $otherClass = $this->makeClass(null, 'NUR-B');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);
        $otherSubject = Subject::create(['name' => 'Math', 'code' => 'MATH', 'is_active' => true]);

        $exam = $this->makeExam([
            'academic_year_id' => $year->id, 'term_id' => $term->id, 'created_by' => $hos->id,
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $assessment = Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student->id,
            'class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Mid Term - Phonics', 'type' => 'exam', 'score' => 15,
            'max_score' => 20, 'recorded_by' => $teacher->id, 'status' => 'draft',
        ]);

        // Teachers cannot reach the examination management endpoints at all
        $this->actingAs($teacher)
            ->put("/examinations/{$exam->id}", ['name' => 'Hacked'])
            ->assertRedirect('/dashboard');

        // Attempting to re-point the result at another class/subject/term is ignored
        $this->actingAs($teacher)
            ->put("/assessments/{$assessment->id}", [
                'student_id' => $student->id,
                'title' => 'Mid Term - Phonics',
                'type' => 'exam',
                'score' => 18,
                'max_score' => 20,
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term_id' => $term->id,
            ])
            ->assertRedirect();

        $row = Assessment::find($assessment->id);
        $this->assertSame(18.00, (float) $row->score);
        $this->assertSame($class->id, $row->class_id);
        $this->assertSame($subject->id, $row->subject_id);
        $this->assertSame($term->id, $row->term_id);
        $this->assertSame($exam->id, $row->examination_id);
    }

    /* ---------------- TASK 1: Unauthorized classes remain inaccessible ------ */

    public function test_teacher_cannot_access_unauthorized_classes(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $other = $this->makeUser(UserRole::Teacher, 'other-teacher-2@godwin.ac.tz');
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $ownClass = $this->makeClass($teacher, 'KG1-A');
        $otherClass = $this->makeClass($other, 'NUR-B');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $ownStudent = $this->makeStudent($ownClass, 'Own');
        $otherStudent = $this->makeStudent($otherClass, 'Other');

        $own = Assessment::create([
            'student_id' => $ownStudent->id, 'class_id' => $ownClass->id,
            'subject_id' => $subject->id, 'title' => 'Own', 'type' => 'test',
            'score' => 15, 'max_score' => 20, 'recorded_by' => $teacher->id,
        ]);
        $foreign = Assessment::create([
            'student_id' => $otherStudent->id, 'class_id' => $otherClass->id,
            'subject_id' => $subject->id, 'title' => 'Foreign', 'type' => 'test',
            'score' => 15, 'max_score' => 20, 'recorded_by' => $other->id,
        ]);

        $this->actingAs($teacher)
            ->get('/assessments?class_id=' . $otherClass->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('assessments.data', fn ($data) => !collect($data)->contains('id', $foreign->id))
            );

        $this->actingAs($teacher)
            ->put("/assessments/{$foreign->id}", ['score' => 20])
            ->assertForbidden();
    }

    /* ---------------- TASK 1 / TASK 3: Closed examinations ---------------- */

    public function test_closed_examination_rejects_result_submissions(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = $this->makeExam([
            'academic_year_id' => $year->id, 'term_id' => $term->id, 'status' => 'closed',
            'created_by' => $hos->id,
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [['student_id' => $student->id, 'score' => 15]],
            ])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->post("/teacher/examinations/{$exam->id}/results/submit", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
            ])
            ->assertForbidden();

        $assessment = Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student->id,
            'class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Mid Term - Phonics', 'type' => 'exam', 'score' => 15,
            'max_score' => 20, 'recorded_by' => $teacher->id, 'status' => 'draft',
        ]);

        $this->actingAs($teacher)
            ->put("/assessments/{$assessment->id}", ['score' => 18])
            ->assertForbidden();

        $this->assertSame(15.00, (float) Assessment::find($assessment->id)->score);
    }

    /* ---------------- TASK 1 / TASK 3: HoS return-for-correction workflow -- */

    public function test_head_of_school_can_review_and_return_results(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = $this->makeExam([
            'academic_year_id' => $year->id, 'term_id' => $term->id, 'created_by' => $hos->id,
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);
        $class->subjects()->attach($subject->id, ['teacher_id' => $teacher->id]);

        $assessment = Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student->id,
            'class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Mid Term - Phonics', 'type' => 'exam', 'score' => 15,
            'max_score' => 20, 'recorded_by' => $teacher->id, 'status' => 'submitted',
        ]);

        // HoS approves submitted results
        $this->actingAs($hos)
            ->post("/examinations/{$exam->id}/approve", [
                'class_id' => $class->id, 'subject_id' => $subject->id,
            ])
            ->assertRedirect();
        $this->assertSame('approved', Assessment::find($assessment->id)->refresh()->status);

        // HoS returns approved results for correction (authorized workflow)
        $this->actingAs($hos)
            ->post("/examinations/{$exam->id}/return", [
                'class_id' => $class->id, 'subject_id' => $subject->id,
            ])
            ->assertRedirect();
        $this->assertSame('returned', Assessment::find($assessment->id)->refresh()->status);

        // Returned results may be revised by the teacher
        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'results' => [['student_id' => $student->id, 'score' => 18]],
            ])
            ->assertRedirect();
        $this->assertSame(18.00, (float) Assessment::find($assessment->id)->refresh()->score);
        $this->assertSame('draft', Assessment::find($assessment->id)->refresh()->status);
    }

    /* ---------------- TASK 3: Examination lifecycle transitions ------------ */

    public function test_head_of_school_can_publish_and_close_examination(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $draft = Examination::create([
            'name' => 'Draft Exam', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'draft', 'created_by' => $hos->id,
        ]);
        $class = $this->makeClass(null, 'KG1-A');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);
        $draft->classes()->attach($class->id);
        $draft->subjects()->attach($subject->id, ['max_score' => 20]);

        $this->actingAs($hos)
            ->post("/examinations/{$draft->id}/publish")
            ->assertRedirect();
        $this->assertSame('published', $draft->refresh()->status);

        $this->actingAs($hos)
            ->post("/examinations/{$draft->id}/close")
            ->assertRedirect();
        $this->assertSame('closed', $draft->refresh()->status);
    }

    public function test_published_examination_cannot_be_edited(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $published = $this->makeExam([
            'academic_year_id' => $year->id, 'term_id' => $term->id,
            'status' => 'published', 'created_by' => $hos->id,
        ]);

        $this->actingAs($hos)
            ->put("/examinations/{$published->id}", ['name' => 'Should Not Change'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('Mid Term Exam', $published->refresh()->name);
    }

    public function test_teacher_cannot_change_examination_status(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $draft = Examination::create([
            'name' => 'Draft Exam', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'draft',
        ]);

        $this->actingAs($teacher)
            ->post("/examinations/{$draft->id}/publish")
            ->assertRedirect('/dashboard');

        $this->assertSame('draft', $draft->refresh()->status);
    }

    /* ---------------- TASK 2: Grading rules -------------------------------- */

    public function test_grading_scale_is_applied_consistently(): void
    {
        $this->assertSame('A — Excellent', GradingScale::grade(100));
        $this->assertSame('A — Excellent', GradingScale::grade(90));
        $this->assertSame('B — Very Good', GradingScale::grade(89));
        $this->assertSame('B — Very Good', GradingScale::grade(75));
        $this->assertSame('C — Good', GradingScale::grade(74));
        $this->assertSame('C — Good', GradingScale::grade(60));
        $this->assertSame('D — Satisfactory', GradingScale::grade(59));
        $this->assertSame('D — Satisfactory', GradingScale::grade(50));
        $this->assertSame('E — Needs Support', GradingScale::grade(49));
        $this->assertSame('E — Needs Support', GradingScale::grade(0));
    }

    /* ---------------- TASK 6 / TASK 10: Existing records remain intact ----- */

    public function test_existing_records_remain_intact_after_blocked_writes(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = $this->makeExam([
            'academic_year_id' => $year->id, 'term_id' => $term->id, 'created_by' => $hos->id,
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student->id,
            'class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Mid Term - Phonics', 'type' => 'exam', 'score' => 15,
            'max_score' => 20, 'recorded_by' => $teacher->id, 'status' => 'approved',
        ]);

        $before = Assessment::count();

        $this->actingAs($teacher)
            ->put("/assessments/" . Assessment::first()->id, ['score' => 99])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->put("/teacher/examinations/{$exam->id}/results", [
                'class_id' => $class->id, 'subject_id' => $subject->id,
                'results' => [['student_id' => $student->id, 'score' => 18]],
            ])
            ->assertForbidden();

        $this->assertSame($before, Assessment::count());
        $this->assertSame(15.00, (float) Assessment::first()->score);
        $this->assertSame('approved', Assessment::first()->status);
    }

    public function test_report_card_uses_shared_grading_rules(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        // 15 / 20 = 75% -> B — Very Good
        Assessment::create([
            'student_id' => $student->id, 'class_id' => $class->id,
            'subject_id' => $subject->id, 'title' => 'Test',
            'type' => 'test', 'score' => 15, 'max_score' => 20,
            'recorded_by' => $teacher->id,
        ]);

        $this->actingAs($teacher)
            ->get("/students/{$student->id}/report-card")
            ->assertOk()
            ->assertSee('B — Very Good');
    }
}

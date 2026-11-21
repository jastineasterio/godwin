<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\ClassLevel;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Examination;
use App\Models\SchoolClass;
use App\Models\SmsMessage;
use App\Models\SmsProviderConfig;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Results SMS feature tests.
 *
 * No live SMS is sent. DARSMS HTTP calls are faked and the queue runs
 * synchronously (QUEUE_CONNECTION=sync) so messages are persisted end-to-end.
 */
class SmsResultsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test-'.strtolower($role->value).'-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ], $overrides));
    }

    private function activeProvider(): void
    {
        $config = SmsProviderConfig::create([
            'name' => 'Test DARSMS',
            'provider_type' => 'darsms',
            'base_url' => 'https://test.darsms.local/api/v1',
            'sender_id' => 'DARSMS',
            'is_active' => true,
        ]);
        $config->setApiKeyAttribute('test-api-key-12345');
        $config->save();
    }

    private function fakeDarsmsSuccess(string $messageId = 'msg_xyz'): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'messageIds' => [$messageId],
                    'batchId' => 'batch_abc',
                    'queued' => 1,
                    'senderId' => 'DARSMS',
                ],
            ], 202),
        ]);
    }

    private function createPublishedExamination(array $overrides = []): Examination
    {
        $year = AcademicYear::create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $term = Term::create([
            'name' => 'Term 1',
            'academic_year_id' => $year->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'is_current' => true,
        ]);

        $class = SchoolClass::create([
            'name' => 'KG1',
            'code' => 'KG1',
            'level' => ClassLevel::KG1,
            'is_active' => true,
            'capacity' => 25,
        ]);

        $subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH',
            'is_active' => true,
        ]);

        $exam = Examination::create(array_merge([
            'name' => 'Mid Term Exam',
            'academic_year_id' => $year->id,
            'term_id' => $term->id,
            'status' => 'published',
            'created_by' => null,
        ], $overrides));

        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 100]);

        return $exam;
    }

    private function createStudent(array $overrides = []): Student
    {
        $class = SchoolClass::first() ?? SchoolClass::create([
            'name' => 'KG1',
            'code' => 'KG1',
            'level' => ClassLevel::KG1,
            'is_active' => true,
            'capacity' => 25,
        ]);

        return Student::create(array_merge([
            'reg_no' => 'GWD-'.uniqid(),
            'first_name' => 'Amina',
            'last_name' => 'Juma',
            'gender' => Gender::Female,
            'class_id' => $class->id,
            'status' => StudentStatus::Active,
        ], $overrides));
    }

    private function linkParent(Student $student, User $parent, bool $primary = true): void
    {
        DB::table('parent_student')->insert([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship_type' => $primary ? 'mother' : 'father',
            'is_primary_contact' => $primary,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createApprovedAssessment(Student $student, Examination $exam, Subject $subject, float $score, float $maxScore = 100): Assessment
    {
        return Assessment::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'subject_id' => $subject->id,
            'term_id' => $exam->term_id,
            'examination_id' => $exam->id,
            'title' => $subject->name.' Assessment',
            'status' => 'approved',
            'type' => AssessmentType::Exam,
            'score' => $score,
            'max_score' => $maxScore,
            'recorded_by' => null,
        ]);
    }

    // ------------------------------------------------------------------
    // Page access
    // ------------------------------------------------------------------

    /** @test */
    public function head_of_school_can_access_results_page(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)->get('/sms/results');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert->component('Sms/Results'));
    }

    /** @test */
    public function admin_can_access_results_page(): void
    {
        $user = $this->makeUser(UserRole::Admin);

        $response = $this->actingAs($user)->get('/sms/results');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert->component('Sms/Results'));
    }

    /** @test */
    public function teacher_cannot_access_results_page(): void
    {
        $user = $this->makeUser(UserRole::Teacher);

        $response = $this->actingAs($user)->get('/sms/results');

        $response->assertStatus(302); // redirected by role middleware
    }

    /** @test */
    public function senior_pastor_cannot_access_results_page(): void
    {
        $user = $this->makeUser(UserRole::SeniorPastor);

        $response = $this->actingAs($user)->get('/sms/results');

        $response->assertStatus(302); // redirected by role middleware
    }

    /** @test */
    public function unauthenticated_user_cannot_access_results_page(): void
    {
        $response = $this->get('/sms/results');

        $response->assertStatus(302); // redirected to login
    }

    // ------------------------------------------------------------------
    // Available classes
    // ------------------------------------------------------------------

    /** @test */
    public function head_of_school_can_retrieve_available_classes(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        SchoolClass::create([
            'name' => 'KG1 Sunshine',
            'code' => 'KG1-A',
            'level' => ClassLevel::KG1,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        SchoolClass::create([
            'name' => 'Nursery B',
            'code' => 'NUR-B',
            'level' => ClassLevel::Nursery,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        SchoolClass::create([
            'name' => 'Inactive Class',
            'code' => 'INA',
            'level' => ClassLevel::Daycare,
            'is_active' => false,
            'sort_order' => 9,
        ]);

        $response = $this->actingAs($user)->get('/sms/results');

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert
            ->component('Sms/Results')
            ->where('classes.0.name', 'KG1 Sunshine')
            ->where('classes.1.name', 'Nursery B'));
        // The inactive third class is excluded by the active() scope (only two active classes exist).
    }

    // ------------------------------------------------------------------
    // Eligible students (approved results only)
    // ------------------------------------------------------------------

    /** @test */
    public function approved_results_are_eligible_for_sms(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        $this->createApprovedAssessment($student, $exam, $subject, 89, 100);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1, $data['total_students']);
        // The student has no parent linked, so no phone number -> missing phone.
        $this->assertEquals(0, $data['with_phone']);
        $this->assertEquals(1, $data['without_phone']);
        $this->assertCount(0, $data['students']);
    }

    /** @test */
    public function draft_results_are_not_eligible_for_sms(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        Assessment::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'subject_id' => $subject->id,
            'term_id' => $exam->term_id,
            'examination_id' => $exam->id,
            'status' => 'draft',
            'type' => AssessmentType::Exam,
            'title' => 'Draft Assessment',
            'score' => 89,
            'max_score' => 100,
            'recorded_by' => null,
        ]);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('total_students'));
        $this->assertCount(0, $response->json('students'));
    }

    /** @test */
    public function submitted_results_are_eligible_for_sms(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        Assessment::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'subject_id' => $subject->id,
            'term_id' => $exam->term_id,
            'examination_id' => $exam->id,
            'status' => 'submitted',
            'type' => AssessmentType::Exam,
            'title' => 'Submitted Assessment',
            'score' => 89,
            'max_score' => 100,
            'recorded_by' => null,
        ]);

        $guardian = User::create([
            'name' => 'Guardian',
            'email' => 'guard-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($student, $guardian, true);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1, $data['total_students']);
        $this->assertEquals(1, $data['with_phone']);
        $this->assertCount(1, $data['students']);
        $this->assertEquals($student->id, $data['students'][0]['student_id']);
        $this->assertEquals('Submitted', $data['students'][0]['result_status']);
    }

    /** @test */
    public function submitted_and_approved_results_are_both_eligible_for_sms(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();

        $submittedStudent = $this->createStudent(['reg_no' => 'GWD-S-001']);
        $approvedStudent = $this->createStudent(['reg_no' => 'GWD-A-001']);

        Assessment::create([
            'student_id' => $submittedStudent->id,
            'class_id' => $submittedStudent->class_id,
            'subject_id' => $subject->id,
            'term_id' => $exam->term_id,
            'examination_id' => $exam->id,
            'status' => 'submitted',
            'type' => AssessmentType::Exam,
            'title' => 'Submitted Assessment',
            'score' => 89,
            'max_score' => 100,
            'recorded_by' => null,
        ]);
        $this->createApprovedAssessment($approvedStudent, $exam, $subject, 89, 100);

        $guardian = User::create([
            'name' => 'Guardian',
            'email' => 'guard-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($submittedStudent, $guardian, true);

        $guardianB = User::create([
            'name' => 'Guardian B',
            'email' => 'guardb-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0752222222',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($approvedStudent, $guardianB, true);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(2, $data['total_students']);
        $this->assertEquals(2, $data['with_phone']);
        $this->assertCount(2, $data['students']);

        $statuses = collect($data['students'])->pluck('result_status')->all();
        sort($statuses);
        $this->assertEquals(['Approved', 'Submitted'], $statuses);
    }

    /** @test */
    public function submitted_results_generate_accurate_personalized_preview(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        Assessment::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'subject_id' => $subject->id,
            'term_id' => $exam->term_id,
            'examination_id' => $exam->id,
            'status' => 'submitted',
            'type' => AssessmentType::Exam,
            'title' => 'Submitted Assessment',
            'score' => 89,
            'max_score' => 100,
            'recorded_by' => null,
        ]);

        $guardian = User::create([
            'name' => 'Guardian',
            'email' => 'guard-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($student, $guardian, true);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/preview', [
                'examination_id' => $exam->id,
                'template' => 'Dear {{parent_name}}, results for {{student_name}} ({{admission_number}}). {{subject_summary}} Total: {{total_marks}}/{{total_max_marks}} Grade: {{overall_grade}}.',
                'student_ids' => [$student->id],
            ]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert->component('Sms/ResultsPreview'));

        $props = $response->inertiaProps();
        $students = $props['students'] ?? [];
        $this->assertCount(1, $students);
        $this->assertStringContainsString('Mathematics: 89/100', $students[0]['message']);
        $this->assertStringContainsString('Total: 89/100', $students[0]['message']);
        $this->assertStringContainsString('Grade: B — Very Good', $students[0]['message']);
        $this->assertStringContainsString('Guardian', $students[0]['message']);
        $this->assertStringContainsString($student->full_name, $students[0]['message']);
    }

    /** @test */
    public function submitted_results_can_be_sent_through_mocked_sms_workflow(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        Assessment::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'subject_id' => $subject->id,
            'term_id' => $exam->term_id,
            'examination_id' => $exam->id,
            'status' => 'submitted',
            'type' => AssessmentType::Exam,
            'title' => 'Submitted Assessment',
            'score' => 89,
            'max_score' => 100,
            'recorded_by' => null,
        ]);

        $parent = User::create([
            'name' => 'Guardian',
            'email' => 'guard-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($student, $parent, true);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$student->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}} ({{admission_number}}).',
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(1, $json['sent']);
        $this->assertEquals(0, $json['failed']);
        $this->assertEquals(0, $json['skipped']);

        $this->assertDatabaseHas('sms_messages', [
            'to' => '255712345678',
            'category' => 'results',
            'status' => 'queued',
        ]);

        $message = SmsMessage::where('to', '255712345678')->where('category', 'results')->first();
        $this->assertStringContainsString('Guardian', $message->message);
        $this->assertStringContainsString($student->full_name, $message->message);
        $this->assertStringContainsString($student->reg_no, $message->message);
    }

    // ------------------------------------------------------------------
    // Class filtering
    // ------------------------------------------------------------------

    /** @test */
    public function class_filter_returns_only_students_in_that_class(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();

        $classA = SchoolClass::create([
            'name' => 'Class A', 'code' => 'CLA', 'level' => ClassLevel::KG1, 'is_active' => true,
        ]);
        $classB = SchoolClass::create([
            'name' => 'Class B', 'code' => 'CLB', 'level' => ClassLevel::KG2, 'is_active' => true,
        ]);

        $studentA = $this->createStudent(['class_id' => $classA->id, 'reg_no' => 'GWD-A-001']);
        $studentB = $this->createStudent(['class_id' => $classB->id, 'reg_no' => 'GWD-B-001']);

        $this->createApprovedAssessment($studentA, $exam, $subject, 80, 100);
        $this->createApprovedAssessment($studentB, $exam, $subject, 70, 100);

        $guardianA = User::create([
            'name' => 'Guardian A', 'email' => 'ga-'.uniqid().'@godwin.ac.tz', 'password' => 'password',
            'phone' => '0711111111', 'role' => UserRole::Parent, 'status' => 'active',
        ]);
        $guardianB = User::create([
            'name' => 'Guardian B', 'email' => 'gb-'.uniqid().'@godwin.ac.tz', 'password' => 'password',
            'phone' => '0752222222', 'role' => UserRole::Parent, 'status' => 'active',
        ]);
        $this->linkParent($studentA, $guardianA, true);
        $this->linkParent($studentB, $guardianB, true);

        $hos = $this->makeUser(UserRole::HeadOfSchool);

        // No class filter -> both students with valid phones.
        $all = $this->actingAs($hos)
            ->post('/sms/results/students', ['examination_id' => $exam->id]);
        $all->assertStatus(200);
        $this->assertEquals(2, $all->json('with_phone'));

        // Filter by class A -> only student A.
        $filteredA = $this->actingAs($hos)
            ->post('/sms/results/students', ['examination_id' => $exam->id, 'class_id' => $classA->id]);
        $filteredA->assertStatus(200);
        $this->assertEquals(1, $filteredA->json('with_phone'));
        $this->assertEquals($studentA->id, $filteredA->json('students.0.student_id'));
        $this->assertEquals('Class A', $filteredA->json('students.0.class_name'));

        // Filter by class B -> only student B.
        $filteredB = $this->actingAs($hos)
            ->post('/sms/results/students', ['examination_id' => $exam->id, 'class_id' => $classB->id]);
        $filteredB->assertStatus(200);
        $this->assertEquals(1, $filteredB->json('with_phone'));
        $this->assertEquals($studentB->id, $filteredB->json('students.0.student_id'));
        $this->assertEquals('Class B', $filteredB->json('students.0.class_name'));

        // A class with no approved results returns none.
        $empty = SchoolClass::create([
            'name' => 'Empty Class', 'code' => 'EMP', 'level' => ClassLevel::Daycare, 'is_active' => true,
        ]);
        $filteredEmpty = $this->actingAs($hos)
            ->post('/sms/results/students', ['examination_id' => $exam->id, 'class_id' => $empty->id]);
        $filteredEmpty->assertStatus(200);
        $this->assertEquals(0, $filteredEmpty->json('with_phone'));
        $this->assertCount(0, $filteredEmpty->json('students'));
    }

    // ------------------------------------------------------------------
    // Per-student calculations
    // ------------------------------------------------------------------

    /** @test */
    public function subject_summary_totals_and_grade_are_calculated_correctly(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subjects = $exam->subjects;
        $math = $subjects->first();
        $phonics = Subject::create([
            'name' => 'Phonics',
            'code' => 'PHON',
            'is_active' => true,
        ]);
        $exam->subjects()->attach($phonics->id, ['max_score' => 100]);
        $bible = Subject::create([
            'name' => 'Bible Stories',
            'code' => 'BIBLE',
            'is_active' => true,
        ]);
        $exam->subjects()->attach($bible->id, ['max_score' => 100]);

        $student = $this->createStudent();

        $guardian = User::create([
            'name' => 'Guardian',
            'email' => 'guard-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($student, $guardian, true);

        $this->createApprovedAssessment($student, $exam, $math, 89, 100);
        $this->createApprovedAssessment($student, $exam, $phonics, 85, 100);
        $this->createApprovedAssessment($student, $exam, $bible, 92, 100);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $studentData = $response->json('students.0');

        $this->assertEquals('Mathematics: 89/100'."\n".'Phonics: 85/100'."\n".'Bible Stories: 92/100', $studentData['subject_summary']);
        $this->assertEquals(266, $studentData['total_marks']);
        $this->assertEquals(300, $studentData['total_max_marks']);
        $this->assertEquals(88.7, $studentData['average_percentage']);
        $this->assertEquals('B — Very Good', $studentData['overall_grade']);
        $this->assertTrue($studentData['is_complete']);
    }

    /** @test */
    public function missing_marks_are_excluded_from_calculations(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subjects = $exam->subjects;
        $math = $subjects->first();
        $phonics = Subject::create([
            'name' => 'Phonics',
            'code' => 'PHON',
            'is_active' => true,
        ]);
        $exam->subjects()->attach($phonics->id, ['max_score' => 100]);

        $student = $this->createStudent();

        $guardian = User::create([
            'name' => 'Guardian',
            'email' => 'guard-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($student, $guardian, true);

        // Only Mathematics has a recorded score; Phonics is missing.
        $this->createApprovedAssessment($student, $exam, $math, 80, 100);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $studentData = $response->json('students.0');

        $this->assertEquals(80, $studentData['total_marks']);
        $this->assertEquals(100, $studentData['total_max_marks']);
        $this->assertEquals(80.0, $studentData['average_percentage']);
        $this->assertFalse($studentData['is_complete']); // partial results
    }

    // ------------------------------------------------------------------
    // Guardian resolution
    // ------------------------------------------------------------------

    /** @test */
    public function correct_guardian_receives_the_childs_results(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        $this->createApprovedAssessment($student, $exam, $subject, 89, 100);

        $primaryParent = User::create([
            'name' => 'Primary Guardian',
            'email' => 'primary-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $otherParent = User::create([
            'name' => 'Other Guardian',
            'email' => 'other-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0722345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);

        $this->linkParent($student, $primaryParent, true);
        $this->linkParent($student, $otherParent, false);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$student->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}} ({{admission_number}}). Grade: {{overall_grade}}.',
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals(1, $response->json('sent'));
        $this->assertEquals(0, $response->json('failed'));

        // The primary guardian (normalized 255712345678) should receive the SMS.
        $this->assertDatabaseHas('sms_messages', [
            'to' => '255712345678',
            'category' => 'results',
            'status' => 'queued',
        ]);

        $message = SmsMessage::where('to', '255712345678')->where('category', 'results')->first();
        $this->assertStringContainsString('Primary Guardian', $message->message);
        $this->assertStringContainsString($student->full_name, $message->message);
        $this->assertStringContainsString($student->reg_no, $message->message);

        // The other guardian must NOT receive the child's results.
        $this->assertDatabaseMissing('sms_messages', [
            'to' => '255722345678',
            'category' => 'results',
        ]);
    }

    /** @test */
    public function missing_guardian_phone_number_is_handled_safely(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        $this->createApprovedAssessment($student, $exam, $subject, 89, 100);

        // Parent with no phone number.
        $parent = User::create([
            'name' => 'No Phone Parent',
            'email' => 'nophone-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => null,
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($student, $parent, true);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('without_phone'));
        $this->assertCount(0, $response->json('students'));

        // No SMS should be sent.
        $this->assertDatabaseMissing('sms_messages', ['category' => 'results']);
    }

    /** @test */
    public function students_without_any_guardian_are_handled_safely(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        $this->createApprovedAssessment($student, $exam, $subject, 89, 100);
        // No parent linked at all.

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('without_phone'));
        $this->assertCount(0, $response->json('students'));
    }

    // ------------------------------------------------------------------
    // Duplicate prevention
    // ------------------------------------------------------------------

    /** @test */
    public function duplicate_sms_is_prevented_for_same_student_examination_recipient(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        $this->createApprovedAssessment($student, $exam, $subject, 89, 100);

        $parent = User::create([
            'name' => 'Guardian',
            'email' => 'guard-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($student, $parent, true);

        $template = 'Dear {{parent_name}}, results for {{student_name}} ({{admission_number}}).';

        // First send.
        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$student->id],
                'template' => $template,
            ]);

        $this->assertDatabaseHas('sms_messages', [
            'to' => '255712345678',
            'category' => 'results',
        ]);

        // Second send with same template/message -> duplicate prevented.
        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$student->id],
                'template' => $template,
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals(0, $response->json('sent'));
        $this->assertEquals(1, $response->json('duplicates_prevented'));

        // Still only one message record.
        $this->assertEquals(1, SmsMessage::where('category', 'results')->count());
    }

    // ------------------------------------------------------------------
    // Duplicate control: siblings sharing one guardian phone number
    // ------------------------------------------------------------------

    /** @test */
    public function two_siblings_sharing_one_guardian_phone_each_receive_personalized_results_sms(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination(['name' => 'Annual Exam']);
        $subject = $exam->subjects->first();

        // One parent (Martha) with one phone shared by both children.
        $parent = User::create([
            'name' => 'Martha',
            'email' => 'martha-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712342282',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);

        $amina = $this->createStudent([
            'reg_no' => 'GWD-2026-0001',
            'first_name' => 'Amina',
            'last_name' => 'Juma',
        ]);
        $this->createApprovedAssessment($amina, $exam, $subject, 89, 100);
        $this->linkParent($amina, $parent, true);

        $daniel = $this->createStudent([
            'reg_no' => 'GWD-2026-0004',
            'first_name' => 'Daniel',
            'last_name' => 'Mwakalinga',
        ]);
        $this->createApprovedAssessment($daniel, $exam, $subject, 72, 100);
        $this->linkParent($daniel, $parent, true);

        $template = 'Dear {{parent_name}}, results for {{student_name}} ({{admission_number}}). {{subject_summary}} Total: {{total_marks}}/{{total_max_marks}} Grade: {{overall_grade}}.';

        // 1 & 2. Both siblings receive separate personalized SMS to the same parent phone.
        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$amina->id, $daniel->id],
                'template' => $template,
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(2, $json['sent']);
        $this->assertEquals(0, $json['failed']);
        $this->assertEquals(0, $json['duplicates_prevented']);

        $messages = SmsMessage::where('category', 'results')
            ->where('metadata->examination_id', $exam->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $messages);
        // Same parent phone, separate SMS records.
        $this->assertTrue($messages->every(fn ($m) => $m->to === '255712342282'));
        // 3. Different idempotency keys: identity is student + examination, not phone.
        $this->assertNotEquals($messages[0]->idempotency_key, $messages[1]->idempotency_key);
        $this->assertStringStartsWith('results_sms:', $messages[0]->idempotency_key);

        // 2. Each message contains the correct individual student details.
        $aminaMsg = $messages->firstWhere('metadata.student_id', $amina->id);
        $this->assertNotNull($aminaMsg);
        $this->assertEquals($parent->id, $aminaMsg->metadata['parent_id'] ?? null);
        $this->assertStringContainsString('Martha', $aminaMsg->message);
        $this->assertStringContainsString('Amina Juma', $aminaMsg->message);
        $this->assertStringContainsString('GWD-2026-0001', $aminaMsg->message);
        $this->assertStringContainsString('Mathematics: 89/100', $aminaMsg->message);
        $this->assertStringContainsString('Total: 89/100', $aminaMsg->message);

        $danielMsg = $messages->firstWhere('metadata.student_id', $daniel->id);
        $this->assertNotNull($danielMsg);
        $this->assertEquals($parent->id, $danielMsg->metadata['parent_id'] ?? null);
        $this->assertStringContainsString('Martha', $danielMsg->message);
        $this->assertStringContainsString('Daniel Mwakalinga', $danielMsg->message);
        $this->assertStringContainsString('GWD-2026-0004', $danielMsg->message);
        $this->assertStringContainsString('Mathematics: 72/100', $danielMsg->message);
        $this->assertStringContainsString('Total: 72/100', $danielMsg->message);

        // 4. Repeating Amina's notification is blocked (per-student duplicate protection).
        $repeatAmina = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$amina->id],
                'template' => $template,
            ]);
        $repeatAmina->assertStatus(200);
        $this->assertEquals(0, $repeatAmina->json('sent'));
        $this->assertEquals(1, $repeatAmina->json('duplicates_prevented'));
        $this->assertEquals(2, SmsMessage::where('category', 'results')->count());

        // 5. Repeating Daniel's notification is blocked (per-student duplicate protection).
        $repeatDaniel = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$daniel->id],
                'template' => $template,
            ]);
        $repeatDaniel->assertStatus(200);
        $this->assertEquals(0, $repeatDaniel->json('sent'));
        $this->assertEquals(1, $repeatDaniel->json('duplicates_prevented'));
        $this->assertEquals(2, SmsMessage::where('category', 'results')->count());

        // 6. Duplicate protection holds regardless of existing status (queued/sent/delivered).
        $sentAmina = SmsMessage::where('metadata->student_id', $amina->id)
            ->where('category', 'results')
            ->first();
        $sentAmina->update(['status' => 'sent', 'provider_status' => 'SENT']);
        $repeatAminaSent = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$amina->id],
                'template' => $template,
            ]);
        $repeatAminaSent->assertStatus(200);
        $this->assertEquals(0, $repeatAminaSent->json('sent'));
        $this->assertEquals(1, $repeatAminaSent->json('duplicates_prevented'));
        $this->assertEquals(2, SmsMessage::where('category', 'results')->count());

        // 9. Results SMS history associates each message with the correct student and parent.
        $history = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->get('/sms/results/history?examination_id='.$exam->id);
        $history->assertStatus(200);
        $history->assertInertia(fn ($assert) => $assert->component('Sms/ResultsHistory'));
        $this->assertDatabaseHas('sms_messages', [
            'metadata->student_id' => $amina->id,
            'metadata->parent_id' => $parent->id,
            'category' => 'results',
        ]);
        $this->assertDatabaseHas('sms_messages', [
            'metadata->student_id' => $daniel->id,
            'metadata->parent_id' => $parent->id,
            'category' => 'results',
        ]);
    }

    // ------------------------------------------------------------------
    // Template rendering
    // ------------------------------------------------------------------

    /** @test */
    public function template_placeholders_render_correctly(): void
    {
        $service = app(SmsTemplateService::class);

        $rendered = $service->render(
            "Dear {{parent_name}}, results for {{student_name}} ({{admission_number}}), Class {{class_name}}, {{examination_name}}.\n{{subject_summary}}\nTotal: {{total_marks}}/{{total_max_marks}}\nAverage: {{average_percentage}}%\nGrade: {{overall_grade}}\n{{school_name}}.",
            [
                'parent_name' => 'John Doe',
                'student_name' => 'Amina Juma',
                'admission_number' => 'GWD-101',
                'class_name' => 'KG1',
                'academic_year' => '2026',
                'term' => 'Term 1',
                'examination_name' => 'Mid Term Exam',
                'subject_summary' => 'Mathematics: 89/100',
                'total_marks' => 89,
                'total_max_marks' => 100,
                'average_percentage' => 89.0,
                'overall_grade' => 'B — Very Good',
                'school_name' => 'God-Win Daycare & Nursery School',
            ]
        );

        $this->assertStringContainsString('Dear John Doe', $rendered);
        $this->assertStringContainsString('Amina Juma', $rendered);
        $this->assertStringContainsString('GWD-101', $rendered);
        $this->assertStringContainsString('KG1', $rendered);
        $this->assertStringContainsString('Mid Term Exam', $rendered);
        $this->assertStringContainsString('Mathematics: 89/100', $rendered);
        $this->assertStringContainsString('89/100', $rendered);
        $this->assertStringContainsString('89%', $rendered);
        $this->assertStringContainsString('B — Very Good', $rendered);
        $this->assertStringContainsString('God-Win Daycare & Nursery School', $rendered);
        // No unreplaced placeholders should remain.
        $this->assertStringNotContainsString('{{', $rendered);
    }

    /** @test */
    public function single_brace_placeholders_still_work_for_backward_compatibility(): void
    {
        $service = app(SmsTemplateService::class);

        $rendered = $service->render('Dear {parent_name}, your child {student_name} has results.', [
            'parent_name' => 'John Doe',
            'student_name' => 'Amina Juma',
        ]);

        $this->assertStringContainsString('Dear John Doe', $rendered);
        $this->assertStringContainsString('Amina Juma', $rendered);
    }

    // ------------------------------------------------------------------
    // Security / credential exposure
    // ------------------------------------------------------------------

    /** @test */
    public function provider_credentials_are_never_exposed_in_responses(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        $this->createApprovedAssessment($student, $exam, $subject, 89, 100);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $body = $response->getContent();

        $this->assertStringNotContainsString('test-api-key', $body);
        $this->assertStringNotContainsString('api_key', $body);
    }

    // ------------------------------------------------------------------
    // Existing SMS functionality remains operational
    // ------------------------------------------------------------------

    /** @test */
    public function existing_sms_bulk_functionality_remains_operational(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)
            ->post('/sms/bulk/send', [
                'recipients' => [['phone' => '255712345678', 'name' => 'Parent One']],
                'message' => 'Hello {parent_name}',
                'sender_id' => 'DARSMS',
                'campaign_name' => 'Existing Campaign',
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertDatabaseHas('sms_messages', [
            'to' => '255712345678',
            'category' => 'bulk',
        ]);
    }

    // ------------------------------------------------------------------
    // Retry failed without duplicate successful sends
    // ------------------------------------------------------------------

    /** @test */
    public function failed_sms_can_be_retried_without_duplicate_successful_sends(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        $this->createApprovedAssessment($student, $exam, $subject, 89, 100);

        $parent = User::create([
            'name' => 'Guardian',
            'email' => 'guard-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($student, $parent, true);

        // First attempt: provider fails.
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => 'Provider error',
                'data' => null,
            ], 500),
        ]);

        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$student->id],
                'template' => 'Hello {{parent_name}}',
            ]);

        // Message recorded as failed.
        $failed = SmsMessage::where('category', 'results')->where('status', 'failed')->first();
        $this->assertNotNull($failed);
        $this->assertEquals('255712345678', $failed->to);
        $this->assertEquals(1, SmsMessage::where('category', 'results')->count());

        // Retry with the same student/examination/recipient/template: the
        // idempotency key is identical, so no duplicate message is created
        // and no duplicate successful send occurs.
        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$student->id],
                'template' => 'Hello {{parent_name}}',
            ]);

        $this->assertEquals(1, SmsMessage::where('category', 'results')->count());
        // The existing record remains the failed one (no successful duplicate).
        $this->assertDatabaseHas('sms_messages', [
            'to' => '255712345678',
            'category' => 'results',
            'status' => 'failed',
        ]);
    }

    // ------------------------------------------------------------------
    // Status-aware retry handling
    // ------------------------------------------------------------------

    private function retrySend(Examination $exam, int $studentId, string $template)
    {
        return $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$studentId],
                'template' => $template,
                'is_resend' => true,
                'resend_reason' => 'Authorized retry test.',
            ]);
    }

    /** @test */
    public function queued_results_sms_cannot_be_retried(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        // Original send -> queued.
        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Hello {{parent_name}}',
            ]);

        $message = SmsMessage::where('category', 'results')->first();
        $this->assertNotNull($message);
        $this->assertEquals('queued', $message->status);

        // Authorized retry for a queued message is blocked.
        $response = $this->retrySend($exam, $created['student']->id, 'Hello {{parent_name}}');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('sent'));
        $this->assertEquals(1, $response->json('duplicates_prevented'));
        $this->assertEquals(1, SmsMessage::where('category', 'results')->count());
        $this->assertDatabaseHas('sms_messages', ['id' => $message->id, 'status' => 'queued']);
    }

    /** @test */
    public function sent_results_sms_cannot_be_retried(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Hello {{parent_name}}',
            ]);

        $message = SmsMessage::where('category', 'results')->first();
        $message->update(['status' => 'sent', 'provider_status' => 'SENT']);

        $response = $this->retrySend($exam, $created['student']->id, 'Hello {{parent_name}}');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('sent'));
        $this->assertEquals(1, $response->json('duplicates_prevented'));
        $this->assertEquals(1, SmsMessage::where('category', 'results')->count());
        $this->assertDatabaseHas('sms_messages', ['id' => $message->id, 'status' => 'sent']);
    }

    /** @test */
    public function delivered_results_sms_cannot_be_retried(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Hello {{parent_name}}',
            ]);

        $message = SmsMessage::where('category', 'results')->first();
        $message->update(['status' => 'delivered', 'provider_status' => 'DELIVERED', 'delivered_at' => now()]);

        $response = $this->retrySend($exam, $created['student']->id, 'Hello {{parent_name}}');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('sent'));
        $this->assertEquals(1, $response->json('duplicates_prevented'));
        $this->assertEquals(1, SmsMessage::where('category', 'results')->count());
        $this->assertDatabaseHas('sms_messages', ['id' => $message->id, 'status' => 'delivered']);
    }

    /** @test */
    public function failed_results_sms_can_be_retried_through_authorized_workflow(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        // Single fake drives both the original failure and the retry success.
        // This avoids the provider instance being cached under the first fake.
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response([
                    'success' => false,
                    'message' => 'Provider error',
                    'data' => null,
                ], 500);
            }

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'messageIds' => ['retry_msg_1'],
                    'batchId' => 'batch_retry',
                    'queued' => 1,
                    'senderId' => 'DARSMS',
                ],
            ], 202);
        });

        // Original attempt fails -> recorded as failed.
        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Hello {{parent_name}}',
            ]);

        $failed = SmsMessage::where('category', 'results')->where('status', 'failed')->first();
        $this->assertNotNull($failed);
        $this->assertEquals('255712345678', $failed->to);

        // Authorized retry succeeds.
        $response = $this->retrySend($exam, $created['student']->id, 'Hello {{parent_name}}');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals(1, $response->json('sent'));
        $this->assertEquals(0, $response->json('failed'));
        $this->assertEquals(2, $callCount);

        // Original failed record is preserved.
        $this->assertDatabaseHas('sms_messages', ['id' => $failed->id, 'status' => 'failed']);
        // A new retry record was created with its own idempotency key.
        $this->assertEquals(2, SmsMessage::where('category', 'results')->count());
        $retry = SmsMessage::where('category', 'results')
            ->where('idempotency_key', 'like', 'sms_resend:%')
            ->first();
        $this->assertNotNull($retry);
        $this->assertStringStartsWith('sms_resend:', $retry->idempotency_key);
        $this->assertEquals($failed->id, $retry->metadata['resend_of'] ?? null);
        $this->assertEquals($created['student']->id, $retry->metadata['student_id'] ?? null);
        $this->assertEquals($created['parent']->id, $retry->metadata['parent_id'] ?? null);
        $this->assertEquals($exam->id, $retry->metadata['examination_id'] ?? null);
    }

    /** @test */
    public function submission_unknown_results_sms_cannot_be_automatically_resent(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        // Network timeout -> submission_unknown (not confirmed failed).
        Http::fake(function () {
            throw new ConnectionException('Connection timed out while awaiting response.');
        });
        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Hello {{parent_name}}',
            ]);

        $unknown = SmsMessage::where('category', 'results')->where('status', 'submission_unknown')->first();
        $this->assertNotNull($unknown);
        $this->assertEquals('255712345678', $unknown->to);

        // Authorized retry is blocked for submission_unknown.
        $response = $this->retrySend($exam, $created['student']->id, 'Hello {{parent_name}}');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('sent'));
        $this->assertEquals(0, $response->json('failed'));
        $this->assertEquals(1, $response->json('duplicates_prevented'));
        $this->assertEquals(1, SmsMessage::where('category', 'results')->count());
        $this->assertDatabaseHas('sms_messages', ['id' => $unknown->id, 'status' => 'submission_unknown']);
    }

    /** @test */
    public function submission_unknown_displays_verification_required_state(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        Http::fake(function () {
            throw new ConnectionException('Connection timed out while awaiting response.');
        });
        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Hello {{parent_name}}',
            ]);

        $response = $this->retrySend($exam, $created['student']->id, 'Hello {{parent_name}}');

        $response->assertStatus(200);
        $detail = $response->json('details.0') ?? null;
        $this->assertNotNull($detail);
        $this->assertTrue($detail['verification_required'] ?? false);
        $this->assertStringContainsString('unknown', strtolower($detail['reason'] ?? ''));
    }

    /** @test */
    public function repeated_retry_requests_do_not_create_duplicate_attempts(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        // Single fake drives both the original failure and the retry success.
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response([
                    'success' => false,
                    'message' => 'Provider error',
                    'data' => null,
                ], 500);
            }

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'messageIds' => ['msg_retry'],
                    'batchId' => 'batch_retry',
                    'queued' => 1,
                ],
            ], 202);
        });
        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Hello {{parent_name}}',
            ]);
        $failed = SmsMessage::where('category', 'results')->where('status', 'failed')->first();

        // First retry is accepted.
        $first = $this->retrySend($exam, $created['student']->id, 'Hello {{parent_name}}');
        $first->assertStatus(200);
        $this->assertEquals(1, $first->json('sent'));

        // Repeated retry must not call the provider again or create another record.
        $second = $this->retrySend($exam, $created['student']->id, 'Hello {{parent_name}}');
        $second->assertStatus(200);
        $this->assertEquals(0, $second->json('sent'));
        $this->assertEquals(1, $second->json('duplicates_prevented'));

        // Original failure (call 1) + first retry (call 2). Second retry is
        // short-circuited by the retry idempotency key, so no third call.
        $this->assertEquals(2, $callCount);
        $this->assertEquals(2, SmsMessage::where('category', 'results')->count());
    }

    /** @test */
    public function results_sms_history_displays_retry_attempts_and_statuses(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        // Failed original, then retry accepted -> new queued retry record.
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response([
                    'success' => false,
                    'message' => 'Provider error',
                    'data' => null,
                ], 500);
            }

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'messageIds' => ['retry_msg_history'],
                    'batchId' => 'batch_retry',
                    'queued' => 1,
                    'senderId' => 'DARSMS',
                ],
            ], 202);
        });
        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Hello {{parent_name}}',
            ]);
        $failed = SmsMessage::where('category', 'results')->where('status', 'failed')->first();

        // Retry accepted -> new queued retry record, original preserved.
        $this->retrySend($exam, $created['student']->id, 'Hello {{parent_name}}');

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->get('/sms/results/history?examination_id='.$exam->id);
        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert->component('Sms/ResultsHistory'));

        $messages = $response->inertiaProps()['messages']['data'] ?? [];
        $this->assertCount(2, $messages);
        $statuses = collect($messages)->pluck('status')->all();
        $this->assertContains('failed', $statuses);
        $this->assertContains('queued', $statuses);

        $retryMessages = array_values(array_filter($messages, fn ($m) => ! empty($m['is_resend'])));
        $this->assertCount(1, $retryMessages);
        $this->assertEquals($failed->id, $retryMessages[0]['resend_of'] ?? null);
    }

    // ------------------------------------------------------------------
    // Results SMS send: JSON response, 202 handling, batch accuracy
    // ------------------------------------------------------------------

    private function createStudentWithParent(Examination $exam, Subject $subject, string $phone, string $name = 'Guardian'): array
    {
        $student = $this->createStudent();
        $this->createApprovedAssessment($student, $exam, $subject, 80, 100);

        $parent = User::create([
            'name' => $name,
            'email' => 'guard-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => $phone,
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($student, $parent, true);

        return ['student' => $student, 'parent' => $parent];
    }

    /** @test */
    public function results_sms_send_returns_json_not_redirect(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(1, $json['sent']);
        $this->assertEquals(0, $json['failed']);
        $this->assertArrayHasKey('details', $json);

        $this->assertDatabaseHas('sms_messages', [
            'to' => '255712345678',
            'category' => 'results',
            'status' => 'queued',
        ]);
    }

    /** @test */
    public function http_202_accepted_is_recorded_as_queued_not_delivered(): void
    {
        $this->activeProvider();
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => [
                    'messageIds' => ['msg_123'],
                    'batchId' => 'batch_123',
                    'queued' => 1,
                    'senderId' => 'DARSMS',
                ],
            ], 202),
        ]);

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $message = SmsMessage::where('category', 'results')->first();
        $this->assertNotNull($message);
        $this->assertEquals('queued', $message->status);
        $this->assertNotEquals('delivered', $message->status);
        $this->assertEquals('msg_123', $message->provider_message_id);
        $this->assertEquals('batch_123', $message->batch_id);
        $this->assertEquals('QUEUED', $message->provider_status);
    }

    /** @test */
    public function successful_batch_with_eight_unique_numbers_is_recorded_per_recipient(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();

        $studentIds = [];
        for ($i = 0; $i < 8; $i++) {
            $created = $this->createStudentWithParent(
                $exam, $subject, '071'.str_pad($i, 7, '0', STR_PAD_LEFT), 'Guardian '.$i
            );
            $studentIds[] = $created['student']->id;
        }

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => $studentIds,
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(8, $json['sent']);
        $this->assertEquals(0, $json['failed']);
        $this->assertEquals(8, SmsMessage::where('category', 'results')->count());
    }

    /** @test */
    public function eight_students_sharing_one_guardian_number_are_each_notified(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();

        $parent = User::create([
            'name' => 'Shared Guardian',
            'email' => 'shared-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => '0712345678',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);

        $studentIds = [];
        for ($i = 0; $i < 8; $i++) {
            $student = $this->createStudent();
            $this->createApprovedAssessment($student, $exam, $subject, 80, 100);
            $this->linkParent($student, $parent, true);
            $studentIds[] = $student->id;
        }

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => $studentIds,
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(8, $json['sent']);
        $this->assertEquals(0, $json['failed']);
        // One personalized message per student, all to the same guardian phone.
        $this->assertEquals(8, SmsMessage::where('category', 'results')->count());
        $this->assertEquals(8, SmsMessage::where('category', 'results')->where('to', '255712345678')->count());
    }

    /** @test */
    public function partial_acceptance_is_reported_accurately_and_batch_continues(): void
    {
        $this->activeProvider();

        $call = 0;
        Http::fake(function () use (&$call) {
            $call++;
            if ($call === 1) {
                return Http::response([
                    'success' => true,
                    'message' => 'Accepted',
                    'data' => ['messageIds' => ['msg_a'], 'batchId' => 'batch_a', 'queued' => 1],
                ], 202);
            }

            return Http::response([
                'success' => false,
                'message' => 'Invalid phone number.',
                'data' => null,
            ], 400);
        });

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();

        $first = $this->createStudentWithParent($exam, $subject, '0712345678');
        $second = $this->createStudentWithParent($exam, $subject, '0752222222');

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$first['student']->id, $second['student']->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        // Partial outcome: some accepted, some failed. Not a clean success, but not a total failure either.
        $this->assertFalse($json['success']);
        $this->assertEquals(1, $json['sent']);
        $this->assertEquals(1, $json['failed']);
        // Batch continued: both students were attempted, not aborted after the first.
        $this->assertEquals(2, SmsMessage::where('category', 'results')->count());
    }

    /** @test */
    public function provider_rejection_results_in_total_failure_and_422(): void
    {
        $this->activeProvider();
        Http::fake([
            '*' => Http::response([
                'success' => false,
                'message' => 'Provider error',
                'data' => null,
            ], 500),
        ]);

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $response->assertStatus(422);
        $json = $response->json();
        $this->assertFalse($json['success']);
        $this->assertEquals(0, $json['sent']);
        $this->assertEquals(1, $json['failed']);
        // A useful provider failure reason is surfaced to the caller.
        $this->assertEquals('Provider error', $json['error']);
    }

    /** @test */
    public function network_timeout_continues_batch_and_records_submission_unknown(): void
    {
        $this->activeProvider();

        $call = 0;
        Http::fake(function () use (&$call) {
            $call++;
            if ($call === 1) {
                throw new ConnectionException('Connection timed out while awaiting response.');
            }

            return Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['messageIds' => ['msg_b'], 'batchId' => 'batch_b', 'queued' => 1],
            ], 202);
        });

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();

        $first = $this->createStudentWithParent($exam, $subject, '0712345678');
        $second = $this->createStudentWithParent($exam, $subject, '0752222222');

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$first['student']->id, $second['student']->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertEquals(1, $json['sent']);
        $this->assertEquals(1, $json['failed']);

        // Timed-out message is submission_unknown (investigable), not delivered or definitely failed.
        $timedOut = SmsMessage::where('category', 'results')->where('status', 'submission_unknown')->first();
        $this->assertNotNull($timedOut);
        $this->assertEquals('255712345678', $timedOut->to);
        $this->assertEquals('255752222222', SmsMessage::where('category', 'results')->where('status', 'queued')->value('to'));
    }

    // ------------------------------------------------------------------
    // Security: manipulated / cross-class student IDs
    // ------------------------------------------------------------------

    /** @test */
    public function student_ids_from_another_class_are_rejected_even_with_approved_results(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();

        $classA = SchoolClass::create([
            'name' => 'Class A', 'code' => 'CLA', 'level' => ClassLevel::KG1, 'is_active' => true,
        ]);
        $classB = SchoolClass::create([
            'name' => 'Class B', 'code' => 'CLB', 'level' => ClassLevel::KG2, 'is_active' => true,
        ]);

        $studentA = $this->createStudent(['class_id' => $classA->id, 'reg_no' => 'GWD-A-001']);
        $studentB = $this->createStudent(['class_id' => $classB->id, 'reg_no' => 'GWD-B-001']);

        $this->createApprovedAssessment($studentA, $exam, $subject, 80, 100);
        $this->createApprovedAssessment($studentB, $exam, $subject, 70, 100);

        $guardianA = User::create([
            'name' => 'Guardian A', 'email' => 'ga-'.uniqid().'@godwin.ac.tz', 'password' => 'password',
            'phone' => '0711111111', 'role' => UserRole::Parent, 'status' => 'active',
        ]);
        $guardianB = User::create([
            'name' => 'Guardian B', 'email' => 'gb-'.uniqid().'@godwin.ac.tz', 'password' => 'password',
            'phone' => '0752222222', 'role' => UserRole::Parent, 'status' => 'active',
        ]);
        $this->linkParent($studentA, $guardianA, true);
        $this->linkParent($studentB, $guardianB, true);

        // Attempt to send Class B's student while selecting Class A.
        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'class_id' => $classA->id,
                'student_ids' => [$studentA->id, $studentB->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(1, $json['sent']);
        $this->assertEquals(0, $json['failed']);
        $this->assertEquals(1, $json['skipped']);
        $this->assertStringContainsString('selected class', $json['details'][1]['reason'] ?? '');

        // Only Class A's parent received the message.
        $this->assertEquals(1, SmsMessage::where('category', 'results')->count());
        $this->assertDatabaseHas('sms_messages', [
            'to' => '255711111111',
            'category' => 'results',
        ]);
        $this->assertDatabaseMissing('sms_messages', [
            'to' => '255752222222',
            'category' => 'results',
        ]);
    }

    /** @test */
    public function students_list_respects_the_selected_class(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();

        $classA = SchoolClass::create([
            'name' => 'Class A', 'code' => 'CLA', 'level' => ClassLevel::KG1, 'is_active' => true,
        ]);
        $classB = SchoolClass::create([
            'name' => 'Class B', 'code' => 'CLB', 'level' => ClassLevel::KG2, 'is_active' => true,
        ]);

        $studentA = $this->createStudent(['class_id' => $classA->id, 'reg_no' => 'GWD-A-001']);
        $studentB = $this->createStudent(['class_id' => $classB->id, 'reg_no' => 'GWD-B-001']);

        $guardianA = User::create([
            'name' => 'Guardian A', 'email' => 'ga-'.uniqid().'@godwin.ac.tz', 'password' => 'password',
            'phone' => '0711111111', 'role' => UserRole::Parent, 'status' => 'active',
        ]);
        $guardianB = User::create([
            'name' => 'Guardian B', 'email' => 'gb-'.uniqid().'@godwin.ac.tz', 'password' => 'password',
            'phone' => '0752222222', 'role' => UserRole::Parent, 'status' => 'active',
        ]);
        $this->linkParent($studentA, $guardianA, true);
        $this->linkParent($studentB, $guardianB, true);

        $this->createApprovedAssessment($studentA, $exam, $subject, 80, 100);
        $this->createApprovedAssessment($studentB, $exam, $subject, 70, 100);

        $filteredA = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id, 'class_id' => $classA->id]);

        $filteredA->assertStatus(200);
        $this->assertEquals(1, $filteredA->json('with_phone'));
        $this->assertEquals($studentA->id, $filteredA->json('students.0.student_id'));
        $this->assertEquals('Class A', $filteredA->json('students.0.class_name'));
        $this->assertCount(1, $filteredA->json('students'));
    }

    // ------------------------------------------------------------------
    // Result status gating
    // ------------------------------------------------------------------

    /** @test */
    public function returned_results_are_not_eligible_for_sms(): void
    {
        $this->activeProvider();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        Assessment::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'subject_id' => $subject->id,
            'term_id' => $exam->term_id,
            'examination_id' => $exam->id,
            'status' => 'returned',
            'type' => AssessmentType::Exam,
            'title' => 'Returned Assessment',
            'score' => 89,
            'max_score' => 100,
            'recorded_by' => null,
        ]);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('total_students'));
        $this->assertCount(0, $response->json('students'));
    }

    // ------------------------------------------------------------------
    // Phone validation
    // ------------------------------------------------------------------

    /** @test */
    public function invalid_parent_phone_number_is_skipped_safely_on_send(): void
    {
        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $student = $this->createStudent();

        $this->createApprovedAssessment($student, $exam, $subject, 89, 100);

        $parent = User::create([
            'name' => 'Invalid Phone Parent',
            'email' => 'badphone-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'phone' => 'not-a-phone-number',
            'role' => UserRole::Parent,
            'status' => 'active',
        ]);
        $this->linkParent($student, $parent, true);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/students', ['examination_id' => $exam->id]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('without_phone'));

        $send = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$student->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $send->assertStatus(200);
        $this->assertEquals(0, $send->json('sent'));
        $this->assertEquals(1, $send->json('skipped'));
        $this->assertDatabaseMissing('sms_messages', ['category' => 'results']);
    }

    // ------------------------------------------------------------------
    // Provider response handling
    // ------------------------------------------------------------------

    /** @test */
    public function send_without_provider_message_ids_does_not_crash(): void
    {
        $this->activeProvider();
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'message' => 'Accepted',
                'data' => ['queued' => 1, 'senderId' => 'DARSMS'],
            ], 202),
        ]);

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals(1, $json['sent']);

        $message = SmsMessage::where('category', 'results')->first();
        $this->assertNotNull($message);
        $this->assertEquals('queued', $message->status);
        // Missing optional messageIds must not leave the record empty of a provider id
        // field — it is simply null rather than causing an error.
        $this->assertNull($message->provider_message_id);
    }

    // ------------------------------------------------------------------
    // SMS history
    // ------------------------------------------------------------------

    /** @test */
    public function results_sms_history_records_each_student_message_with_names(): void
    {
        $this->withoutExceptionHandling();

        $this->activeProvider();
        $this->fakeDarsmsSuccess();

        $exam = $this->createPublishedExamination();
        $subject = $exam->subjects->first();
        $created = $this->createStudentWithParent($exam, $subject, '0712345678');

        $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->post('/sms/results/send', [
                'examination_id' => $exam->id,
                'student_ids' => [$created['student']->id],
                'template' => 'Dear {{parent_name}}, results for {{student_name}}.',
            ]);

        $response = $this->actingAs($this->makeUser(UserRole::HeadOfSchool))
            ->get('/sms/results/history?examination_id='.$exam->id);

        $response->assertStatus(200);
        $response->assertInertia(fn ($assert) => $assert->component('Sms/ResultsHistory'));

        // The message was persisted and is visible in history.
        $this->assertDatabaseHas('sms_messages', [
            'to' => '255712345678',
            'category' => 'results',
            'metadata->student_id' => $created['student']->id,
            'metadata->parent_id' => $created['parent']->id,
            'metadata->examination_id' => $exam->id,
            'status' => 'queued',
        ]);
    }

    // ------------------------------------------------------------------
    // Cross-examination filtering
    // ------------------------------------------------------------------

    /** @test */
    public function another_examination_returns_only_results_for_that_examination(): void
    {
        $this->activeProvider();

        $year = AcademicYear::create([
            'name' => '2026-CrossExam', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $term = Term::create([
            'name' => 'Term 1', 'academic_year_id' => $year->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-03-31', 'is_current' => true,
        ]);

        $examA = Examination::create([
            'name' => 'Examination A', 'academic_year_id' => $year->id, 'term_id' => $term->id,
            'status' => 'published', 'created_by' => null,
        ]);
        $examB = Examination::create([
            'name' => 'Examination B', 'academic_year_id' => $year->id, 'term_id' => $term->id,
            'status' => 'published', 'created_by' => null,
        ]);

        $subjectA = Subject::create(['name' => 'Mathematics A', 'code' => 'MTHA', 'is_active' => true]);
        $subjectB = Subject::create(['name' => 'Mathematics B', 'code' => 'MTHB', 'is_active' => true]);
        $examA->subjects()->attach($subjectA->id, ['max_score' => 100]);
        $examB->subjects()->attach($subjectB->id, ['max_score' => 100]);

        $studentA = $this->createStudent(['reg_no' => 'GWD-XA-001']);
        $studentB = $this->createStudent(['reg_no' => 'GWD-XB-001']);

        $this->createApprovedAssessment($studentA, $examA, $subjectA, 80, 100);
        $this->createApprovedAssessment($studentB, $examB, $subjectB, 70, 100);

        $guardianA = User::create([
            'name' => 'Guardian A', 'email' => 'gxa-'.uniqid().'@godwin.ac.tz', 'password' => 'password',
            'phone' => '0711111111', 'role' => UserRole::Parent, 'status' => 'active',
        ]);
        $guardianB = User::create([
            'name' => 'Guardian B', 'email' => 'gxb-'.uniqid().'@godwin.ac.tz', 'password' => 'password',
            'phone' => '0752222222', 'role' => UserRole::Parent, 'status' => 'active',
        ]);
        $this->linkParent($studentA, $guardianA, true);
        $this->linkParent($studentB, $guardianB, true);

        $hos = $this->makeUser(UserRole::HeadOfSchool);

        $filteredA = $this->actingAs($hos)
            ->post('/sms/results/students', ['examination_id' => $examA->id]);
        $filteredA->assertStatus(200);
        $this->assertEquals(1, $filteredA->json('with_phone'));
        $this->assertEquals($studentA->id, $filteredA->json('students.0.student_id'));
        $this->assertCount(1, $filteredA->json('students'));

        $filteredB = $this->actingAs($hos)
            ->post('/sms/results/students', ['examination_id' => $examB->id]);
        $filteredB->assertStatus(200);
        $this->assertEquals(1, $filteredB->json('with_phone'));
        $this->assertEquals($studentB->id, $filteredB->json('students.0.student_id'));
        $this->assertCount(1, $filteredB->json('students'));
    }

    // ------------------------------------------------------------------
    // API error handling
    // ------------------------------------------------------------------

    /** @test */
    public function students_list_api_returns_error_for_invalid_examination(): void
    {
        $user = $this->makeUser(UserRole::HeadOfSchool);

        $response = $this->actingAs($user)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post('/sms/results/students', ['examination_id' => 99999]);

        // The API reports a validation error rather than silently returning an empty list.
        $response->assertStatus(422);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ClassLevel;
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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature coverage for the teacher marks submission workflow:
 * draft save -> submit -> Head of School approve/return -> revise & resubmit.
 */
class TeacherResultSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Marker Teacher',
            'email' => 'marker-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::Teacher,
            'status' => 'active',
        ], $extra));
    }

    private function headOfSchool(): User
    {
        return User::create([
            'name' => 'Head of School',
            'email' => 'hos-'.uniqid().'@godwin.ac.tz',
            'password' => 'password',
            'role' => UserRole::HeadOfSchool,
            'status' => 'active',
        ]);
    }

    /** Set up a published examination the teacher is assigned to record marks for. */
    private function setUpExamination(User $teacher, array $studentOverrides = []): array
    {
        $class = SchoolClass::create(array_merge([
            'name' => 'KG1',
            'code' => 'KG1',
            'level' => ClassLevel::KG1,
            'is_active' => true,
            'capacity' => 25,
            'teacher_id' => $teacher->id,
        ], $studentOverrides['class'] ?? []));

        $subject = Subject::create([
            'name' => 'Phonics',
            'code' => 'PHON',
            'is_active' => true,
        ]);

        // Teacher is assigned to teach this subject in this class.
        DB::table('class_subject')->insert([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = Student::create(array_merge([
            'reg_no' => 'GWD-'.uniqid(),
            'first_name' => 'Amina',
            'last_name' => 'Juma',
            'gender' => 'female',
            'class_id' => $class->id,
            'status' => 'active',
            'admission_date' => now()->subMonths(2),
        ], $studentOverrides['student'] ?? []));

        $year = AcademicYear::create([
            'name' => '2026',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
            'status' => 'active',
        ]);
        $term = Term::create([
            'academic_year_id' => $year->id,
            'name' => 'Term 1',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'is_current' => true,
        ]);

        $exam = Examination::create([
            'name' => 'Mid Term',
            'academic_year_id' => $year->id,
            'term_id' => $term->id,
            'status' => 'published',
            'description' => null,
            'created_by' => $this->headOfSchool()->id,
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 100]);

        return [
            'teacher' => $teacher,
            'class' => $class,
            'subject' => $subject,
            'student' => $student,
            'exam' => $exam,
        ];
    }

    /** Payload for saving/submitting one student's result. */
    private function resultsPayload(array $context, array $overrides = []): array
    {
        return array_merge([
            'class_id' => $context['class']->id,
            'subject_id' => $context['subject']->id,
            'results' => [
                [
                    'student_id' => $context['student']->id,
                    'score' => 70,
                    'remarks' => null,
                ],
            ],
        ], $overrides);
    }

    /* ------------------------------------------------------------------ */
    /* Draft save */
    /* ------------------------------------------------------------------ */

    public function test_teacher_can_save_marks_as_draft(): void
    {
        $context = $this->setUpExamination($this->teacher());
        $response = $this->actingAs($context['teacher'])
            ->put(route('teacher.results.save', $context['exam']), $this->resultsPayload($context));

        $response->assertRedirect()->assertSessionHas('success');

        $assessment = Assessment::forExamination($context['exam']->id)
            ->where('student_id', $context['student']->id)
            ->where('subject_id', $context['subject']->id)
            ->first();

        $this->assertNotNull($assessment);
        $this->assertSame('draft', $assessment->status);
        $this->assertNull($assessment->submitted_at);
        $this->assertSame(70.00, (float) $assessment->score);
    }

    /* ------------------------------------------------------------------ */
    /* Submit */
    /* ------------------------------------------------------------------ */

    public function test_teacher_can_submit_draft_marks_and_submitted_at_is_populated(): void
    {
        $context = $this->setUpExamination($this->teacher());

        $this->actingAs($context['teacher'])
            ->put(route('teacher.results.save', $context['exam']), $this->resultsPayload($context))
            ->assertRedirect();

        $response = $this->actingAs($context['teacher'])
            ->post(route('teacher.results.submit', $context['exam']), [
                'class_id' => $context['class']->id,
                'subject_id' => $context['subject']->id,
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $assessment = Assessment::forExamination($context['exam']->id)
            ->where('student_id', $context['student']->id)
            ->where('subject_id', $context['subject']->id)
            ->first();

        $this->assertSame('submitted', $assessment->status);
        $this->assertNotNull($assessment->submitted_at);
        $this->assertTrue($assessment->submitted_at->lte(now()));
        $this->assertSame(70.00, (float) $assessment->score);
    }

    public function test_already_submitted_marks_cannot_be_resubmitted(): void
    {
        $context = $this->setUpExamination($this->teacher());

        $this->actingAs($context['teacher'])
            ->put(route('teacher.results.save', $context['exam']), $this->resultsPayload($context))
            ->assertRedirect();
        $this->actingAs($context['teacher'])
            ->post(route('teacher.results.submit', $context['exam']), [
                'class_id' => $context['class']->id,
                'subject_id' => $context['subject']->id,
            ])
            ->assertRedirect()->assertSessionHas('success');

        $response = $this->actingAs($context['teacher'])
            ->post(route('teacher.results.submit', $context['exam']), [
                'class_id' => $context['class']->id,
                'subject_id' => $context['subject']->id,
            ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertCount(1, Assessment::forExamination($context['exam']->id)
            ->where('status', 'submitted')
            ->get());
    }

    public function test_submitted_marks_cannot_be_modified_via_save(): void
    {
        $context = $this->setUpExamination($this->teacher());

        $this->actingAs($context['teacher'])
            ->put(route('teacher.results.save', $context['exam']), $this->resultsPayload($context))
            ->assertRedirect();
        $this->actingAs($context['teacher'])
            ->post(route('teacher.results.submit', $context['exam']), [
                'class_id' => $context['class']->id,
                'subject_id' => $context['subject']->id,
            ])
            ->assertRedirect();

        $response = $this->actingAs($context['teacher'])
            ->put(route('teacher.results.save', $context['exam']), $this->resultsPayload($context, [
                'results' => [['student_id' => $context['student']->id, 'score' => 90, 'remarks' => 'changed']],
            ]));

        $response->assertStatus(403);
        $this->assertSame(70.00, (float) Assessment::forExamination($context['exam']->id)
            ->where('student_id', $context['student']->id)
            ->value('score'));
    }

    /* ------------------------------------------------------------------ */
    /* Head of School approve / return */
    /* ------------------------------------------------------------------ */

    public function test_head_of_school_can_approve_submitted_marks(): void
    {
        $context = $this->setUpExamination($this->teacher());
        $hos = $this->headOfSchool();

        $this->submitDraft($context);

        $response = $this->actingAs($hos)
            ->post(route('examinations.approve', $context['exam']), [
                'class_id' => $context['class']->id,
                'subject_id' => $context['subject']->id,
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertSame('approved', Assessment::forExamination($context['exam']->id)
            ->where('student_id', $context['student']->id)
            ->value('status'));
    }

    public function test_head_of_school_can_return_submitted_marks_without_erasing_scores(): void
    {
        $context = $this->setUpExamination($this->teacher());
        $hos = $this->headOfSchool();

        $this->submitDraft($context);

        $response = $this->actingAs($hos)
            ->post(route('examinations.return', $context['exam']), [
                'class_id' => $context['class']->id,
                'subject_id' => $context['subject']->id,
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $assessment = Assessment::forExamination($context['exam']->id)
            ->where('student_id', $context['student']->id)
            ->first();

        $this->assertSame('returned', $assessment->status);
        $this->assertSame(70.00, (float) $assessment->score); // scores preserved
    }

    public function test_approved_marks_cannot_be_modified_by_teacher(): void
    {
        $context = $this->setUpExamination($this->teacher());
        $hos = $this->headOfSchool();

        $this->submitDraft($context);
        $this->actingAs($hos)
            ->post(route('examinations.approve', $context['exam']), [
                'class_id' => $context['class']->id,
                'subject_id' => $context['subject']->id,
            ])
            ->assertRedirect();

        $response = $this->actingAs($context['teacher'])
            ->put(route('teacher.results.save', $context['exam']), $this->resultsPayload($context, [
                'results' => [['student_id' => $context['student']->id, 'score' => 90]],
            ]));

        $response->assertStatus(403);
    }

    /* ------------------------------------------------------------------ */
    /* Returned marks: teacher revises and resubmits */
    /* ------------------------------------------------------------------ */

    public function test_teacher_can_revise_and_resubmit_returned_marks(): void
    {
        $context = $this->setUpExamination($this->teacher());
        $hos = $this->headOfSchool();

        $this->submitDraft($context);
        $this->actingAs($hos)
            ->post(route('examinations.return', $context['exam']), [
                'class_id' => $context['class']->id,
                'subject_id' => $context['subject']->id,
            ])
            ->assertRedirect();

        // Teacher revises the returned result.
        $this->actingAs($context['teacher'])
            ->put(route('teacher.results.save', $context['exam']), $this->resultsPayload($context, [
                'results' => [['student_id' => $context['student']->id, 'score' => 85, 'remarks' => 'revised']],
            ]))
            ->assertRedirect();

        $this->assertSame(85.00, (float) Assessment::forExamination($context['exam']->id)
            ->where('student_id', $context['student']->id)
            ->value('score'));

        // Teacher resubmits the revised result.
        $this->actingAs($context['teacher'])
            ->post(route('teacher.results.submit', $context['exam']), [
                'class_id' => $context['class']->id,
                'subject_id' => $context['subject']->id,
            ])
            ->assertRedirect()->assertSessionHas('success');

        $assessment = Assessment::forExamination($context['exam']->id)
            ->where('student_id', $context['student']->id)
            ->first();

        $this->assertSame('submitted', $assessment->status);
        $this->assertNotNull($assessment->submitted_at);
        $this->assertSame('revised', $assessment->remarks);
    }

    private function submitDraft(array $context): void
    {
        $this->actingAs($context['teacher'])
            ->put(route('teacher.results.save', $context['exam']), $this->resultsPayload($context))
            ->assertRedirect();
        $this->actingAs($context['teacher'])
            ->post(route('teacher.results.submit', $context['exam']), [
                'class_id' => $context['class']->id,
                'subject_id' => $context['subject']->id,
            ])
            ->assertRedirect()->assertSessionHas('success');
    }
}

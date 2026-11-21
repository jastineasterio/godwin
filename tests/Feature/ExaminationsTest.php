<?php

namespace Tests\Feature;

use App\Enums\ClassLevel;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExaminationsTest extends TestCase
{
    use RefreshDatabase;

    private int $userSeq = 0;

    private function userFor(UserRole $role, array $extra = []): User
    {
        $this->userSeq++;

        return User::create(array_merge([
            'name' => 'Exams '.ucfirst($role->value),
            'email' => 'exams_'.$this->userSeq.'_'.$role->value.'@godwin.ac.tz',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ], $extra));
    }

    private function makeExamination(array $overrides = []): Examination
    {
        $year = AcademicYear::create(array_merge([
            'name' => '2026', 'start_date' => now()->subMonth(), 'end_date' => now()->addMonth(),
            'is_current' => true,
        ], $overrides['year'] ?? []));
        $term = Term::create(array_merge([
            'academic_year_id' => $year->id, 'name' => 'Term 1', 'start_date' => now(),
            'end_date' => now()->addMonth(), 'is_current' => true,
        ], $overrides['term'] ?? []));
        $subject = Subject::create(array_merge([
            'name' => 'Phonics', 'code' => 'PHON', 'is_active' => true,
        ], $overrides['subject'] ?? []));

        $exam = Examination::create(array_merge([
            'name' => 'Mid Term', 'academic_year_id' => $year->id, 'term_id' => $term->id,
            'status' => 'draft', 'description' => null, 'created_by' => $this->head->id,
        ], $overrides['exam'] ?? []));
        $exam->classes()->attach($this->testClass->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 100]);

        return $exam;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->head = $this->userFor(UserRole::HeadOfSchool);
        $this->testClass = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => ClassLevel::KG1, 'is_active' => true,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Index access                                                       */
    /* ------------------------------------------------------------------ */

    public function test_examinations_index_loads_for_head_of_school_and_admin(): void
    {
        $this->makeExamination();

        foreach ([UserRole::HeadOfSchool, UserRole::Admin] as $role) {
            $user = $this->userFor($role);
            $this->actingAs($user)
                ->get('/examinations')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Examinations/Index')
                    ->has('examinations')
                    ->has('filters'));
        }
    }

    public function test_teacher_cannot_access_examinations_index(): void
    {
        $teacher = $this->userFor(UserRole::Teacher);
        $this->actingAs($teacher)->get('/examinations')->assertRedirect('/dashboard');
    }

    public function test_empty_examination_list_renders(): void
    {
        $this->actingAs($this->head)
            ->get('/examinations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Examinations/Index')
                ->where('examinations.data', []));
    }

    public function test_existing_examinations_render(): void
    {
        $this->makeExamination();

        $this->actingAs($this->head)
            ->get('/examinations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Examinations/Index')
                ->has('examinations.data', 1));
    }

    /* ------------------------------------------------------------------ */
    /* Analytics route must not replace the index page                    */
    /* ------------------------------------------------------------------ */

    public function test_analytics_route_renders_separately_from_index(): void
    {
        $exam = $this->makeExamination();

        $this->actingAs($this->head)
            ->get(route('examinations.analytics', $exam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Examinations/Analytics'));

        // The index page still renders its own component (analytics did not replace it)
        $this->actingAs($this->head)
            ->get('/examinations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Examinations/Index'));
    }

    public function test_analytics_requires_head_of_school_or_admin(): void
    {
        $exam = $this->makeExamination();
        $teacher = $this->userFor(UserRole::Teacher);
        $this->actingAs($teacher)->get(route('examinations.analytics', $exam))->assertRedirect('/dashboard');
    }

    /* ------------------------------------------------------------------ */
    /* Edit access                                                        */
    /* ------------------------------------------------------------------ */

    public function test_head_of_school_can_open_draft_examination_edit_page(): void
    {
        $exam = $this->makeExamination();

        $this->actingAs($this->head)
            ->get(route('examinations.edit', $exam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Examinations/Edit')
                ->where('examination.id', $exam->id)
                ->where('examination.status', 'draft'));
    }

    public function test_admin_can_open_draft_examination_edit_page(): void
    {
        $exam = $this->makeExamination();
        $admin = $this->userFor(UserRole::Admin);

        $this->actingAs($admin)
            ->get(route('examinations.edit', $exam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Examinations/Edit'));
    }

    public function test_cannot_edit_published_examination(): void
    {
        $exam = $this->makeExamination(['exam' => ['status' => 'published']]);

        $response = $this->actingAs($this->head)
            ->get(route('examinations.edit', $exam))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft examinations can be edited.');

        $this->assertSame('published', $exam->fresh()->status);
    }

    public function test_cannot_edit_closed_examination(): void
    {
        $exam = $this->makeExamination(['exam' => ['status' => 'closed']]);

        $response = $this->actingAs($this->head)
            ->get(route('examinations.edit', $exam))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only draft examinations can be edited.');

        $this->assertSame('closed', $exam->fresh()->status);
    }

    public function test_teacher_cannot_edit_examination(): void
    {
        $exam = $this->makeExamination();
        $teacher = $this->userFor(UserRole::Teacher);

        $this->actingAs($teacher)
            ->get(route('examinations.edit', $exam))
            ->assertRedirect('/dashboard');
    }

    public function test_can_update_draft_examination(): void
    {
        $exam = $this->makeExamination();

        $this->actingAs($this->head)
            ->put(route('examinations.update', $exam), [
                'name' => 'Updated Mid Term',
                'academic_year_id' => $exam->academic_year_id,
                'term_id' => $exam->term_id,
                'description' => 'Updated description.',
                'class_ids' => [$this->testClass->id],
                'subjects' => [['subject_id' => \App\Models\Subject::first()->id, 'max_score' => 100]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Updated Mid Term', $exam->fresh()->name);
        $this->assertSame('draft', $exam->fresh()->status);
    }

    /* ------------------------------------------------------------------ */
    /* Status transitions (publish / reopen / close)                      */
    /* ------------------------------------------------------------------ */

    public function test_examination_creation_defaults_to_draft(): void
    {
        $exam = $this->makeExamination();

        $this->assertSame('draft', $exam->fresh()->status);
    }

    public function test_head_of_school_can_publish_valid_examination(): void
    {
        $exam = $this->makeExamination();

        $this->actingAs($this->head)
            ->post(route('examinations.publish', $exam))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('published', $exam->fresh()->status);
    }

    public function test_teacher_cannot_publish_examination(): void
    {
        $exam = $this->makeExamination();
        $teacher = $this->userFor(UserRole::Teacher);

        $this->actingAs($teacher)
            ->post(route('examinations.publish', $exam))
            ->assertRedirect('/dashboard');
    }

    public function test_cannot_publish_examination_without_classes(): void
    {
        $year = AcademicYear::create([
            'name' => '2026', 'start_date' => now()->subMonth(), 'end_date' => now()->addMonth(), 'is_current' => true,
        ]);
        $term = Term::create([
            'academic_year_id' => $year->id, 'name' => 'Term 1', 'start_date' => now(),
            'end_date' => now()->addMonth(), 'is_current' => true,
        ]);
        $exam = Examination::create([
            'name' => 'No Classes', 'academic_year_id' => $year->id, 'term_id' => $term->id,
            'status' => 'draft', 'created_by' => $this->head->id,
        ]);

        $this->actingAs($this->head)
            ->post(route('examinations.publish', $exam))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('draft', $exam->fresh()->status);
    }

    public function test_cannot_publish_examination_without_subjects(): void
    {
        $year = AcademicYear::create([
            'name' => '2026', 'start_date' => now()->subMonth(), 'end_date' => now()->addMonth(), 'is_current' => true,
        ]);
        $term = Term::create([
            'academic_year_id' => $year->id, 'name' => 'Term 1', 'start_date' => now(),
            'end_date' => now()->addMonth(), 'is_current' => true,
        ]);
        $class = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1-B', 'level' => ClassLevel::KG1, 'is_active' => true,
        ]);
        $exam = Examination::create([
            'name' => 'No Subjects', 'academic_year_id' => $year->id, 'term_id' => $term->id,
            'status' => 'draft', 'created_by' => $this->head->id,
        ]);
        $exam->classes()->attach($class->id);

        $this->actingAs($this->head)
            ->post(route('examinations.publish', $exam))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('draft', $exam->fresh()->status);
    }

    public function test_head_of_school_can_reopen_published_examination_without_results(): void
    {
        $exam = $this->makeExamination();

        $this->actingAs($this->head)
            ->post(route('examinations.publish', $exam))
            ->assertRedirect();

        $this->actingAs($this->head)
            ->post(route('examinations.reopen', $exam))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('draft', $exam->fresh()->status);
    }

    public function test_cannot_reopen_examination_with_submitted_results(): void
    {
        $exam = $this->makeExamination();

        $this->actingAs($this->head)
            ->post(route('examinations.publish', $exam))
            ->assertRedirect();

        // Simulate a submitted result
        $year = AcademicYear::first();
        $term = Term::first();
        $student = \App\Models\Student::create([
            'reg_no' => 'GWD-100', 'first_name' => 'Amina', 'last_name' => 'Juma',
            'gender' => 'female', 'class_id' => $this->testClass->id, 'status' => 'active',
            'admission_date' => now()->subMonths(2),
        ]);
        $subject = \App\Models\Subject::first();

        \App\Models\Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student->id,
            'class_id' => $this->testClass->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Mid Term - Phonics', 'type' => 'exam', 'score' => 15, 'max_score' => 20,
            'recorded_by' => $this->head->id, 'status' => 'submitted',
        ]);

        $this->actingAs($this->head)
            ->post(route('examinations.reopen', $exam))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('published', $exam->fresh()->status);
    }

    public function test_head_of_school_can_close_published_examination(): void
    {
        $exam = $this->makeExamination();

        $this->actingAs($this->head)
            ->post(route('examinations.publish', $exam))
            ->assertRedirect();

        $this->actingAs($this->head)
            ->post(route('examinations.close', $exam))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('closed', $exam->fresh()->status);
    }

    public function test_teacher_cannot_change_examination_status(): void
    {
        $exam = $this->makeExamination();
        $teacher = $this->userFor(UserRole::Teacher);

        $this->actingAs($teacher)
            ->post(route('examinations.publish', $exam))
            ->assertRedirect('/dashboard');

        $this->assertSame('draft', $exam->fresh()->status);
    }
}

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

class DebugTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role, string $email = ''): User
    {
        return User::create([
            'name' => 'X', 'email' => $email ?: $role->value . '-' . uniqid() . '@godwin.ac.tz',
            'password' => 'password', 'role' => $role, 'status' => 'active',
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

    private function makeAcademicYear(string $name = '2026'): AcademicYear
    {
        return AcademicYear::create([
            'name' => $name, 'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(), 'is_current' => true, 'status' => 'active',
        ]);
    }

    private function makeTerm(AcademicYear $year): Term
    {
        return Term::create([
            'academic_year_id' => $year->id, 'name' => 'First Term',
            'start_date' => now()->startOfMonth(), 'end_date' => now()->endOfMonth(), 'is_current' => true,
        ]);
    }

    public function test_debug_update(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = $this->makeStudent($class, 'Amina');
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term Exam', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published', 'created_by' => $hos->id,
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        $assessment = Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student->id,
            'class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'title' => 'Mid Term - Phonics', 'type' => 'exam', 'score' => 15,
            'max_score' => 20, 'recorded_by' => $teacher->id, 'status' => 'draft',
        ]);

        $response = $this->actingAs($teacher)
            ->put("/assessments/{$assessment->id}", [
                'student_id' => $student->id,
                'title' => 'Mid Term - Phonics',
                'score' => 18,
            ]);

        fwrite(STDERR, "\nDEBUG STATUS: " . $response->getStatusCode() . "\n");
        $row = Assessment::find($assessment->id);
        fwrite(STDERR, "DEBUG SCORE: " . var_export($row->score, true) . "\n");
        fwrite(STDERR, "DEBUG DATA: " . json_encode($row->toArray()) . "\n");

        $this->assertTrue(true);
    }
}

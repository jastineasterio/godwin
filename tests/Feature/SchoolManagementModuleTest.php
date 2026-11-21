<?php

namespace Tests\Feature;

use App\Enums\ClassLevel;
use App\Enums\ExpenseCategory;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AcademicYear;
use App\Models\Asset;
use App\Models\Expense;
use App\Models\Examination;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolManagementModuleTest extends TestCase
{
    use RefreshDatabase;

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

    /* ---------------- Subjects ---------------- */

    public function test_head_of_school_can_register_subject(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass();

        $this->actingAs($hos)
            ->post('/subjects', [
                'name' => 'Phonics',
                'code' => 'PHO-101',
                'description' => 'Letter sounds and blending',
                'class_ids' => [$class->id],
                'teacher_ids' => [],
                'is_active' => true,
            ])
            ->assertRedirect(route('subjects.index'));

        $this->assertDatabaseHas('subjects', ['name' => 'Phonics', 'code' => 'PHO-101', 'is_active' => 1]);
        $this->assertDatabaseHas('class_subject', [
            'subject_id' => Subject::where('code', 'PHO-101')->first()->id,
            'class_id' => $class->id,
        ]);
    }

    public function test_duplicate_subject_code_is_rejected(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        Subject::create(['name' => 'Phonics', 'code' => 'PHO-101', 'is_active' => true]);

        $this->actingAs($hos)
            ->post('/subjects', [
                'name' => 'Phonics 2',
                'code' => 'PHO-101',
                'class_ids' => [],
                'is_active' => true,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_teacher_cannot_access_subjects(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($teacher)->get('/subjects')->assertRedirect('/dashboard');
        $this->actingAs($teacher)->post('/subjects', ['name' => 'X'])->assertRedirect('/dashboard');
    }

    public function test_head_of_school_can_deactivate_subject(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHO-101', 'is_active' => true]);

        $this->actingAs($hos)
            ->put("/subjects/{$subject->id}", [
                'name' => 'Phonics',
                'code' => 'PHO-101',
                'class_ids' => [],
                'is_active' => false,
            ])
            ->assertRedirect(route('subjects.index'));

        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'is_active' => 0]);
    }

    public function test_subject_with_assessments_cannot_be_deleted(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass();
        $student = \App\Models\Student::create([
            'reg_no' => 'GWD-' . uniqid(), 'first_name' => 'Amina', 'last_name' => 'Juma',
            'gender' => 'male', 'class_id' => $class->id, 'status' => 'active',
        ]);
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHO-101', 'is_active' => true]);

        \App\Models\Assessment::create([
            'student_id' => $student->id, 'class_id' => $class->id,
            'subject_id' => $subject->id, 'title' => 'Test', 'type' => 'test',
            'score' => 15, 'max_score' => 20, 'recorded_by' => $hos->id,
        ]);

        $this->actingAs($hos)
            ->delete("/subjects/{$subject->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    /* ---------------- Teachers ---------------- */

    public function test_head_of_school_can_register_teacher(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);

        $this->actingAs($hos)
            ->post('/teachers', [
                'name' => 'Amina Juma',
                'email' => 'amina.juma@godwin.ac.tz',
                'phone' => '0757123456',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'status' => 'active',
            ])
            ->assertRedirect(route('teachers.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Amina Juma', 'email' => 'amina.juma@godwin.ac.tz',
            'role' => UserRole::Teacher->value, 'status' => 'active',
        ]);
        $this->assertTrue(password_verify('secret123', User::where('email', 'amina.juma@godwin.ac.tz')->first()->password));
    }

    public function test_teacher_password_is_hashed(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);

        $this->actingAs($hos)
            ->post('/teachers', [
                'name' => 'Amina Juma',
                'email' => 'amina.juma@godwin.ac.tz',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'status' => 'active',
            ]);

        $user = User::where('email', 'amina.juma@godwin.ac.tz')->first();
        $this->assertTrue(password_verify('secret123', $user->password));
    }

    public function test_duplicate_email_is_rejected_for_teacher(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        User::create([
            'name' => 'Existing', 'email' => 'dup@godwin.ac.tz',
            'password' => 'password', 'role' => UserRole::Teacher, 'status' => 'active',
        ]);

        $this->actingAs($hos)
            ->post('/teachers', [
                'name' => 'Dup Two', 'email' => 'dup@godwin.ac.tz',
                'password' => 'secret123', 'password_confirmation' => 'secret123',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_teacher_cannot_access_teacher_management(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($teacher)->get('/teachers')->assertRedirect('/dashboard');
        $this->actingAs($teacher)->post('/teachers', ['name' => 'X'])->assertRedirect('/dashboard');
    }

    public function test_head_of_school_can_deactivate_teacher(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($hos)
            ->patch("/teachers/{$teacher->id}/status", ['status' => 'suspended'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'status' => UserStatus::Suspended->value]);
    }

    /* ---------------- Teaching Assignments ---------------- */

    public function test_subject_can_be_assigned_to_multiple_classes(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $c1 = $this->makeClass(null, 'KG1-A');
        $c2 = $this->makeClass(null, 'KG2-B');

        $this->actingAs($hos)
            ->post('/subjects', [
                'name' => 'Phonics', 'code' => 'PHO-102', 'class_ids' => [$c1->id, $c2->id],
                'is_active' => true,
            ]);

        $newSubject = Subject::where('code', 'PHO-102')->first();

        $this->assertDatabaseHas('class_subject', ['subject_id' => $newSubject->id, 'class_id' => $c1->id]);
        $this->assertDatabaseHas('class_subject', ['subject_id' => $newSubject->id, 'class_id' => $c2->id]);
    }

    public function test_duplicate_class_subject_assignment_is_prevented(): void
    {
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHO-101', 'is_active' => true]);
        $subject->classes()->attach($class->id, ['teacher_id' => null]);

        // Unique constraint on (class_id, subject_id) prevents duplicates
        $this->expectException(\Illuminate\Database\QueryException::class);
        $subject->classes()->attach($class->id, ['teacher_id' => null]);
    }

    /* ---------------- Finance ---------------- */

    public function test_finance_dashboard_returns_real_figures(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass();

        $student = \App\Models\Student::create([
            'reg_no' => 'GWD-' . uniqid(), 'first_name' => 'Amina', 'last_name' => 'Juma',
            'gender' => 'male', 'class_id' => $class->id, 'status' => 'active',
        ]);

        $fee = FeeStructure::create([
            'name' => 'Tuition', 'type' => 'tuition', 'amount' => 100000,
            'frequency' => 'termly', 'class_id' => $class->id, 'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-001', 'student_id' => $student->id,
            'academic_year_id' => $year->id, 'term_id' => $term->id,
            'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 100000, 'discount' => 0, 'total_amount' => 100000,
            'amount_paid' => 0, 'status' => InvoiceStatus::Unpaid,
            'issued_by' => $hos->id,
        ]);

        Payment::create([
            'receipt_number' => 'RCT-001', 'invoice_id' => $invoice->id,
            'student_id' => $student->id, 'amount' => 60000,
            'method' => 'cash', 'status' => PaymentStatus::Completed,
            'paid_at' => now(), 'received_by' => $hos->id,
        ]);

        Expense::create([
            'title' => 'Stationery', 'category' => ExpenseCategory::Supplies,
            'amount' => 20000, 'incurred_on' => now(),
            'recorded_by' => $hos->id,
        ]);

        $this->actingAs($hos)
            ->get('/finance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Dashboard')
                ->where('stats.total_income', 60000)
                ->where('stats.total_expenses', 20000)
                ->where('stats.net_balance', 40000)
            );
    }

    public function test_finance_date_filter_applies(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);

        Expense::create([
            'title' => 'Old Expense', 'category' => ExpenseCategory::Supplies,
            'amount' => 50000, 'incurred_on' => now()->subYear()->toDateString(),
            'recorded_by' => $hos->id,
        ]);
        Expense::create([
            'title' => 'Recent Expense', 'category' => ExpenseCategory::Supplies,
            'amount' => 10000, 'incurred_on' => now()->toDateString(),
            'recorded_by' => $hos->id,
        ]);

        $this->actingAs($hos)
            ->get('/finance?period=current_month')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('range.period', 'current_month')
            );
    }

    public function test_finance_report_returns_transactions(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);

        $this->actingAs($hos)
            ->get('/finance/report')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Report')
                ->has('stats')
                ->has('transactions')
            );
    }

    public function test_accountant_can_access_finance(): void
    {
        $accountant = $this->makeUser(UserRole::Accountant);

        $this->actingAs($accountant)->get('/finance')->assertOk();
        $this->actingAs($accountant)->get('/expenses')->assertOk();
    }

    public function test_teacher_cannot_access_finance(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($teacher)->get('/finance')->assertRedirect('/dashboard');
        $this->actingAs($teacher)->get('/expenses')->assertRedirect('/dashboard');
    }

    public function test_expense_can_be_created_and_voided(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);

        $this->actingAs($hos)
            ->post('/expenses', [
                'title' => 'Utility Bill',
                'category' => 'utilities',
                'amount' => 50000,
                'incurred_on' => now()->toDateString(),
                'reference' => 'REF-123',
                'description' => 'Monthly electricity',
            ])
            ->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', ['title' => 'Utility Bill', 'amount' => 50000]);

        $expense = Expense::first();
        $this->actingAs($hos)
            ->delete("/expenses/{$expense->id}")
            ->assertRedirect(route('expenses.index'));

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    /* ---------------- Examination Analytics ---------------- */

    public function test_head_of_school_can_view_examination_analytics(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);
        $class = $this->makeClass($teacher, 'KG1-A');
        $student = \App\Models\Student::create([
            'reg_no' => 'GWD-' . uniqid(), 'first_name' => 'Amina', 'last_name' => 'Juma',
            'gender' => 'male', 'class_id' => $class->id, 'status' => 'active',
        ]);
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHO-101', 'is_active' => true]);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_score' => 20]);

        \App\Models\Assessment::create([
            'examination_id' => $exam->id, 'student_id' => $student->id,
            'class_id' => $class->id, 'subject_id' => $subject->id,
            'term_id' => $term->id, 'title' => 'Mid Term - Phonics',
            'type' => 'exam', 'score' => 18, 'max_score' => 20,
            'recorded_by' => $teacher->id, 'status' => 'approved',
        ]);

        $this->actingAs($hos)
            ->get("/examinations/{$exam->id}/analytics")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Examinations/Analytics')
                ->where('summary.overall_average', 90)
                ->where('summary.pass_rate', 100)
            );
    }

    public function test_teacher_cannot_view_examination_analytics(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $year = $this->makeAcademicYear();
        $term = $this->makeTerm($year);

        $exam = Examination::create([
            'name' => 'Mid Term', 'academic_year_id' => $year->id,
            'term_id' => $term->id, 'status' => 'published',
        ]);

        $this->actingAs($teacher)
            ->get("/examinations/{$exam->id}/analytics")
            ->assertRedirect('/dashboard');
    }

    /* ---------------- Assets ---------------- */

    public function test_head_of_school_can_register_asset(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);

        $this->actingAs($hos)
            ->post('/facilities', [
                'name' => 'Desktop Computer',
                'category' => 'electronics',
                'asset_code' => 'GW-0001',
                'quantity' => 2,
                'purchase_date' => now()->toDateString(),
                'purchase_price' => 500000,
                'supplier' => 'Tech Co',
                'location' => 'Office',
                'condition' => 'good',
                'status' => 'active',
            ])
            ->assertRedirect(route('facilities.index'));

        $this->assertDatabaseHas('assets', [
            'name' => 'Desktop Computer', 'asset_code' => 'GW-0001',
            'quantity' => 2, 'status' => 'active',
        ]);
    }

    public function test_duplicate_asset_code_is_rejected(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        Asset::create([
            'name' => 'Chair', 'category' => 'furniture', 'asset_code' => 'GW-001',
            'quantity' => 1, 'condition' => 'good', 'status' => 'active',
        ]);

        $this->actingAs($hos)
            ->post('/facilities', [
                'name' => 'Chair 2', 'category' => 'furniture',
                'asset_code' => 'GW-001', 'quantity' => 1,
                'condition' => 'good', 'status' => 'active',
            ])
            ->assertSessionHasErrors('asset_code');
    }

    public function test_asset_inventory_summary_is_returned(): void
    {
        $hos = $this->makeUser(UserRole::HeadOfSchool);
        Asset::create([
            'name' => 'Chair', 'category' => 'furniture', 'asset_code' => 'GW-002',
            'quantity' => 10, 'condition' => 'good', 'status' => 'active',
        ]);
        Asset::create([
            'name' => 'Desk', 'category' => 'furniture', 'asset_code' => 'GW-003',
            'quantity' => 5, 'condition' => 'damaged', 'status' => 'active',
        ]);

        $this->actingAs($hos)
            ->get('/facilities')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facilities/Index')
                ->where('summary.total_quantity', 15)
                ->where('summary.damaged', 5)
            );
    }

    public function test_teacher_cannot_access_facilities(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($teacher)->get('/facilities')->assertRedirect('/dashboard');
        $this->actingAs($teacher)->post('/facilities', ['name' => 'X'])->assertRedirect('/dashboard');
    }

    /* ---------------- Cross-school / unauthorized access ---------------- */

    public function test_parent_cannot_access_head_of_school_pages(): void
    {
        $parent = $this->makeUser(UserRole::Parent);

        $this->actingAs($parent)->get('/subjects')->assertRedirect('/dashboard');
        $this->actingAs($parent)->get('/teachers')->assertRedirect('/dashboard');
        $this->actingAs($parent)->get('/finance')->assertRedirect('/dashboard');
        $this->actingAs($parent)->get('/facilities')->assertRedirect('/dashboard');
        $this->actingAs($parent)->get('/examinations')->assertRedirect('/dashboard');
    }
}

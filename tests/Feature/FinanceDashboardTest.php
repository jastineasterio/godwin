<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $userSeq = 0;

    private function userFor(UserRole $role, array $extra = []): User
    {
        $this->userSeq++;

        return User::create(array_merge([
            'name' => 'Finance '.ucfirst($role->value),
            'email' => 'finance_'.$this->userSeq.'_'.$role->value.'@godwin.ac.tz',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ], $extra));
    }

    private function makeInvoice(array $overrides = []): Invoice
    {
        $student = Student::create(array_merge([
            'reg_no' => 'GWD-'.uniqid(), 'first_name' => 'Finance', 'last_name' => 'Student',
            'gender' => 'female', 'class_id' => $this->testClass->id, 'status' => 'active',
        ], $overrides['student'] ?? []));

        return Invoice::create(array_merge([
            'invoice_number' => 'INV-'.uniqid(), 'student_id' => $student->id,
            'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 100000, 'total_amount' => 100000, 'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid, 'issued_by' => $this->head->id,
        ], $overrides['invoice'] ?? []));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->head = $this->userFor(UserRole::HeadOfSchool);
        $this->testClass = SchoolClass::create([
            'name' => 'KG1', 'code' => 'KG1', 'level' => \App\Enums\ClassLevel::KG1, 'is_active' => true,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Access                                                             */
    /* ------------------------------------------------------------------ */

    public function test_finance_dashboard_loads_for_authorized_roles(): void
    {
        foreach ([UserRole::HeadOfSchool, UserRole::Admin, UserRole::Accountant] as $role) {
            $user = $this->userFor($role);
            $this->actingAs($user)
                ->get('/finance')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Finance/Dashboard')
                    ->has('stats')
                    ->where('stats.outstanding_fees', 0));
        }
    }

    public function test_unauthorized_roles_cannot_access_finance(): void
    {
        foreach ([UserRole::Teacher, UserRole::Parent] as $role) {
            $user = $this->userFor($role);
            $this->actingAs($user)->get('/finance')->assertRedirect('/dashboard');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Outstanding fees calculation                                       */
    /* ------------------------------------------------------------------ */

    public function test_outstanding_fees_for_unpaid_invoice(): void
    {
        $this->makeInvoice(['invoice' => ['total_amount' => 100000, 'amount_paid' => 0, 'status' => 'unpaid']]);

        $this->actingAs($this->head)
            ->get('/finance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Dashboard')
                ->where('stats.outstanding_fees', 100000));
    }

    public function test_outstanding_fees_for_partially_paid_invoice(): void
    {
        $this->makeInvoice(['invoice' => ['total_amount' => 100000, 'amount_paid' => 40000, 'status' => 'partial']]);

        $this->actingAs($this->head)
            ->get('/finance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Dashboard')
                ->where('stats.outstanding_fees', 60000));
    }

    public function test_fully_paid_invoice_contributes_zero_outstanding(): void
    {
        $this->makeInvoice(['invoice' => ['total_amount' => 100000, 'amount_paid' => 100000, 'status' => 'paid']]);

        $this->actingAs($this->head)
            ->get('/finance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Dashboard')
                ->where('stats.outstanding_fees', 0));
    }

    public function test_void_and_deleted_invoices_are_excluded(): void
    {
        $this->makeInvoice(['invoice' => ['total_amount' => 50000, 'amount_paid' => 0, 'status' => 'void']]);
        $this->makeInvoice(['invoice' => ['total_amount' => 70000, 'amount_paid' => 0, 'status' => 'overdue']]);
        $voided = $this->makeInvoice(['invoice' => ['total_amount' => 90000, 'amount_paid' => 0, 'status' => 'unpaid']]);
        $voided->delete();

        $this->actingAs($this->head)
            ->get('/finance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Dashboard')
                ->where('stats.outstanding_fees', 70000));
    }

    public function test_outstanding_fees_never_goes_negative(): void
    {
        $this->makeInvoice(['invoice' => [
            'total_amount' => 100000, 'amount_paid' => 150000, 'status' => 'partial',
        ]]);

        $this->actingAs($this->head)
            ->get('/finance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Dashboard')
                ->where('stats.outstanding_fees', 0));
    }

    public function test_income_is_based_on_payments_not_invoice_totals(): void
    {
        $invoice = $this->makeInvoice(['invoice' => ['total_amount' => 100000, 'amount_paid' => 0, 'status' => 'unpaid']]);
        Payment::create([
            'receipt_number' => 'RCT-DC-1', 'invoice_id' => $invoice->id,
            'student_id' => $invoice->student_id, 'amount' => 40000, 'method' => 'cash',
            'paid_at' => now(), 'status' => 'completed', 'received_by' => $this->head->id,
        ]);
        $invoice->update(['amount_paid' => 40000, 'status' => 'partial']);

        $this->actingAs($this->head)
            ->get('/finance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Dashboard')
                ->where('stats.total_income', 40000)
                ->where('stats.outstanding_fees', 60000));
    }

    public function test_date_filters_still_work(): void
    {
        $this->makeInvoice();

        $this->actingAs($this->head)
            ->get('/finance?period=custom&from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Dashboard')
                ->where('range.period', 'custom'));
    }

    public function test_report_and_pdf_include_outstanding_fees(): void
    {
        $this->makeInvoice(['invoice' => ['total_amount' => 100000, 'amount_paid' => 30000, 'status' => 'partial']]);

        $this->actingAs($this->head)
            ->get('/finance/report')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Report')
                ->where('outstanding_fees', 70000));

        $this->actingAs($this->head)
            ->get('/finance/report/pdf')
            ->assertOk();
    }
}

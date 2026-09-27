<?php

namespace Tests\Feature;

use App\Enums\ClassLevel;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 3 — functional modules:
 *  1. Student management + parent linking
 *  2. Attendance marking
 *  3. Assessments + printable report card
 *  4. Invoicing, payments, receipts, fee statements
 *  5. Announcements & direct messaging
 */
class ModulesTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------- Fixtures ---------------------------- */

    private function makeUser(UserRole $role, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst($role->value).' User',
            'email' => $role->value.'@godwin.ac.tz',
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
            'reg_no' => 'GWD-'.uniqid(), 'first_name' => $name, 'last_name' => 'Juma',
            'gender' => Gender::Female, 'class_id' => $class->id, 'status' => 'active',
            'admission_date' => now()->subMonths(2),
        ]);
    }

    /* --------------------- 1. Students & parents ---------------------- */

    public function test_head_of_school_registers_student_with_parent_in_one_step(): void
    {
        $head = $this->makeUser(UserRole::HeadOfSchool);
        $class = $this->makeClass();

        $this->actingAs($head)
            ->post('/students', [
                'reg_no' => 'GWD-2026-0100', 'first_name' => 'Amina', 'last_name' => 'Juma',
                'gender' => 'female', 'class_id' => $class->id, 'status' => 'active',
                'with_parent' => true, 'parent_name' => 'Bwana Juma',
                'parent_phone' => '0751000000', 'relationship_type' => 'father',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('students', ['reg_no' => 'GWD-2026-0100']);

        $parent = User::role(UserRole::Parent)->where('phone', '0751000000')->first();
        $this->assertNotNull($parent);

        $student = Student::where('reg_no', 'GWD-2026-0100')->first();

        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id, 'student_id' => $student->id,
            'relationship_type' => 'father', 'is_primary_contact' => 1,
        ]);
    }

    public function test_duplicate_registration_number_is_rejected(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $class = $this->makeClass();
        $this->makeStudent($class);

        $this->actingAs($admin)
            ->post('/students', [
                'reg_no' => Student::first()->reg_no, 'first_name' => 'Clone', 'last_name' => 'Child',
                'gender' => 'male', 'class_id' => $class->id, 'status' => 'active',
            ])
            ->assertSessionHasErrors('reg_no');
    }

    public function test_teacher_cannot_access_student_management(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($teacher)->get('/students')->assertRedirect('/dashboard');
        $this->actingAs($teacher)->get('/students/create')->assertRedirect('/dashboard');
    }

    public function test_parent_can_be_linked_and_unlinked(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $class = $this->makeClass();
        $student = $this->makeStudent($class);
        $parent = $this->makeUser(UserRole::Parent, ['phone' => '0751111111']);

        $this->actingAs($admin)->post("/students/{$student->id}/parents", [
            'parent_id' => $parent->id, 'relationship_type' => 'mother', 'is_primary_contact' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id, 'student_id' => $student->id, 'is_primary_contact' => 1,
        ]);

        $this->actingAs($admin)
            ->delete("/students/{$student->id}/parents/{$parent->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('parent_student', [
            'parent_id' => $parent->id, 'student_id' => $student->id,
        ]);
    }

    public function test_student_profile_lists_parents_and_stats(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $class = $this->makeClass();
        $student = $this->makeStudent($class);
        $parent = $this->makeUser(UserRole::Parent);

        $parent->students()->attach($student->id, [
            'relationship_type' => 'father', 'is_primary_contact' => true,
        ]);

        $this->actingAs($admin)->get("/students/{$student->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Students/Show')
                ->where('student.full_name', 'Amina Juma')
                ->has('parents', 1)
                ->has('stats')
            );
    }

    /* --------------------- 2. Attendance marking ---------------------- */

    public function test_teacher_marks_attendance_for_their_own_class(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher);
        $a = $this->makeStudent($class, 'Amina');
        $b = $this->makeStudent($class, 'Joshua');

        // Marking screen shows only the teacher's class roster
        $this->actingAs($teacher)
            ->get('/attendance')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Attendance/Index')
                ->has('roster', 2)
                ->where('classes', fn ($c) => $c->count() === 1)
            );

        $this->actingAs($teacher)
            ->post('/attendance', [
                'date' => now()->toDateString(),
                'class_id' => $class->id,
                'marks' => [
                    ['student_id' => $a->id, 'status' => 'present'],
                    ['student_id' => $b->id, 'status' => 'late'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance', [
            'student_id' => $a->id, 'status' => 'present', 'marked_by' => $teacher->id,
        ]);
        $this->assertDatabaseHas('attendance', ['student_id' => $b->id, 'status' => 'late']);
    }

    public function test_marking_the_same_day_twice_updates_instead_of_duplicating(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher);
        $student = $this->makeStudent($class);
        $date = now()->toDateString();

        $payload = [
            'date' => $date, 'class_id' => $class->id,
            'marks' => [['student_id' => $student->id, 'status' => 'present']],
        ];

        $this->actingAs($teacher)->post('/attendance', $payload);
        $this->actingAs($teacher)->post('/attendance', [
            'date' => $date, 'class_id' => $class->id,
            'marks' => [['student_id' => $student->id, 'status' => 'absent']],
        ]);

        // Still exactly ONE row (unique index + upsert), now updated
        $this->assertSame(1, Attendance::where('student_id', $student->id)->count());
        $this->assertSame('absent', Attendance::where('student_id', $student->id)->first()->status->value);
    }

    public function test_teacher_cannot_mark_a_class_they_do_not_own(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $otherTeacher = $this->makeUser(UserRole::Teacher, ['email' => 'other@godwin.ac.tz']);
        $class = $this->makeClass($otherTeacher, 'NUR-B');
        $student = $this->makeStudent($class);

        $this->actingAs($teacher)
            ->post('/attendance', [
                'date' => now()->toDateString(), 'class_id' => $class->id,
                'marks' => [['student_id' => $student->id, 'status' => 'present']],
            ])
            ->assertForbidden();
    }

    public function test_teacher_cannot_mark_students_from_another_class(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $own = $this->makeClass($teacher, 'KG1-A');
        $other = $this->makeClass(null, 'NUR-B');
        $foreign = $this->makeStudent($other, 'Foreign');

        $this->actingAs($teacher)
            ->post('/attendance', [
                'date' => now()->toDateString(), 'class_id' => $own->id,
                'marks' => [['student_id' => $foreign->id, 'status' => 'present']],
            ])
            ->assertSessionHasErrors('marks');
    }

    /* ------------------ 3. Assessments & report card ------------------ */

    public function test_teacher_records_an_assessment(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher);
        $student = $this->makeStudent($class);
        $subject = Subject::create(['name' => 'Phonics', 'code' => 'PHON']);

        $this->actingAs($teacher)
            ->post('/assessments', [
                'student_id' => $student->id, 'class_id' => $class->id,
                'subject_id' => $subject->id, 'title' => 'Week 4 Phonics Test',
                'type' => 'test', 'score' => 18, 'max_score' => 20,
                'obtained_at' => now()->toDateString(),
            ])
            ->assertRedirect(route('assessments.index'));

        $this->assertDatabaseHas('assessments', [
            'student_id' => $student->id, 'title' => 'Week 4 Phonics Test',
            'score' => 18, 'recorded_by' => $teacher->id,
        ]);
    }

    public function test_score_cannot_exceed_maximum(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher);
        $student = $this->makeStudent($class);

        $this->actingAs($teacher)
            ->post('/assessments', [
                'student_id' => $student->id, 'title' => 'Impossible', 'type' => 'test',
                'score' => 30, 'max_score' => 20,
            ])
            ->assertSessionHasErrors('score');
    }

    public function test_teacher_only_sees_assessments_for_their_own_classes(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $other = $this->makeUser(UserRole::Teacher, ['email' => 'other2@godwin.ac.tz']);
        $this->makeClass($other, 'NUR-B');

        $this->actingAs($teacher)->get('/assessments')->assertOk()
            ->assertInertia(fn ($page) => $page->where('classes', fn ($c) => $c->count() === 0));
    }

    public function test_report_card_renders_for_teacher_and_parent_but_not_strangers(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher);
        $student = $this->makeStudent($class);
        $parent = $this->makeUser(UserRole::Parent, ['phone' => '0752222222']);

        $parent->students()->attach($student->id, [
            'relationship_type' => 'mother', 'is_primary_contact' => true,
        ]);

        $this->actingAs($teacher)->get("/students/{$student->id}/report-card")->assertOk();
        $this->actingAs($parent)->get("/students/{$student->id}/report-card")->assertOk();

        $stranger = $this->makeUser(UserRole::Parent, [
            'email' => 'stranger@example.com', 'phone' => '0753333333',
        ]);
        $this->actingAs($stranger)->get("/students/{$student->id}/report-card")->assertForbidden();
    }

    /* -------------------- 4. Finance & invoicing ---------------------- */

    public function test_accountant_generates_an_invoice_with_line_items(): void
    {
        $accountant = $this->makeUser(UserRole::Accountant);
        $class = $this->makeClass();
        $student = $this->makeStudent($class);
        $fee = FeeStructure::create([
            'name' => 'Termly Tuition', 'type' => 'tuition', 'class_id' => null,
            'amount' => 250000, 'frequency' => 'termly', 'is_active' => true,
        ]);

        $this->actingAs($accountant)
            ->post('/invoices', [
                'student_id' => $student->id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'items' => [[
                    'fee_structure_id' => $fee->id, 'description' => 'Termly Tuition',
                    'quantity' => 1, 'amount' => 250000,
                ]],
            ])
            ->assertRedirect();

        $invoice = Invoice::first();

        $this->assertNotNull($invoice);
        $this->assertSame('unpaid', $invoice->status->value);
        $this->assertSame('250000.00', $invoice->total_amount);
        $this->assertDatabaseCount('invoice_items', 1);
    }

    public function test_invoice_create_form_suggests_applicable_fees(): void
    {
        $accountant = $this->makeUser(UserRole::Accountant);
        $class = $this->makeClass();
        $student = $this->makeStudent($class);

        FeeStructure::create([
            'name' => 'Universal Feeding', 'type' => 'feeding', 'class_id' => null,
            'amount' => 60000, 'frequency' => 'termly', 'is_active' => true,
        ]);
        FeeStructure::create([
            'name' => 'Class Transport', 'type' => 'transport', 'class_id' => $class->id,
            'amount' => 90000, 'frequency' => 'termly', 'is_active' => true,
        ]);

        $this->actingAs($accountant)
            ->get("/invoices/create?student_id={$student->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/InvoiceCreate')
                ->has('suggestedFees', 2)
            );
    }

    public function test_payment_updates_balance_status_and_creates_receipt(): void
    {
        $accountant = $this->makeUser(UserRole::Accountant);
        $student = $this->makeStudent($this->makeClass());

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001', 'student_id' => $student->id,
            'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 310000, 'total_amount' => 310000, 'amount_paid' => 0,
            'status' => 'unpaid', 'issued_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)
            ->post("/invoices/{$invoice->id}/payments", [
                'amount' => 150000, 'method' => 'mobile_money', 'reference' => 'TXN-1',
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertSame('partial', $invoice->status->value);
        $this->assertSame('150000.00', $invoice->amount_paid);
        $this->assertSame(160000.0, (float) $invoice->balance);

        $payment = Payment::first();
        $this->assertNotNull($payment);
        $this->assertStringStartsWith('RCT-', $payment->receipt_number);

        // Printable receipt
        $this->actingAs($accountant)->get("/payments/{$payment->id}/receipt")->assertOk();
    }

    public function test_payment_cannot_exceed_outstanding_balance(): void
    {
        $accountant = $this->makeUser(UserRole::Accountant);
        $student = $this->makeStudent($this->makeClass());

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-002', 'student_id' => $student->id,
            'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 100000, 'total_amount' => 100000, 'amount_paid' => 0,
            'status' => 'unpaid', 'issued_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)
            ->post("/invoices/{$invoice->id}/payments", ['amount' => 500000, 'method' => 'cash'])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invoice_with_payments_cannot_be_voided(): void
    {
        $accountant = $this->makeUser(UserRole::Accountant);
        $student = $this->makeStudent($this->makeClass());

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-003', 'student_id' => $student->id,
            'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 100000, 'total_amount' => 100000, 'amount_paid' => 100000,
            'status' => 'paid', 'issued_by' => $accountant->id,
        ]);

        Payment::create([
            'receipt_number' => 'RCT-X-1', 'invoice_id' => $invoice->id,
            'student_id' => $student->id, 'amount' => 100000, 'method' => 'cash',
            'paid_at' => now(), 'status' => 'completed',
        ]);

        $this->actingAs($accountant)->delete("/invoices/{$invoice->id}")->assertStatus(422);
    }

    public function test_parent_receives_fee_statement_but_teacher_cannot_open_finance(): void
    {
        $student = $this->makeStudent($this->makeClass());
        $parent = $this->makeUser(UserRole::Parent, ['phone' => '0754444444']);
        $parent->students()->attach($student->id, [
            'relationship_type' => 'father', 'is_primary_contact' => true,
        ]);

        $this->actingAs($parent)->get("/students/{$student->id}/fee-statement")->assertOk();

        $teacher = $this->makeUser(UserRole::Teacher, ['email' => 't2@godwin.ac.tz']);
        $this->actingAs($teacher)->get('/invoices')->assertRedirect('/dashboard');
    }

    /* --------------- 5. Announcements & direct messaging ------------- */

    public function test_senior_pastor_publishes_an_announcement_and_parents_are_notified(): void
    {
        $pastor = $this->makeUser(UserRole::SeniorPastor);
        $parent = $this->makeUser(UserRole::Parent, ['phone' => '0755555555']);

        $this->actingAs($pastor)
            ->post('/announcements', [
                'title' => 'Guidance on Good Character',
                'type' => 'announcement', 'audience' => 'parents',
                'excerpt' => 'A short word on discipline and love.',
                'body' => 'Let us guide our children with patience and prayer.',
                'is_published' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('announcements', [
            'title' => 'Guidance on Good Character', 'is_published' => true,
        ]);

        // The parent audience received a database notification
        $this->assertSame(1, $parent->notifications()->count());
    }

    public function test_teacher_cannot_publish_announcements(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);

        $this->actingAs($teacher)->get('/announcements')->assertRedirect('/dashboard');
    }

    public function test_announcement_can_be_unpublished(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        $announcement = Announcement::create([
            'author_id' => $admin->id, 'type' => 'news', 'audience' => 'everyone',
            'title' => 'School Closed', 'body' => 'Public holiday.',
            'slug' => 'school-closed-abc', 'is_published' => true, 'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post("/announcements/{$announcement->id}/toggle-publish")
            ->assertRedirect();

        $this->assertFalse($announcement->refresh()->is_published);
    }

    public function test_parent_can_message_their_childs_teacher_only(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $stranger = $this->makeUser(UserRole::Teacher, ['email' => 'stranger.t@godwin.ac.tz']);
        $class = $this->makeClass($teacher);
        $student = $this->makeStudent($class);
        $parent = $this->makeUser(UserRole::Parent, ['phone' => '0756666666']);

        $parent->students()->attach($student->id, [
            'relationship_type' => 'mother', 'is_primary_contact' => true,
        ]);

        // Allowed: the teacher of their own child
        $this->actingAs($parent)
            ->post('/messages', ['user_id' => $teacher->id, 'body' => 'How is Amina doing?'])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', ['sender_id' => $parent->id, 'body' => 'How is Amina doing?']);

        // Forbidden: an unrelated teacher
        $this->actingAs($parent)
            ->post('/messages', ['user_id' => $stranger->id, 'body' => 'Hello?'])
            ->assertForbidden();
    }

    public function test_teacher_replies_in_an_existing_thread(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher);
        $student = $this->makeStudent($class);
        $parent = $this->makeUser(UserRole::Parent, ['phone' => '0757777777']);

        $parent->students()->attach($student->id, ['relationship_type' => 'father', 'is_primary_contact' => true]);

        $this->actingAs($parent)->post('/messages', ['user_id' => $teacher->id, 'body' => 'Good morning']);
        $conversationId = $parent->conversations()->first()->id;

        $this->actingAs($teacher)
            ->post("/messages/{$conversationId}", ['body' => 'Good morning to you too!'])
            ->assertRedirect();

        $this->assertSame(2, Message::where('conversation_id', $conversationId)->count());

        // Thread is visible to the teacher
        $this->actingAs($teacher)
            ->get("/messages?conversation={$conversationId}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Comms/Messages')
                ->where('active.other.name', 'Parent User')
                ->has('active.messages', 2)
            );
    }

    public function test_outsiders_cannot_open_someone_elses_conversation(): void
    {
        $teacher = $this->makeUser(UserRole::Teacher);
        $class = $this->makeClass($teacher);
        $student = $this->makeStudent($class);
        $parent = $this->makeUser(UserRole::Parent, ['phone' => '0758888888']);
        $parent->students()->attach($student->id, ['relationship_type' => 'father', 'is_primary_contact' => true]);

        $this->actingAs($parent)->post('/messages', ['user_id' => $teacher->id, 'body' => 'Hi']);
        $conversationId = $parent->conversations()->first()->id;

        $accountant = $this->makeUser(UserRole::Accountant, ['email' => 'acc@godwin.ac.tz']);
        $this->actingAs($accountant)
            ->post("/messages/{$conversationId}", ['body' => 'Let me in'])
            ->assertForbidden();
    }

    public function test_fee_structures_can_be_managed_by_accountant(): void
    {
        $accountant = $this->makeUser(UserRole::Accountant);

        $this->actingAs($accountant)
            ->post('/fee-structures', [
                'name' => 'Daycare Monthly Fee', 'type' => 'daycare',
                'amount' => 150000, 'frequency' => 'monthly',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fee_structures', ['name' => 'Daycare Monthly Fee', 'amount' => 150000]);

        // `fees` is a paginator object, so assert against its `data` rows
        $this->actingAs($accountant)
            ->get('/fee-structures')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/FeeStructures')
                ->has('fees.data', 1)
            );
    }
}

<?php

/**
 * Schema & relationship smoke test for the God-Win SMS database layer.
 *
 * Run:  php database/verify_schema.php
 *
 * Builds a realistic data graph and traverses every core Eloquent
 * relationship in both directions. Everything runs inside one DB
 * transaction that is rolled back at the end.
 *
 * Exit code 0 = all checks passed; 1 = at least one failure.
 */

use App\Enums\ApplicationStatus;
use App\Enums\AttendanceStatus;
use App\Enums\Audience;
use App\Enums\ClassLevel;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\NoteType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RelationshipType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\BehaviorNote;
use App\Models\Conversation;
use App\Models\Expense;
use App\Models\FeeStructure;
use App\Models\Homework;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Payment;
use App\Models\SchoolApplication;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failures = [];

function check(string $label, bool $condition): void
{
    global $failures;

    if ($condition) {
        echo "  [PASS] {$label}\n";
    } else {
        $failures[] = $label;
        echo "  [FAIL] {$label}\n";
    }
}

echo "\n=== God-Win SMS - Schema & Relationship Verification ===\n";

try {
    DB::transaction(function () use (&$failures) {
        /* ------------------------------------------------------------------ */
        echo "\n[1] Core academic structure\n";
        /* ------------------------------------------------------------------ */

        $year = DB::table('academic_years')->insertGetId([
            'name' => '2026', 'start_date' => '2026-01-06', 'end_date' => '2026-12-04',
            'is_current' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $term = Term::create([
            'academic_year_id' => $year, 'name' => 'First Term',
            'start_date' => '2026-01-06', 'end_date' => '2026-04-03', 'is_current' => true,
        ]);

        $teacher = User::create([
            'name' => 'Mama Neema', 'email' => 'neema@godwin.ac.tz', 'phone' => '0784343454',
            'password' => 'secret-password', 'role' => UserRole::Teacher, 'status' => UserStatus::Active,
        ]);
        $parent = User::create([
            'name' => 'Bwana Juma', 'email' => 'juma@example.com', 'phone' => '0757348041',
            'password' => 'secret-password', 'role' => UserRole::Parent, 'status' => UserStatus::Active,
        ]);

        $kg1 = SchoolClass::create([
            'name' => 'KG1 Sunshine', 'code' => 'KG1-A', 'level' => ClassLevel::KG1,
            'teacher_id' => $teacher->id, 'capacity' => 25, 'is_active' => true,
        ]);
        $nursery = SchoolClass::create([
            'name' => 'Nursery B', 'code' => 'NUR-B', 'level' => ClassLevel::Nursery,
            'teacher_id' => $teacher->id, 'is_active' => true,
        ]);

        $phonics = Subject::create(['name' => 'Phonics', 'code' => 'PHON']);
        Subject::create(['name' => 'Numeracy', 'code' => 'NUM']);
        $kg1->subjects()->attach($phonics->id, ['teacher_id' => $teacher->id]);

        check('Term belongs to academic year', $term->academicYear?->name === '2026');
        check('Class belongs to teacher', $kg1->teacher?->id === $teacher->id);
        check('Teacher has classesTaught (hasMany)', $teacher->classesTaught()->count() === 2);
        check('Class <-> Subject pivot carries teacher', $kg1->subjects()->first()?->pivot?->teacher_id === $teacher->id);
        check('Subject -> classes (reverse pivot)', $phonics->classes()->count() === 1);

        /* ------------------------------------------------------------------ */
        echo "\n[2] Students & MULTI-CHILD parent portal\n";
        /* ------------------------------------------------------------------ */

        $amina = Student::create([
            'reg_no' => 'GWD-2026-0001', 'first_name' => 'Amina', 'last_name' => 'Juma',
            'gender' => Gender::Female, 'dob' => '2022-03-14', 'class_id' => $kg1->id,
            'status' => 'active', 'admission_date' => '2026-01-06',
            'medical_notes' => 'Peanut allergy',
        ]);
        $joshua = Student::create([
            'reg_no' => 'GWD-2026-0002', 'first_name' => 'Joshua', 'last_name' => 'Juma',
            'gender' => Gender::Male, 'dob' => '2023-08-02', 'class_id' => $nursery->id,
            'status' => 'active',
        ]);

        // One parent account linked to BOTH children (child switcher foundation)
        $parent->students()->attach($amina->id, [
            'relationship_type' => RelationshipType::Father->value, 'is_primary_contact' => 1,
        ]);
        $parent->students()->attach($joshua->id, [
            'relationship_type' => RelationshipType::Father->value, 'is_primary_contact' => 1,
        ]);

        check('Student full_name accessor', $amina->full_name === 'Amina Juma');
        check('Student -> class (belongsTo)', $amina->class?->code === 'KG1-A');
        check('Parent -> children (belongsToMany)', $parent->children()->count() === 2);
        check('Child -> parents (reverse direction)', $amina->parents()->where('users.id', $parent->id)->exists());
        check('Pivot carries relationship_type', $amina->parents()->first()?->pivot?->relationship_type === 'father');
        check('Primary parent resolution', $amina->primaryParent()?->id === $parent->id);
        check('Class roster (class -> students)', $kg1->students()->count() === 1);
        check('Duplicate parent link rejected by unique index', (bool) rescue(function () use ($parent, $amina) {
            $parent->students()->attach($amina->id, ['relationship_type' => 'father']);

            return false;
        }, true), false);

        /* ------------------------------------------------------------------ */
        echo "\n[3] Attendance\n";
        /* ------------------------------------------------------------------ */

        Attendance::create([
            'student_id' => $amina->id, 'date' => '2026-09-21', 'status' => AttendanceStatus::Present,
            'marked_by' => $teacher->id, 'check_in_time' => '07:45:00',
        ]);
        Attendance::create([
            'student_id' => $amina->id, 'date' => '2026-09-22', 'status' => AttendanceStatus::Late,
            'marked_by' => $teacher->id,
        ]);
        Attendance::create([
            'student_id' => $amina->id, 'date' => '2026-09-23', 'status' => AttendanceStatus::Absent,
            'marked_by' => $teacher->id,
        ]);

        check('Attendance -> student', $amina->attendance()->first()?->student?->id === $amina->id);
        check('Attendance -> markedBy teacher', $amina->attendance()->first()?->markedBy?->id === $teacher->id);
        check('attendanceRate() = 66.7% (present+late / counted)', $amina->attendanceRate('2026-09-21', '2026-09-23') === 66.7);
        check('Unique (student, date) enforced', (bool) rescue(function () use ($amina) {
            Attendance::create(['student_id' => $amina->id, 'date' => '2026-09-21', 'status' => 'absent']);

            return false;
        }, true), false);

        /* ------------------------------------------------------------------ */
        echo "\n[4] Assessments, homework & character development\n";
        /* ------------------------------------------------------------------ */

        $assessment = Assessment::create([
            'student_id' => $amina->id, 'class_id' => $kg1->id, 'subject_id' => $phonics->id,
            'term_id' => $term->id, 'title' => 'Week 4 Phonics Test', 'type' => 'test',
            'score' => 18, 'max_score' => 20, 'recorded_by' => $teacher->id,
            'obtained_at' => '2026-09-22', 'remarks' => 'Excellent progress',
        ]);
        Homework::create([
            'class_id' => $kg1->id, 'subject_id' => $phonics->id, 'teacher_id' => $teacher->id,
            'type' => 'homework', 'title' => 'Letter sounds A-F', 'description' => 'Practise at home.',
            'assigned_on' => '2026-09-22', 'due_on' => '2026-09-25', 'status' => 'published',
        ]);
        BehaviorNote::create([
            'student_id' => $amina->id, 'noted_by' => $teacher->id, 'type' => NoteType::Spiritual,
            'title' => 'Kindness to a friend', 'note' => 'Shared snacks with a classmate.',
            'is_positive' => true, 'occurred_on' => '2026-09-22',
        ]);

        check('Assessment percentage accessor (90%)', $assessment->percentage === 90.0);
        check('Assessment -> class/subject/term/recorder',
            $assessment->schoolClass?->id === $kg1->id
            && $assessment->subject?->id === $phonics->id
            && $assessment->term?->id === $term->id
            && $assessment->recordedBy?->id === $teacher->id);
        check('Teacher homeworksPosted', $teacher->homeworksPosted()->count() === 1);
        check('BehaviorNote -> student & teacher',
            BehaviorNote::first()?->student?->id === $amina->id
            && BehaviorNote::first()?->notedBy?->id === $teacher->id);

        /* ------------------------------------------------------------------ */
        echo "\n[5] Finance (fees, invoices, payments, expenses)\n";
        /* ------------------------------------------------------------------ */

        $tuition = FeeStructure::create([
            'name' => 'KG1 Termly Tuition', 'type' => 'tuition', 'class_id' => $kg1->id,
            'academic_year_id' => $year, 'amount' => 250000, 'frequency' => 'termly', 'is_active' => true,
        ]);
        $feeding = FeeStructure::create([
            'name' => 'Universal Feeding', 'type' => 'feeding', 'class_id' => null,
            'amount' => 40000, 'frequency' => 'termly', 'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-000001', 'student_id' => $amina->id,
            'academic_year_id' => $year, 'term_id' => $term->id,
            'issue_date' => '2026-09-01', 'due_date' => '2026-09-15',
            'subtotal' => 290000, 'total_amount' => 290000, 'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid, 'issued_by' => $teacher->id,
        ]);
        $invoice->items()->create([
            'fee_structure_id' => $tuition->id, 'description' => 'KG1 Termly Tuition',
            'quantity' => 1, 'unit_amount' => 250000, 'amount' => 250000,
        ]);
        $invoice->items()->create([
            'fee_structure_id' => $feeding->id, 'description' => 'Universal Feeding',
            'quantity' => 1, 'unit_amount' => 40000, 'amount' => 40000,
        ]);

        $payment = Payment::create([
            'receipt_number' => 'RCT-2026-000001', 'invoice_id' => $invoice->id,
            'student_id' => $amina->id, 'amount' => 150000, 'method' => PaymentMethod::MobileMoney,
            'reference' => 'TXN-88213', 'paid_at' => now(), 'status' => PaymentStatus::Completed,
            'received_by' => $teacher->id,
        ]);
        $invoice->update(['amount_paid' => 150000, 'status' => InvoiceStatus::Partial]);

        Expense::create([
            'title' => 'Cooking gas', 'category' => 'food', 'amount' => 120000,
            'incurred_on' => '2026-09-05', 'recorded_by' => $teacher->id,
        ]);

        check('Invoice has 2 line items summing to total',
            $invoice->items()->count() === 2
            && (float) $invoice->items()->sum('amount') === (float) $invoice->total_amount);
        check('Invoice balance accessor (140,000)', (float) $invoice->balance === 140000.0);
        check('Invoice progress accessor (51.7%)', $invoice->progress === 51.7);
        check('Payment -> invoice + student + receiver',
            $payment->invoice?->id === $invoice->id
            && $payment->student?->id === $amina->id
            && $payment->receivedBy?->id === $teacher->id);
        check('Invoice -> payments (hasMany)', $invoice->payments()->count() === 1);
        check('Universal fee has class_id NULL', $feeding->class_id === null);
        check('Teacher paymentsReceived / expensesRecorded',
            $teacher->paymentsReceived()->count() === 1
            && $teacher->expensesRecorded()->count() === 1);
        check('FeeStructure -> class & academic year',
            $tuition->schoolClass?->id === $kg1->id
            && $tuition->academicYear?->name === '2026');

        /* ------------------------------------------------------------------ */
        echo "\n[6] Messaging, announcements, events, applications, audit\n";
        /* ------------------------------------------------------------------ */

        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$teacher->id, $parent->id]);
        $conversation->messages()->create(['sender_id' => $teacher->id, 'body' => 'Amina did well today!']);
        $conversation->update(['last_message_at' => now()]);

        check('Conversation participants (2 users)', $conversation->participants()->count() === 2);
        check('Conversation unread count for parent', $conversation->unreadCountFor($parent->id) === 1);
        check('User conversations (reverse)', $parent->conversations()->count() === 1);
        check('Message -> conversation + sender',
            Message::first()?->conversation?->id === $conversation->id
            && Message::first()?->sender?->id === $teacher->id);

        Announcement::create([
            'author_id' => $teacher->id, 'type' => 'admission', 'audience' => Audience::Everyone,
            'title' => 'Admission 2026 Open', 'body' => 'We are accepting applications.',
            'is_published' => true, 'published_at' => now(),
        ]);
        SchoolEvent::create([
            'created_by' => $teacher->id, 'title' => 'Sports Day', 'start_date' => '2026-10-10',
            'color' => '#0288D1', 'audience' => Audience::Parents, 'is_published' => true,
        ]);

        $application = SchoolApplication::create([
            'reference_no' => 'APP-2026-0001', 'parent_name' => 'Mama Grace',
            'parent_email' => 'grace@example.com', 'parent_phone' => '0753640374',
            'relationship' => 'mother', 'child_first_name' => 'Ester', 'child_last_name' => 'Moshi',
            'gender' => Gender::Female, 'dob' => '2023-01-11', 'class_id' => $nursery->id,
            'status' => ApplicationStatus::Submitted, 'submitted_at' => now(),
        ]);

        $log = AuditLog::record($teacher, 'student.updated', 'Updated medical notes', $amina, [
            'before' => ['medical_notes' => ''],
            'after' => ['medical_notes' => 'Peanut allergy'],
        ]);

        check('Announcement auto-slug', Announcement::first()?->slug !== null);
        check('Announcement published scope', Announcement::published()->count() === 1);
        check('Event -> creator + audience cast',
            SchoolEvent::first()?->creator?->id === $teacher->id
            && SchoolEvent::first()?->audience === Audience::Parents);
        check('Application -> class + status cast',
            $application->schoolClass?->id === $nursery->id
            && $application->status === ApplicationStatus::Submitted);
        check('AuditLog -> user + auditable morph',
            $log->user?->id === $teacher->id
            && $log->auditable?->id === $amina->id);
        check('AuditLog::record captured IP/UA', $log->ip_address !== null);

        /* ------------------------------------------------------------------ */
        echo "\n[7] RBAC & settings helpers\n";
        /* ------------------------------------------------------------------ */

        check('User role enum cast', $teacher->role === UserRole::Teacher);
        check('isStaff(): true for teacher, false for parent',
            $teacher->isStaff() === true && $parent->isStaff() === false);
        check('hasAnyRole() check', $teacher->hasAnyRole([UserRole::Teacher, UserRole::Admin]) === true);
        check('scopeStaff() excludes parents', User::staff()->count() === 1);
        check('scopeParents() returns only parents', User::parents()->count() === 1);

        Setting::put('admissions.open', 'true', 'admissions');
        check('Setting::get returns boolean', Setting::get('admissions.open') === true);
        check('Setting::get default fallback', Setting::get('missing.key', 'fallback') === 'fallback');

        /* Roll the entire test graph back so the script stays repeatable. */
        throw new RuntimeException('__ROLLBACK__');
    });
} catch (RuntimeException $e) {
    // __ROLLBACK__ is our intentional, expected transaction abort.
    if ($e->getMessage() !== '__ROLLBACK__') {
        throw $e;
    }
}

echo "\n";
if (empty($failures)) {
    echo "=== ALL CHECKS PASSED ===\n\n";
    exit(0);
}

echo '=== '.count($failures)." CHECK(S) FAILED ===\n";
foreach ($failures as $failure) {
    echo "  - {$failure}\n";
}
echo "\n";
exit(1);

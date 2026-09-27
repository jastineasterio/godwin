<?php

// Quick data-integrity snapshot:  php database/verify_data.php
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\BehaviorNote;
use App\Models\Conversation;
use App\Models\Expense;
use App\Models\FeeStructure;
use App\Models\Homework;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Message;
use App\Models\Payment;
use App\Models\SchoolApplication;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$checks = [
    'academic years' => AcademicYear::count(),
    'users' => User::count(),
    'classes' => SchoolClass::count(),
    'subjects' => Subject::count(),
    'students' => Student::count(),
    'attendance rows' => Attendance::count(),
    'assessments' => Assessment::count(),
    'homework' => Homework::count(),
    'timetable slots' => Timetable::count(),
    'character notes' => BehaviorNote::count(),
    'fee structures' => FeeStructure::count(),
    'invoices' => Invoice::count(),
    'invoice items' => InvoiceItem::count(),
    'payments' => Payment::count(),
    'expenses' => Expense::count(),
    'announcements' => Announcement::count(),
    'applications' => SchoolApplication::count(),
    'conversations' => Conversation::count(),
    'messages' => Message::count(),
];

echo "\n=== God-Win SMS · data integrity snapshot ===\n\n";
foreach ($checks as $label => $count) {
    printf("  %-20s %6d\n", $label, $count);
}

// Referential sanity: parents linked, payments matched to invoices
$linkedParents = DB::table('parent_student')->count();
$orphanPayments = Payment::whereNull('invoice_id')->count();
$unpaidInvoices = Invoice::whereIn('status', ['unpaid', 'partial'])->count();

echo "\n  linked parent-student rows : {$linkedParents}\n";
echo "  payments without invoice  : {$orphanPayments}\n";
echo "  invoices still outstanding: {$unpaidInvoices}\n\n";
echo "Done.\n";

<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\AssessmentType;
use App\Enums\AttendanceStatus;
use App\Enums\ClassLevel;
use App\Enums\Gender;
use App\Enums\NoteType;
use App\Enums\StudentStatus;
use App\Enums\TimetableDay;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\BehaviorNote;
use App\Models\Expense;
use App\Models\FeeStructure;
use App\Models\Homework;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\SchoolApplication;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Rich demo dataset so every dashboard chart renders live values.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();

        // Every demo account shares the password "password". Hashing once and
        // reusing the result keeps seeding fast (bcrypt is intentionally slow)
        // and the `hashed` cast will not re-hash an existing hash.
        $demoPassword = Hash::make('password');

        // ---------- 1. Users (one account per RBAC role) -------------------
        $admin = User::create([
            'name' => 'Administrator', 'email' => 'admin@godwin.ac.tz', 'phone' => '0784343454',
            'password' => $demoPassword, 'role' => UserRole::Admin, 'status' => UserStatus::Active,
            'last_login_at' => now(),
        ]);
        $pastor = User::create([
            'name' => 'Pastor Daniel Mhame', 'email' => 'pastor@godwin.ac.tz', 'phone' => '0757348041',
            'password' => $demoPassword, 'role' => UserRole::SeniorPastor, 'status' => UserStatus::Active,
            'last_login_at' => $today->copy()->subDays(1),
        ]);
        $head = User::create([
            'name' => 'Mrs. Grace Mushi', 'email' => 'head@godwin.ac.tz', 'phone' => '0753640374',
            'password' => $demoPassword, 'role' => UserRole::HeadOfSchool, 'status' => UserStatus::Active,
            'last_login_at' => now(),
        ]);
        $teachers = collect(['Neema', 'Peter', 'Ruth'])->map(fn ($name, $i) => User::create([
            'name' => "Mama {$name}", 'email' => "teacher{$name}@godwin.ac.tz", 'phone' => '077000000'.$i,
            'password' => $demoPassword, 'role' => UserRole::Teacher, 'status' => UserStatus::Active,
        ]));
        $accountant = User::create([
            'name' => 'Joseph Kambi', 'email' => 'accounts@godwin.ac.tz', 'phone' => '0765550001',
            'password' => $demoPassword, 'role' => UserRole::Accountant, 'status' => UserStatus::Active,
            'last_login_at' => $today->copy()->subHours(3),
        ]);
        $parents = collect(['Juma', 'Moshi', 'Kileo'])->map(fn ($name, $i) => User::create([
            'name' => "Bwana {$name}", 'email' => "parent{$name}@example.com", 'phone' => '075100000'.$i,
            'password' => $demoPassword, 'role' => UserRole::Parent, 'status' => UserStatus::Active,
        ]));

        // ---------- 2. Academic structure ----------------------------------
        $year = AcademicYear::create([
            'name' => (string) $today->year, 'start_date' => $today->copy()->startOfYear(),
            'end_date' => $today->copy()->endOfYear(), 'is_current' => true, 'status' => 'active',
        ]);
        $terms = collect(['First Term', 'Second Term', 'Third Term'])->map(fn ($name, $i) => Term::create([
            'academic_year_id' => $year->id, 'name' => $name,
            'start_date' => $today->copy()->startOfYear()->addMonths($i * 4),
            'end_date' => $today->copy()->startOfYear()->addMonths(($i * 4) + 4)->subDay(),
            'is_current' => $i === 1,
        ]));

        // ---------- 3. Subjects & classes ----------------------------------
        $subjects = collect([
            ['Phonics', 'PHON'], ['Numeracy', 'NUM'], ['Bible Stories', 'BIBL'],
            ['Creative Arts', 'ARTS'], ['Fine Motor Skills', 'FMS'],
        ])->map(fn ($row) => Subject::create(['name' => $row[0], 'code' => $row[1]]));

        $classes = collect([
            ['Daycare Little Stars', 'DC-A', ClassLevel::Daycare, 0],
            ['Nursery B', 'NUR-B', ClassLevel::Nursery, 1],
            ['KG1 Sunshine', 'KG1-A', ClassLevel::KG1, 0],
            ['KG2 Stars', 'KG2-A', ClassLevel::KG2, 2],
        ])->map(fn ($row) => SchoolClass::create([
            'name' => $row[0], 'code' => $row[1], 'level' => $row[2],
            'teacher_id' => $teachers[$row[3]]->id, 'capacity' => 25, 'is_active' => true,
        ]));

        // Subject allocation per class (pivot carries the allocated teacher)
        foreach ($classes as $class) {
            foreach ($subjects->take(3) as $subject) {
                $class->subjects()->attach($subject->id, ['teacher_id' => $class->teacher_id]);
            }
        }

        // ---------- 4. Students (records only â€” never users) ---------------
        $firstNames = ['Amina', 'Joshua', 'Ester', 'Daniel', 'Mercy', 'Yusuf', 'Sarah', 'David',
            'Ruth', 'Samuel', 'Grace', 'Emmanuel', 'Faith', 'John', 'Mary', 'Peter'];
        $lastNames = ['Juma', 'Moshi', 'Kileo', 'Mwakalinga', 'Sanga', 'Kagera'];

        $students = collect();
        $counter = 0;

        foreach ($classes as $classIndex => $class) {
            for ($i = 0; $i < 8; $i++) {
                $counter++;
                $first = $firstNames[($counter - 1) % count($firstNames)];
                $last = $lastNames[($counter - 1) % count($lastNames)];

                $students->push(Student::create([
                    'reg_no' => sprintf('GWD-%d-%04d', $today->year, $counter),
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $counter % 2 === 0 ? Gender::Male : Gender::Female,
                    'dob' => $today->copy()->subYears(random_int(2, 5))->subMonths(random_int(0, 11)),
                    'class_id' => $class->id,
                    'admission_date' => $today->copy()->subMonths(random_int(0, 8))->subDays(random_int(0, 25)),
                    'status' => StudentStatus::Active,
                    'medical_notes' => $counter % 7 === 0 ? 'Peanut allergy â€” monitor meals' : null,
                ]));
            }
        }

        // Multi-child parent: Bwana Juma has children in KG2 and Nursery
        $parents[0]->students()->attach($students[0]->id, [
            'relationship_type' => 'father', 'is_primary_contact' => true,
        ]);
        $parents[0]->students()->attach($students[10]->id, [
            'relationship_type' => 'father', 'is_primary_contact' => true,
        ]);
        $parents[1]->students()->attach($students[3]->id, ['relationship_type' => 'mother', 'is_primary_contact' => true]);
        $parents[2]->students()->attach($students[18]->id, ['relationship_type' => 'guardian', 'is_primary_contact' => true]);

        // ---------- 5. Attendance (last 45 school days) --------------------
        // Bulk-inserted in chunks: ~1,100 rows in a handful of statements
        // instead of ~1,100 individual INSERTs.
        $now = now();
        $attendanceRows = [];

        foreach ($students as $student) {
            $teacherId = SchoolClass::find($student->class_id)?->teacher_id;

            for ($day = 45; $day >= 0; $day--) {
                $date = $today->copy()->subDays($day);

                // Skip Sundays & Saturdays
                if ($date->isSunday() || $date->isSaturday()) {
                    continue;
                }

                $roll = random_int(1, 100);
                $status = match (true) {
                    $roll <= 84 => AttendanceStatus::Present,
                    $roll <= 93 => AttendanceStatus::Late,
                    $roll <= 98 => AttendanceStatus::Absent,
                    default => AttendanceStatus::Excused,
                };

                $attendanceRows[] = [
                    'student_id' => $student->id,
                    'date' => $date->toDateString(),
                    'status' => $status->value,
                    'marked_by' => $teacherId,
                    'check_in_time' => $status === AttendanceStatus::Present ? '07:45:00' : null,
                    'remarks' => $status === AttendanceStatus::Excused ? 'Medical appointment' : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($attendanceRows, 500) as $chunk) {
            DB::table('attendance')->insert($chunk);
        }

        // ---------- 6. Assessments & homework ------------------------------
        $assessmentRows = [];

        foreach ($students as $student) {
            $teacherId = SchoolClass::find($student->class_id)?->teacher_id;

            foreach ($subjects->take(3) as $index => $subject) {
                $assessmentRows[] = [
                    'student_id' => $student->id,
                    'class_id' => $student->class_id,
                    'subject_id' => $subject->id,
                    'term_id' => $terms[1]->id,
                    'title' => "{$subject->name} Continuous Assessment",
                    'type' => $index === 0 ? AssessmentType::Test->value : AssessmentType::Milestone->value,
                    'score' => random_int(11, 20),
                    'max_score' => 20,
                    'remarks' => 'Progress noted during class.',
                    'recorded_by' => $teacherId,
                    'obtained_at' => $today->copy()->subDays(random_int(3, 40))->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($assessmentRows, 100) as $chunk) {
            DB::table('assessments')->insert($chunk);
        }

        foreach ($classes as $class) {
            Homework::create([
                'class_id' => $class->id,
                'subject_id' => $subjects->first()->id,
                'teacher_id' => $class->teacher_id,
                'title' => 'Letter sounds A â€“ F',
                'description' => 'Practise the letter sounds at home with your child.',
                'assigned_on' => $today->copy()->subDays(4),
                'due_on' => $today->copy()->addDays(3),
                'status' => 'published',
            ]);
        }

        // ---------- 7. Character & spiritual notes ------------------------
        $noteTypes = [NoteType::Spiritual, NoteType::Character, NoteType::Behavior, NoteType::Achievement];
        $noteTexts = [
            'Recited a Bible verse confidently in class.',
            'Shared toys with a classmate without being asked.',
            'Led the morning prayer with great confidence.',
            'Completed all colouring pages neatly.',
            'Helped tidy the classroom after lunch.',
            'Showed excellent respect to the teacher.',
        ];

        foreach ($students->take(22) as $index => $student) {
            BehaviorNote::create([
                'student_id' => $student->id,
                'noted_by' => SchoolClass::find($student->class_id)?->teacher_id,
                'type' => $noteTypes[$index % 4],
                'title' => 'Character observation',
                'note' => $noteTexts[$index % count($noteTexts)],
                'is_positive' => true,
                'occurred_on' => $today->copy()->subDays(random_int(0, 25)),
            ]);
        }

        // ---------- 8. Timetable (this week) --------------------------------
        $days = [TimetableDay::Monday, TimetableDay::Tuesday, TimetableDay::Wednesday, TimetableDay::Thursday, TimetableDay::Friday];
        $slots = [['08:00:00', '09:00:00'], ['09:15:00', '10:15:00'], ['10:30:00', '11:30:00']];

        foreach ($classes as $class) {
            foreach ($days as $day) {
                foreach ($slots as $slotIndex => $slot) {
                    Timetable::create([
                        'class_id' => $class->id,
                        'subject_id' => $subjects[$slotIndex % 3]->id,
                        'teacher_id' => $class->teacher_id,
                        'day' => $day,
                        'start_time' => $slot[0],
                        'end_time' => $slot[1],
                        'room' => $class->code.' Room',
                    ]);
                }
            }
        }

        // ---------- 9. Finance ---------------------------------------------
        $tuition = FeeStructure::create([
            'name' => 'Termly Tuition', 'type' => 'tuition', 'class_id' => null,
            'academic_year_id' => $year->id, 'amount' => 250000, 'frequency' => 'termly', 'is_active' => true,
        ]);
        $feeding = FeeStructure::create([
            'name' => 'Feeding & Meals', 'type' => 'feeding', 'class_id' => null,
            'academic_year_id' => $year->id, 'amount' => 60000, 'frequency' => 'termly', 'is_active' => true,
        ]);
        FeeStructure::create([
            'name' => 'School Transport', 'type' => 'transport', 'class_id' => null,
            'academic_year_id' => $year->id, 'amount' => 90000, 'frequency' => 'termly', 'is_active' => true,
        ]);

        $methods = ['cash', 'mobile_money', 'bank_transfer', 'cheque'];
        $paymentIndex = 0;

        foreach ($students as $student) {
            $paymentIndex++;
            $total = 310000;

            // Vary the payment state so the doughnut + progress bars are realistic
            $paidRatio = match ($student->id % 4) {
                0 => 1.0,   // fully paid
                1 => 0.6,   // partially paid
                2 => 0.0,   // fully outstanding
                default => 1.0,
            };

            $paid = (int) round($total * $paidRatio);

            $invoice = Invoice::create([
                'invoice_number' => sprintf('INV-%d-%06d', $today->year, $paymentIndex),
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'term_id' => $terms[1]->id,
                'issue_date' => $today->copy()->subDays(random_int(40, 70)),
                'due_date' => $today->copy()->subDays(random_int(-10, 20)),
                'subtotal' => $total,
                'total_amount' => $total,
                'amount_paid' => $paid,
                'status' => $paid === 0 ? 'unpaid' : ($paid >= $total ? 'paid' : 'partial'),
                'issued_by' => $accountant->id,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id, 'fee_structure_id' => $tuition->id,
                'description' => 'Termly Tuition', 'quantity' => 1,
                'unit_amount' => 250000, 'amount' => 250000,
            ]);
            InvoiceItem::create([
                'invoice_id' => $invoice->id, 'fee_structure_id' => $feeding->id,
                'description' => 'Feeding & Meals', 'quantity' => 1,
                'unit_amount' => 60000, 'amount' => 60000,
            ]);

            if ($paid > 0) {
                Payment::create([
                    'receipt_number' => sprintf('RCT-%d-%06d', $today->year, $paymentIndex),
                    'invoice_id' => $invoice->id,
                    'student_id' => $student->id,
                    'amount' => $paid,
                    'method' => $methods[$student->id % 4],
                    'reference' => 'TXN-'.random_int(10000, 99999),
                    'paid_at' => $today->copy()->subDays(random_int(1, 55)),
                    'status' => 'completed',
                    'received_by' => $accountant->id,
                ]);
            }
        }

        // Expenses spread over the last 6 months (income vs expense chart)
        $expenseTitles = [
            ['Teacher salaries', 'salaries'], ['Water & electricity', 'utilities'],
            ['Classroom maintenance', 'maintenance'], ['Learning materials', 'supplies'],
            ['Kitchen & feeding', 'food'], ['School bus fuel', 'transport'],
        ];

        for ($month = 5; $month >= 0; $month--) {
            foreach (array_slice($expenseTitles, 0, 4) as $expense) {
                Expense::create([
                    'title' => $expense[0],
                    'category' => $expense[1],
                    'amount' => random_int(80, 900) * 1000,
                    'incurred_on' => $today->copy()->subMonths($month)->day(random_int(2, 26)),
                    'recorded_by' => $accountant->id,
                ]);
            }
        }

        // ---------- 10. CMS: banners, news, events -------------------------
        Banner::create([
            'title' => 'Admission 2026 Open',
            'subtitle' => 'Nafasi za Masomo Mwaka 2026 â€” apply online today',
            'image_path' => 'banners/admission-2026.svg',
            'cta_label' => 'Apply Now', 'link' => '/apply',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $posts = [
            ['Welcome to a New School Year', 'news', 'We warmly welcome every family back for the 2026 academic year.'],
            ['Admission 2026 Now Open', 'admission', 'Applications for Daycare, Nursery, KG1 and KG2 are now open.'],
            ['Parents Meeting â€” 28th', 'event', 'Monthly parents meeting to review children progress and welfare.'],
        ];

        foreach ($posts as $index => [$title, $type, $excerpt]) {
            Announcement::create([
                'author_id' => $head->id,
                'type' => $type,
                'audience' => 'everyone',
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => $excerpt.' Full details are available from the school office.',
                'is_published' => true,
                'is_pinned' => $index === 0,
                'published_at' => $today->copy()->subDays($index * 4),
            ]);
        }

        $events = [
            ['Sports Day', 'Children compete in fun team games.', 5, '#D81B60'],
            ['Parent-Teacher Meeting', 'Termly progress review with class teachers.', 12, '#0288D1'],
            ['Church Thanksgiving Day', 'Family worship and thanksgiving.', 19, '#FBC02D'],
        ];

        foreach ($events as [$title, $description, $inDays, $color]) {
            SchoolEvent::create([
                'created_by' => $head->id,
                'title' => $title,
                'description' => $description,
                'start_date' => $today->copy()->addDays($inDays),
                'start_time' => '08:00:00',
                'location' => 'School compound, Medeli',
                'color' => $color,
                'audience' => 'everyone',
                'is_published' => true,
            ]);
        }

        // ---------- 11. Online applications (admissions pipeline) ----------
        SchoolApplication::create([
            'reference_no' => sprintf('APP-%d-0001', $today->year),
            'parent_name' => 'Mama Salma Said', 'parent_email' => 'salma@example.com',
            'parent_phone' => '0757333444', 'relationship' => 'mother',
            'child_first_name' => 'Zainabu', 'child_last_name' => 'Said',
            'gender' => Gender::Female, 'dob' => $today->copy()->subYears(4),
            'class_id' => $classes[2]->id,
            'message' => 'Would like to join KG1 in January.',
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now()->subDays(2),
        ]);

        // ---------- 12. Audit trail -----------------------------------------
        $auditActions = [
            ['login', 'Administrator signed in'],
            ['student.updated', 'Updated student medical notes'],
            ['invoice.created', 'Generated invoice for termly fees'],
            ['attendance.marked', 'Marked attendance for KG1 Sunshine'],
        ];

        foreach ($auditActions as $index => [$action, $description]) {
            AuditLog::record($admin, $action, $description, $students[$index], null);
        }
    }
}

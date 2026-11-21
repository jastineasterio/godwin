<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\AssessmentType;
use App\Enums\AttendanceStatus;
use App\Enums\ClassLevel;
use App\Enums\ExpenseCategory;
use App\Enums\FeeFrequency;
use App\Enums\FeeType;
use App\Enums\Gender;
use App\Enums\HomeworkType;
use App\Enums\InvoiceStatus;
use App\Enums\NoteType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RelationshipType;
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
use App\Models\ContactMessage;
use App\Models\Expense;
use App\Models\FeeStructure;
use App\Models\Homework;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\SchoolApplication;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GodwinDemoDataSeeder extends Seeder
{
    public const DEMO_PASSWORD = '12345678';
    public const DEMO_PHONE = '0655362282';
    public const DEMO_MARKER = 'TEST DATA — God-Win demo seed';

    public function run(): void
    {
        $today = Carbon::today('Africa/Dar_es_Salaam');
        $password = Hash::make(self::DEMO_PASSWORD);
        $phone = self::DEMO_PHONE;

        DB::transaction(function () use ($today, $password, $phone) {
            $users = $this->seedUsers($password, $phone);
            $academic = $this->seedAcademicStructure($today);
            $subjects = $this->seedSubjects();
            $classes = $this->seedClasses($today, $users, $academic['year']);
            $this->seedClassSubjects($classes, $subjects, $users);
            $students = $this->seedStudents($today, $classes);
            $this->seedParentStudentLinks($students, $users);
            $this->seedExaminations($today, $academic, $users);
            $this->seedAssessments($today, $students, $classes, $subjects, $academic, $users);
            $this->seedHomeworks($today, $classes, $subjects, $users);
            $this->seedTimetables($today, $classes, $subjects, $users);
            $this->seedAttendance($today, $students, $classes, $users);
            $this->seedBehaviorNotes($today, $students, $users);
            $this->seedFeeStructures($today, $classes, $academic['year']);
            $this->seedInvoicesAndPayments($today, $students, $academic, $users, $phone);
            $this->seedExpenses($today, $users);
            $this->seedAnnouncements($today, $users);
            $this->seedEvents($today, $users);
            $this->seedSmsTemplates($today, $users);
            $this->seedSmsCampaigns($today, $users);
            $this->seedContactMessages($today);
            $this->seedApplications($today, $classes, $users);
            $this->seedAssets($today, $users);
            $this->seedSettings($today);
            $this->seedBanners($today);
            $this->seedNotifications($today, $users, $students);
            $this->seedConversationsAndMessages($today, $users);
            $this->seedAuditLogs($today, $users, $students);
        });
    }

    protected function seedUsers(string $password, string $phone): array
    {
        $accounts = [
            ['name' => 'Administrator', 'email' => 'admin@godwin.ac.tz', 'role' => UserRole::Admin, 'phone' => $phone],
            ['name' => 'Pastor Daniel Mhame', 'email' => 'pastor@godwin.ac.tz', 'role' => UserRole::SeniorPastor, 'phone' => $phone],
            ['name' => 'Mrs. Grace Mushi', 'email' => 'head@godwin.ac.tz', 'role' => UserRole::HeadOfSchool, 'phone' => $phone],
            ['name' => 'Mama Neema', 'email' => 'teacherNeema@godwin.ac.tz', 'role' => UserRole::Teacher, 'phone' => $phone],
            ['name' => 'Mama Peter', 'email' => 'teacherPeter@godwin.ac.tz', 'role' => UserRole::Teacher, 'phone' => $phone],
            ['name' => 'Mama Ruth', 'email' => 'teacherRuth@godwin.ac.tz', 'role' => UserRole::Teacher, 'phone' => $phone],
            ['name' => 'Teacher Grace', 'email' => 'teacherGrace@godwin.ac.tz', 'role' => UserRole::Teacher, 'phone' => $phone],
            ['name' => 'Joseph Kambi', 'email' => 'accounts@godwin.ac.tz', 'role' => UserRole::Accountant, 'phone' => $phone],
            ['name' => 'Bwana Juma', 'email' => 'parentJuma@example.com', 'role' => UserRole::Parent, 'phone' => $phone],
            ['name' => 'Bwana Moshi', 'email' => 'parentMoshi@example.com', 'role' => UserRole::Parent, 'phone' => $phone],
            ['name' => 'Bwana Kileo', 'email' => 'parentKileo@example.com', 'role' => UserRole::Parent, 'phone' => $phone],
            ['name' => 'Asi Mwanga', 'email' => 'parentAsi@example.com', 'role' => UserRole::Parent, 'phone' => $phone],
            ['name' => 'Rehema Saidi', 'email' => 'parentRehema@example.com', 'role' => UserRole::Parent, 'phone' => $phone],
        ];

        $users = [];
        foreach ($accounts as $data) {
            $users[$data['email']] = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => $password,
                    'role' => $data['role'],
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'avatar_path' => null,
                    'last_login_at' => null,
                ]
            );
        }

        return $users;
    }

    protected function seedAcademicStructure(Carbon $today): array
    {
        $year = AcademicYear::updateOrCreate(
            ['name' => '2026'],
            [
                'start_date' => $today->copy()->startOfYear(),
                'end_date' => $today->copy()->endOfYear(),
                'is_current' => true,
                'status' => 'active',
            ]
        );

        $terms = [];
        $termNames = ['First Term', 'Second Term', 'Third Term'];
        foreach ($termNames as $index => $name) {
            $terms[$name] = Term::updateOrCreate(
                ['academic_year_id' => $year->id, 'name' => $name],
                [
                    'start_date' => $today->copy()->startOfYear()->addMonths($index * 4),
                    'end_date' => $today->copy()->startOfYear()->addMonths($index * 4 + 4)->subDay(),
                    'is_current' => $index === 1,
                ]
            );
        }

        return ['year' => $year, 'terms' => $terms];
    }

    protected function seedSubjects(): array
    {
        $subjects = [
            ['Phonics', 'PHON'],
            ['Numeracy', 'NUM'],
            ['Bible Stories', 'BIBL'],
            ['Creative Arts', 'ARTS'],
            ['Fine Motor Skills', 'FMS'],
            ['Swahili', 'KISW'],
            ['Science', 'SCIE'],
        ];

        $result = [];
        foreach ($subjects as [$name, $code]) {
            $result[$code] = Subject::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => "{$name} for early-years learners", 'is_active' => true]
            );
        }

        return $result;
    }

    protected function seedClasses(Carbon $today, array $users, AcademicYear $year): array
    {
        $classDefs = [
            ['Daycare Little Stars', 'DC-A', ClassLevel::Daycare, 'teacherNeema@godwin.ac.tz', 15, 'Ages 2-3, play-based learning.'],
            ['Nursery B', 'NUR-B', ClassLevel::Nursery, 'teacherRuth@godwin.ac.tz', 20, 'Ages 3-4, foundational skills.'],
            ['KG1 Sunshine', 'KG1-A', ClassLevel::KG1, 'teacherPeter@godwin.ac.tz', 22, 'Ages 4-5, kindergarten year 1.'],
            ['KG2 Stars', 'KG2-A', ClassLevel::KG2, 'teacherGrace@godwin.ac.tz', 25, 'Ages 5-6, kindergarten year 2.'],
        ];

        $result = [];
        foreach ($classDefs as [$name, $code, $level, $teacherEmail, $capacity, $description]) {
            $result[$code] = SchoolClass::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'level' => $level,
                    'teacher_id' => $users[$teacherEmail]->id,
                    'capacity' => $capacity,
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => array_search($code, array_column($classDefs, null, 1)),
                ]
            );
        }

        return $result;
    }

    protected function seedClassSubjects(array $classes, array $subjects, array $users): void
    {
        $allocations = [
            'DC-A' => ['PHON', 'NUM', 'BIBL', 'FMS'],
            'NUR-B' => ['PHON', 'NUM', 'BIBL', 'ARTS', 'FMS'],
            'KG1-A' => ['PHON', 'NUM', 'BIBL', 'KISW', 'SCIE'],
            'KG2-A' => ['NUM', 'BIBL', 'ARTS', 'KISW', 'SCIE'],
        ];

        foreach ($allocations as $classCode => $subjectCodes) {
            $class = $classes[$classCode];
            $teacherId = $class->teacher_id;
            foreach ($subjectCodes as $subjectCode) {
                $subject = $subjects[$subjectCode];
                DB::table('class_subject')->updateOrInsert(
                    ['class_id' => $class->id, 'subject_id' => $subject->id],
                    ['teacher_id' => $teacherId, 'updated_at' => now()]
                );
            }
        }
    }

    protected function seedStudents(Carbon $today, array $classes): array
    {
        $firstNames = ['Amina', 'Joshua', 'Ester', 'Daniel', 'Mercy', 'Yusuf', 'Sarah', 'David',
            'Ruth', 'Samuel', 'Grace', 'Emmanuel', 'Faith', 'John', 'Mary', 'Peter',
            'Neema', 'Juma', 'Moshi', 'Kileo', 'Asi', 'Rehema', 'Irene', 'Baraka'];
        $lastNames = ['Juma', 'Moshi', 'Kileo', 'Mwakalinga', 'Sanga', 'Kagera', 'Mhame', 'Mushi',
            'Kambi', 'Saidi', 'John', 'Peter', 'Paul', 'Thomas', 'James', 'Joseph'];

        $students = [];
        $counter = 0;
        $dobByLevel = [
            'daycare' => [2, 3],
            'nursery' => [3, 4],
            'kg1' => [4, 5],
            'kg2' => [5, 6],
        ];

        foreach ($classes as $class) {
            $level = $class->level->value;
            $ageRange = $dobByLevel[$level] ?? [4, 5];
            for ($i = 0; $i < 8; $i++) {
                $counter++;
                $first = $firstNames[($counter - 1) % count($firstNames)];
                $last = $lastNames[($counter - 1) % count($lastNames)];
                $age = random_int($ageRange[0], $ageRange[1]);
                $monthsOffset = random_int(0, 11);

                $students[] = Student::updateOrCreate(
                    ['reg_no' => sprintf('GWD-2026-%04d', $counter)],
                    [
                        'first_name' => $first,
                        'last_name' => $last,
                        'gender' => $counter % 2 === 0 ? Gender::Male : Gender::Female,
                        'dob' => $today->copy()->subYears($age)->subMonths($monthsOffset),
                        'class_id' => $class->id,
                        'admission_date' => $today->copy()->subMonths(random_int(0, 8))->subDays(random_int(0, 25)),
                        'status' => StudentStatus::Active,
                        'medical_notes' => $counter % 7 === 0 ? 'Peanut allergy — monitor meals' : null,
                        'notes' => self::DEMO_MARKER,
                    ]
                );
            }
        }

        return $students;
    }

    protected function seedParentStudentLinks(array $students, array $users): void
    {
        $parents = [
            'parentJuma@example.com',
            'parentMoshi@example.com',
            'parentKileo@example.com',
            'parentAsi@example.com',
            'parentRehema@example.com',
        ];

        $studentIndex = 0;
        foreach ($parents as $parentEmail) {
            $parent = $users[$parentEmail];
            $childCount = $parentEmail === 'parentJuma@example.com' ? 2 : 1;
            for ($j = 0; $j < $childCount && $studentIndex < count($students); $j++, $studentIndex++) {
                $student = $students[$studentIndex];
                DB::table('parent_student')->updateOrInsert(
                    ['parent_id' => $parent->id, 'student_id' => $student->id],
                    ['relationship_type' => 'guardian', 'is_primary_contact' => 1, 'updated_at' => now()]
                );
            }
        }
    }

    protected function seedExaminations(Carbon $today, array $academic, array $users): void
    {
        $year = $academic['year'];
        $term2 = $academic['terms']['Second Term'];
        $term3 = $academic['terms']['Third Term'];

        $exams = [
            ['Midterm Examination', 'term2' => $term2, 'status' => 'published', 'created_by' => $users['head@godwin.ac.tz']->id],
            ['Final Examination', 'term2' => $term3, 'status' => 'draft', 'created_by' => $users['head@godwin.ac.tz']->id],
        ];

        foreach ($exams as $examData) {
            $name = $examData[0];
            $term = $examData['term2'];
            $status = $examData['status'];
            $createdBy = $examData['created_by'];

            $exam = \App\Models\Examination::updateOrCreate(
                ['name' => $name, 'academic_year_id' => $year->id],
                [
                    'term_id' => $term->id,
                    'status' => $status,
                    'description' => "{$name} for the {$term->name}.",
                    'created_by' => $createdBy,
                ]
            );

            foreach ($this->getClassCodes() as $classCode) {
                $class = SchoolClass::where('code', $classCode)->first();
                if ($class) {
                    DB::table('examination_classes')->updateOrInsert(
                        ['examination_id' => $exam->id, 'class_id' => $class->id],
                        ['updated_at' => now()]
                    );
                }
            }

            foreach (['PHON', 'NUM', 'BIBL'] as $subjectCode) {
                $subject = Subject::where('code', $subjectCode)->first();
                if ($subject) {
                    DB::table('examination_subjects')->updateOrInsert(
                        ['examination_id' => $exam->id, 'subject_id' => $subject->id],
                        ['max_score' => 100.00, 'updated_at' => now()]
                    );
                }
            }
        }
    }

    protected function getClassCodes(): array
    {
        return ['DC-A', 'NUR-B', 'KG1-A', 'KG2-A'];
    }

    protected function seedAssessments(Carbon $today, array $students, array $classes, array $subjects, array $academic, array $users): void
    {
        $term2 = $academic['terms']['Second Term'];
        $statuses = ['draft', 'submitted', 'approved', 'returned'];
        $types = [AssessmentType::Test, AssessmentType::Milestone, AssessmentType::Participation];

        $count = 0;
        foreach ($students as $student) {
            $class = $classes[$student->class->code];
            $teacherId = $class->teacher_id;
            $subjectCodes = ['PHON', 'NUM', 'BIBL'];

            foreach ($subjectCodes as $index => $subjectCode) {
                $subject = $subjects[$subjectCode];
                $status = $statuses[$count % 4];
                $type = $types[$count % 3];
                $score = random_int(60, 95);
                $maxScore = 100.00;

                \App\Models\Assessment::updateOrCreate(
                    ['student_id' => $student->id, 'examination_id' => null, 'subject_id' => $subject->id],
                    [
                        'class_id' => $class->id,
                        'term_id' => $term2->id,
                        'title' => "{$subject->name} Continuous Assessment",
                        'type' => $type->value,
                        'score' => (string) $score,
                        'max_score' => (string) $maxScore,
                        'remarks' => 'Progress noted during class.',
                        'recorded_by' => $teacherId,
                        'obtained_at' => $today->copy()->subDays(random_int(3, 40))->toDateString(),
                        'status' => $status,
                    ]
                );
                $count++;
            }
        }
    }

    protected function seedHomeworks(Carbon $today, array $classes, array $subjects, array $users): void
    {
        foreach ($classes as $class) {
            $subject = $subjects['PHON'];
            Homework::updateOrCreate(
                ['class_id' => $class->id, 'title' => 'Letter sounds A — F'],
                [
                    'subject_id' => $subject->id,
                    'teacher_id' => $class->teacher_id,
                    'type' => HomeworkType::Homework,
                    'description' => 'Practise the letter sounds at home with your child.',
                    'assigned_on' => $today->copy()->subDays(4),
                    'due_on' => $today->copy()->addDays(3),
                    'status' => 'published',
                ]
            );
        }
    }

    protected function seedTimetables(Carbon $today, array $classes, array $subjects, array $users): void
    {
        $days = [TimetableDay::Monday, TimetableDay::Tuesday, TimetableDay::Wednesday, TimetableDay::Thursday, TimetableDay::Friday];
        $slots = [['08:00:00', '09:00:00'], ['09:15:00', '10:15:00'], ['10:30:00', '11:30:00']];
        $subjectCodes = ['PHON', 'NUM', 'BIBL'];

        foreach ($classes as $class) {
            foreach ($days as $day) {
                foreach ($slots as $slotIndex => $slot) {
                    $subjectCode = $subjectCodes[$slotIndex % count($subjectCodes)];
                    $subject = $subjects[$subjectCode];
                    Timetable::updateOrCreate(
                        ['class_id' => $class->id, 'day' => $day->value, 'start_time' => $slot[0]],
                        [
                            'subject_id' => $subject->id,
                            'teacher_id' => $class->teacher_id,
                            'end_time' => $slot[1],
                            'room' => $class->code . ' Room',
                        ]
                    );
                }
            }
        }
    }

    protected function seedAttendance(Carbon $today, array $students, array $classes, array $users): void
    {
        $rows = [];
        foreach ($students as $student) {
            $teacherId = $classes[$student->class->code]->teacher_id;
            for ($day = 10; $day >= 0; $day--) {
                $date = $today->copy()->subDays($day);
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

                $rows[] = [
                    'student_id' => $student->id,
                    'date' => $date->toDateString(),
                    'status' => $status->value,
                    'marked_by' => $teacherId,
                    'check_in_time' => $status === AttendanceStatus::Present ? '07:45:00' : null,
                    'remarks' => $status === AttendanceStatus::Excused ? 'Medical appointment' : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            foreach ($chunk as $row) {
                Attendance::updateOrCreate(
                    ['student_id' => $row['student_id'], 'date' => $row['date']],
                    $row
                );
            }
        }
    }

    protected function seedBehaviorNotes(Carbon $today, array $students, array $users): void
    {
        $noteTypes = [NoteType::Spiritual, NoteType::Character, NoteType::Behavior, NoteType::Achievement];
        $noteTexts = [
            'Recited a Bible verse confidently in class.',
            'Shared toys with a classmate without being asked.',
            'Led the morning prayer with great confidence.',
            'Completed all colouring pages neatly.',
            'Helped tidy the classroom after lunch.',
            'Showed excellent respect to the teacher.',
        ];

        $index = 0;
        foreach (array_slice($students, 0, 22) as $student) {
            $teacherId = $student->class->teacher_id;
            BehaviorNote::updateOrCreate(
                ['student_id' => $student->id, 'title' => 'Character observation', 'occurred_on' => $today->copy()->subDays($index % 25)],
                [
                    'noted_by' => $teacherId,
                    'type' => $noteTypes[$index % 4],
                    'note' => $noteTexts[$index % count($noteTexts)],
                    'is_positive' => true,
                ]
            );
            $index++;
        }
    }

    protected function seedFeeStructures(Carbon $today, array $classes, AcademicYear $year): void
    {
        $fees = [
            ['Termly Tuition', 'tuition', null, 250000, 'termly'],
            ['Feeding & Meals', 'feeding', null, 60000, 'termly'],
            ['School Transport', 'transport', null, 90000, 'termly'],
        ];

        foreach ($fees as [$name, $type, $classId, $amount, $frequency]) {
            FeeStructure::updateOrCreate(
                ['name' => $name, 'academic_year_id' => $year->id],
                [
                    'type' => $type,
                    'class_id' => $classId,
                    'amount' => (string) $amount,
                    'frequency' => $frequency,
                    'description' => self::DEMO_MARKER,
                    'is_active' => true,
                ]
            );
        }
    }

    protected function seedInvoicesAndPayments(Carbon $today, array $students, array $academic, array $users, string $phone): void
    {
        $year = $academic['year'];
        $term2 = $academic['terms']['Second Term'];
        $accountant = $users['accounts@godwin.ac.tz'];
        $tuition = FeeStructure::where('name', 'Termly Tuition')->where('academic_year_id', $year->id)->first();
        $feeding = FeeStructure::where('name', 'Feeding & Meals')->where('academic_year_id', $year->id)->first();

        $paymentIndex = 0;
        $methods = [PaymentMethod::Cash, PaymentMethod::MobileMoney, PaymentMethod::BankTransfer, PaymentMethod::Cheque];

        foreach ($students as $student) {
            $paymentIndex++;
            $total = 310000;

            $paidRatio = match ($student->id % 4) {
                0 => 1.0,
                1 => 0.6,
                2 => 0.0,
                default => 1.0,
            };
            $paid = (int) round($total * $paidRatio);

            $invoice = Invoice::updateOrCreate(
                ['invoice_number' => sprintf('INV-2026-%06d', $paymentIndex)],
                [
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'term_id' => $term2->id,
                    'issue_date' => $today->copy()->subDays(random_int(40, 70)),
                    'due_date' => $today->copy()->subDays(random_int(-10, 20)),
                    'subtotal' => (string) $total,
                    'discount' => '0.00',
                    'total_amount' => (string) $total,
                    'amount_paid' => (string) $paid,
                    'status' => $paid === 0 ? InvoiceStatus::Unpaid : ($paid >= $total ? InvoiceStatus::Paid : InvoiceStatus::Partial),
                    'notes' => self::DEMO_MARKER . ' — synthetic test record, not a real payment.',
                    'issued_by' => $accountant->id,
                ]
            );

            $tuitionAmount = 250000;
            $feedingAmount = 60000;

            InvoiceItem::updateOrCreate(
                ['invoice_id' => $invoice->id, 'description' => 'Termly Tuition'],
                [
                    'fee_structure_id' => $tuition?->id,
                    'quantity' => 1,
                    'unit_amount' => (string) $tuitionAmount,
                    'amount' => (string) $tuitionAmount,
                ]
            );
            InvoiceItem::updateOrCreate(
                ['invoice_id' => $invoice->id, 'description' => 'Feeding & Meals'],
                [
                    'fee_structure_id' => $feeding?->id,
                    'quantity' => 1,
                    'unit_amount' => (string) $feedingAmount,
                    'amount' => (string) $feedingAmount,
                ]
            );

            if ($paid > 0) {
                Payment::updateOrCreate(
                    ['receipt_number' => sprintf('RCT-2026-%06d', $paymentIndex)],
                    [
                        'invoice_id' => $invoice->id,
                        'student_id' => $student->id,
                        'amount' => (string) $paid,
                        'method' => $methods[$student->id % 4],
                        'reference' => 'TXN-' . random_int(10000, 99999),
                        'paid_at' => $today->copy()->subDays(random_int(1, 55)),
                        'status' => PaymentStatus::Completed,
                        'received_by' => $accountant->id,
                        'notes' => self::DEMO_MARKER . ' — synthetic test payment.',
                    ]
                );
            }
        }
    }

    protected function seedExpenses(Carbon $today, array $users): void
    {
        $accountant = $users['accounts@godwin.ac.tz'];
        $expenseTitles = [
            ['Teacher salaries', 'salaries'],
            ['Water & electricity', 'utilities'],
            ['Classroom maintenance', 'maintenance'],
            ['Learning materials', 'supplies'],
            ['Kitchen & feeding', 'food'],
            ['School bus fuel', 'transport'],
        ];

        $index = 0;
        for ($month = 5; $month >= 0; $month--) {
            foreach (array_slice($expenseTitles, 0, 4) as $expense) {
                Expense::updateOrCreate(
                    ['title' => $expense[0] . ' ' . $today->copy()->subMonths($month)->format('Y-m'),
                        'incurred_on' => $today->copy()->subMonths($month)->day(random_int(2, 26))],
                    [
                        'category' => $expense[1],
                        'amount' => (string) (random_int(80, 900) * 1000),
                        'reference' => 'EXP-' . strtoupper(Str::random(6)),
                        'description' => self::DEMO_MARKER,
                        'recorded_by' => $accountant->id,
                    ]
                );
                $index++;
            }
        }
    }

    protected function seedAnnouncements(Carbon $today, array $users): void
    {
        $head = $users['head@godwin.ac.tz'];
        $posts = [
            ['Welcome to a New School Year', 'news', 'We warmly welcome every family back for the 2026 academic year.', true, true],
            ['Admission 2026 Now Open', 'admission', 'Applications for Daycare, Nursery, KG1 and KG2 are now open.', false, false],
            ['Parents Meeting — 28th', 'event', 'Monthly parents meeting to review children progress and welfare.', false, false],
        ];

        foreach ($posts as $index => [$title, $type, $excerpt, $pinned, $published]) {
            $slug = Str::slug($title);
            Announcement::updateOrCreate(
                ['slug' => $slug],
                [
                    'author_id' => $head->id,
                    'type' => $type,
                    'audience' => 'everyone',
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'body' => $excerpt . ' Full details are available from the school office.',
                    'is_published' => $published,
                    'is_pinned' => $pinned,
                    'published_at' => $published ? $today->copy()->subDays($index * 4) : null,
                ]
            );
        }
    }

    protected function seedEvents(Carbon $today, array $users): void
    {
        $head = $users['head@godwin.ac.tz'];
        $events = [
            ['Sports Day', 'Children compete in fun team games.', 5, '#D81B60'],
            ['Parent-Teacher Meeting', 'Termly progress review with class teachers.', 12, '#0288D1'],
            ['Church Thanksgiving Day', 'Family worship and thanksgiving.', 19, '#FBC02D'],
        ];

        foreach ($events as [$title, $description, $inDays, $color]) {
            SchoolEvent::updateOrCreate(
                ['title' => $title, 'start_date' => $today->copy()->addDays($inDays)],
                [
                    'created_by' => $head->id,
                    'description' => $description,
                    'end_date' => $today->copy()->addDays($inDays),
                    'start_time' => '08:00:00',
                    'end_time' => '12:00:00',
                    'location' => 'School compound, Medeli',
                    'color' => $color,
                    'audience' => 'everyone',
                    'is_published' => true,
                ]
            );
        }
    }

    protected function seedSmsTemplates(Carbon $today, array $users): void
    {
        $head = $users['head@godwin.ac.tz'];
        $templates = [
            ['Fee Reminder', 'fee_reminders', 'Dear {parent_name}, a reminder that {student_name}\'s fee invoice of {amount} TSh is due on {date}. Please clear the balance at the school office. - God-Win School', true],
            ['Attendance Alert', 'attendance', 'Dear {parent_name}, {student_name} was absent from {class_name} on {date}. Please contact the class teacher. - God-Win School', true],
            ['Result Published', 'results', 'Dear {parent_name}, {student_name}\'s {exam_name} results are now available. Please check the parent portal. - God-Win School', true],
            ['General Announcement', 'announcements', 'Dear parents, {school_name} announces: {message}. - God-Win School', true],
            ['Welcome', 'general', 'Welcome to {school_name}, {parent_name}! We are glad to have {student_name} in {class_name}. - God-Win School', false],
        ];

        foreach ($templates as [$name, $category, $template, $isActive]) {
            \App\Models\SmsTemplate::updateOrCreate(
                ['name' => $name],
                [
                    'category' => $category,
                    'template' => $template,
                    'is_active' => $isActive,
                    'created_by' => $head->id,
                ]
            );
        }
    }

    protected function seedSmsCampaigns(Carbon $today, array $users): void
    {
        $head = $users['head@godwin.ac.tz'];
        $campaigns = [
            ['Term 2 Fee Reminders', 'Draft fee reminder campaign for all parents.', 'bulk', 'pending'],
            ['Sports Day Invitation', 'Draft invitation for Sports Day event.', 'announcements', 'pending'],
        ];

        foreach ($campaigns as [$name, $description, $category, $status]) {
            \App\Models\SmsCampaign::updateOrCreate(
                ['name' => $name],
                [
                    'user_id' => $head->id,
                    'message' => $description,
                    'category' => $category,
                    'sender_id' => 'GODWIN',
                    'provider_type' => 'darsms',
                    'provider_name' => 'DARSMS',
                    'total_recipients' => 0,
                    'sent_count' => 0,
                    'delivered_count' => 0,
                    'failed_count' => 0,
                    'status' => $status,
                ]
            );
        }
    }

    protected function seedContactMessages(Carbon $today): void
    {
        $messages = [
            ['John Doe', 'john@example.com', '0712345678', 'Admission Inquiry', 'I would like to enrol my child in KG1 for 2026.'],
            ['Sarah Smith', 'sarah@example.com', '0756789012', 'Fee Question', 'Can I pay school fees in instalments?'],
        ];

        foreach ($messages as [$name, $email, $phone, $subject, $message]) {
            ContactMessage::updateOrCreate(
                ['email' => $email, 'subject' => $subject],
                [
                    'name' => $name,
                    'phone' => $phone,
                    'message' => $message,
                    'is_read' => false,
                    'admin_reply' => null,
                    'replied_at' => null,
                ]
            );
        }
    }

    protected function seedApplications(Carbon $today, array $classes, array $users): void
    {
        $head = $users['head@godwin.ac.tz'];
        $apps = [
            ['APP-2026-0001', 'Mama Salma Said', 'salma@example.com', '0757333444', 'mother', 'Zainabu', 'Said', Gender::Female, $today->copy()->subYears(4), $classes['KG1-A'], 'submitted', null],
            ['APP-2026-0002', 'Bwana John Peter', 'john@example.com', '0765432109', 'father', 'David', 'Peter', Gender::Male, $today->copy()->subYears(3), $classes['NUR-B'], 'accepted', $head->id],
        ];

        foreach ($apps as [$ref, $pname, $pemail, $pphone, $rel, $cfname, $clname, $gender, $dob, $class, $status, $reviewedBy]) {
            SchoolApplication::updateOrCreate(
                ['reference_no' => $ref],
                [
                    'parent_name' => $pname,
                    'parent_email' => $pemail,
                    'parent_phone' => $pphone,
                    'relationship' => $rel,
                    'child_first_name' => $cfname,
                    'child_last_name' => $clname,
                    'gender' => $gender,
                    'dob' => $dob,
                    'class_id' => $class->id,
                    'message' => 'Would like to enrol my child.',
                    'status' => $status,
                    'reviewed_by' => $reviewedBy,
                    'review_notes' => $status === 'accepted' ? 'Accepted for 2026 intake.' : null,
                    'submitted_at' => now()->subDays(2),
                ]
            );
        }
    }

    protected function seedAssets(Carbon $today, array $users): void
    {
        $head = $users['head@godwin.ac.tz'];
        $assets = [
            ['Classroom Chairs KG2', 'Furniture', 'CHR-001', 'Plastic chairs for KG2 classroom.', 25, $today->copy()->subMonths(2), 375000, 'School Supplies Ltd', 'KG2 Stars', 'good', 'active'],
            ['Whiteboard Daycare', 'Furniture', 'WBD-001', 'Magnetic whiteboard for Daycare.', 1, $today->copy()->subMonths(4), 150000, 'Office Equip Co', 'Daycare Little Stars', 'good', 'active'],
            ['Sound System', 'Electronics', 'SND-001', 'PA system for assemblies.', 1, $today->copy()->subMonths(6), 850000, 'Audio Solutions', 'School Hall', 'good', 'in_use'],
        ];

        foreach ($assets as [$name, $category, $code, $desc, $qty, $pdate, $price, $supplier, $location, $condition, $status]) {
            \App\Models\Asset::updateOrCreate(
                ['asset_code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'description' => $desc,
                    'quantity' => $qty,
                    'purchase_date' => $pdate,
                    'purchase_price' => (string) $price,
                    'supplier' => $supplier,
                    'location' => $location,
                    'condition' => $condition,
                    'status' => $status,
                    'recorded_by' => $head->id,
                ]
            );
        }
    }

    protected function seedSettings(Carbon $today): void
    {
        $settings = [
            ['general.school_name', 'God-Win Daycare & Nursery School', 'general', 'string'],
            ['general.school_phone', self::DEMO_PHONE, 'contact', 'string'],
            ['contact.contact_phone', self::DEMO_PHONE, 'contact', 'string'],
            ['general.school_location', 'Medeli, Dodoma', 'general', 'string'],
            ['sms.provider_type', 'darsms', 'sms', 'string'],
            ['sms.sender_id', 'GODWIN', 'sms', 'string'],
        ];

        foreach ($settings as [$key, $value, $group, $type]) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group, 'type' => $type]
            );
        }
    }

    protected function seedBanners(Carbon $today): void
    {
        Banner::updateOrCreate(
            ['title' => 'Admission 2026 Open'],
            [
                'subtitle' => 'Nafasi za Masomo Mwaka 2026 — apply online today',
                'image_path' => 'banners/admission-2026.svg',
                'link' => '/apply',
                'cta_label' => 'Apply Now',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    protected function seedNotifications(Carbon $today, array $users, array $students): void
    {
        $notifiableTypes = [
            ['App\Models\User', $users['parentJuma@example.com']->id],
            ['App\Models\User', $users['parentMoshi@example.com']->id],
            ['App\Models\User', $users['teacherNeema@godwin.ac.tz']->id],
            ['App\Models\User', $users['head@godwin.ac.tz']->id],
        ];

        $index = 0;
        foreach ($notifiableTypes as [$type, $id]) {
            $uuid = (string) Str::uuid();
            DB::table('notifications')->updateOrInsert(
                ['id' => $uuid],
                [
                    'type' => 'App\Notifications\DemoNotification',
                    'notifiable_type' => $type,
                    'notifiable_id' => $id,
                    'data' => json_encode(['title' => 'Demo notification ' . ($index + 1), 'body' => 'This is a synthetic demo notification.']),
                    'read_at' => $index % 2 === 0 ? now() : null,
                    'created_at' => $today->copy()->subHours($index + 1),
                    'updated_at' => now(),
                ]
            );
            $index++;
        }
    }

    protected function seedConversationsAndMessages(Carbon $today, array $users): void
    {
        $head = $users['head@godwin.ac.tz'];
        $teacher = $users['teacherNeema@godwin.ac.tz'];
        $parent = $users['parentJuma@example.com'];

        $conversation = \App\Models\Conversation::updateOrCreate(
            ['subject' => 'Student Progress Inquiry'],
            [
                'type' => 'direct',
                'last_message_at' => now(),
            ]
        );

        DB::table('conversation_user')->updateOrInsert(
            ['conversation_id' => $conversation->id, 'user_id' => $teacher->id],
            ['last_read_at' => now(), 'updated_at' => now()]
        );
        DB::table('conversation_user')->updateOrInsert(
            ['conversation_id' => $conversation->id, 'user_id' => $parent->id],
            ['last_read_at' => null, 'updated_at' => now()]
        );

        \App\Models\Message::updateOrCreate(
            ['conversation_id' => $conversation->id, 'sender_id' => $teacher->id, 'body' => 'Hello, I wanted to discuss your child\'s progress in class.'],
            [
                'attachments' => null,
                'read_at' => now(),
                'created_at' => now()->subHours(2),
                'updated_at' => now(),
            ]
        );
        \App\Models\Message::updateOrCreate(
            ['conversation_id' => $conversation->id, 'sender_id' => $parent->id, 'body' => 'Thank you, teacher. Please let me know the areas for improvement.'],
            [
                'attachments' => null,
                'read_at' => null,
                'created_at' => now()->subHours(1),
                'updated_at' => now(),
            ]
        );
    }

    protected function seedAuditLogs(Carbon $today, array $users, array $students): void
    {
        $admin = $users['admin@godwin.ac.tz'];
        $actions = [
            ['login', 'Administrator signed in'],
            ['student.updated', 'Updated student medical notes'],
            ['invoice.created', 'Generated invoice for termly fees'],
            ['attendance.marked', 'Marked attendance for KG1 Sunshine'],
        ];

        foreach ($actions as $index => [$action, $description]) {
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => $action,
                'description' => $description,
                'auditable_type' => Student::class,
                'auditable_id' => $students[$index]->id ?? null,
                'properties' => json_encode(['demo' => true]),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Godwin Demo Seeder',
                'created_at' => $today->copy()->subHours($index + 1),
            ]);
        }
    }
}

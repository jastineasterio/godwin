<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HEAD OF SCHOOL — daily operational & academic leader.
 */
class HeadOfSchoolDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $now = Carbon::now();

        // --- KPI cards ---------------------------------------------------
        $enrolledStudents = Student::active()->count();
        $activeTeachers = User::role(UserRole::Teacher)->active()->count();
        $activeClasses = SchoolClass::active()->count();

        $monthRate = $this->overallAttendanceRate(
            $now->copy()->startOfMonth(),
            $now
        );

        // --- Class-by-class attendance (bar chart, this month) ------------
        $classAttendance = SchoolClass::active()
            ->with('students.attendance')
            ->ordered()
            ->get()
            ->map(function (SchoolClass $class) use ($now) {
                $rows = $class->students
                    ->flatMap(fn ($student) => $student->attendance
                        ->filter(fn ($a) => $a->date->between(
                            $now->copy()->startOfMonth(),
                            $now
                        )));

                $rate = $rows->isEmpty()
                    ? 0
                    : round(
                        $rows->filter(fn ($a) => $a->status->countsAsPresent())->count()
                        / $rows->count() * 100,
                        1
                    );

                return [
                    'class' => $class->name,
                    'rate' => $rate,
                    'students' => $class->students()->count(),
                ];
            })
            ->values();

        // --- Class roster summary (small table/cards) ---------------------
        $classRoster = SchoolClass::active()
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->with('teacher:id,name')
            ->ordered()
            ->get(['id', 'name', 'code', 'level', 'teacher_id', 'capacity']);

        return Inertia::render('Dashboards/HeadOfSchool', [
            'stats' => [
                ['label' => 'Total Enrolled Students', 'value' => $enrolledStudents, 'accent' => 'primary'],
                ['label' => 'Active Teachers', 'value' => $activeTeachers, 'accent' => 'secondary'],
                ['label' => 'Active Classes', 'value' => $activeClasses, 'accent' => 'accent'],
                ['label' => 'Average Attendance', 'value' => ($monthRate ?? 0).'%', 'accent' => 'primary'],
            ],
            'charts' => ['classAttendance' => $classAttendance],
            'classes' => $classRoster,
        ]);
    }

    private function overallAttendanceRate(Carbon $start, Carbon $end): ?float
    {
        $rows = Attendance::between($start->toDateString(), $end->toDateString())->get(['status']);

        if ($rows->isEmpty()) {
            return null;
        }

        return round(
            $rows->filter(fn ($r) => $r->status->countsAsPresent())->count() / $rows->count() * 100,
            1
        );
    }
}

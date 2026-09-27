<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TimetableDay;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Timetable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TEACHER — classroom & assessment management, scoped STRICTLY to
 * the classes this teacher owns (data isolation).
 */
class TeacherDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $teacher = $request->user();
        $today = Carbon::today();

        // --- My assigned classes with student counts ----------------------
        $myClasses = $teacher->classesTaught()
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->get(['id', 'name', 'code', 'level', 'capacity']);

        // --- Today's timetable (Mon–Sat; weekends have no lessons) ---------
        $day = TimetableDay::tryFrom(strtolower($today->format('l')));

        $todaySchedule = $day
            ? Timetable::forTeacher($teacher->id)
                ->forDay($day)
                ->with('subject:id,name,code')
                ->orderBy('start_time')
                ->get(['id', 'class_id', 'subject_id', 'day', 'start_time', 'end_time', 'room'])
            : collect();

        // --- Weekly attendance trend across my classes (line chart) -------
        $classIds = $myClasses->pluck('id');

        $weeklyTrend = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($classIds, $today) {
                $date = $today->copy()->subDays($daysAgo);

                $rows = Attendance::onDate($date)
                    ->whereIn('student_id', function ($query) use ($classIds) {
                        $query->select('id')
                            ->from('students')
                            ->whereIn('class_id', $classIds);
                    })
                    ->get(['status']);

                $rate = $rows->isEmpty()
                    ? 0
                    : round(
                        $rows->filter(fn ($r) => $r->status->countsAsPresent())->count()
                        / $rows->count() * 100,
                        1
                    );

                return [
                    'day' => $date->format('D'),
                    'rate' => $rate,
                    'marked' => $rows->count(),
                ];
            });

        // --- Today's quick stats -------------------------------------------
        $markedToday = Attendance::onDate($today)
            ->whereIn('student_id', function ($query) use ($classIds) {
                $query->select('id')->from('students')->whereIn('class_id', $classIds);
            })
            ->count();

        $studentTotal = Student::whereIn('class_id', $classIds)
            ->where('status', 'active')
            ->count();

        return Inertia::render('Dashboards/Teacher', [
            'stats' => [
                ['label' => 'My Classes', 'value' => $myClasses->count(), 'accent' => 'primary'],
                ['label' => 'My Students', 'value' => $studentTotal, 'accent' => 'secondary'],
                ['label' => 'Marked Today', 'value' => $markedToday, 'accent' => 'accent'],
                ['label' => 'Roster Remaining', 'value' => max(0, $studentTotal - $markedToday), 'accent' => 'primary'],
            ],
            'classes' => $myClasses,
            'schedule' => $todaySchedule,
            'charts' => ['weeklyTrend' => $weeklyTrend],
            'today' => [
                'label' => $today->format('l, d M Y'),
                'marked' => $markedToday,
                'total' => $studentTotal,
            ],
        ]);
    }
}

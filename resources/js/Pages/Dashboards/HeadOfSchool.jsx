import { Baby, CalendarDays, GraduationCap, Percent, Users } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, ProgressBar, StatCard } from '../../Components/ui';
import { BarChartCard, ChartCard } from '../../Components/charts';
import { BRAND } from '../../Components/ui';

/* ===========================================================================
 * HEAD OF SCHOOL DASHBOARD — daily operational & academic leadership
 * ======================================================================== */

export default function HeadOfSchool({ stats, charts, classes }) {
    const statIcons = [GraduationCap, Users, Baby, Percent];
    const statFills = ['bg-sky-500', 'bg-emerald-500', 'bg-purple-500', 'bg-amber-500'];

    // Simple agenda: the largest classes to supervise first
    const schedule = [...(classes ?? [])]
        .sort((a, b) => b.students_count - a.students_count)
        .slice(0, 5);

    return (
        <DashboardLayout
            title="Academic Leadership"
            subtitle="Enrollment, teachers, classes and attendance across the whole school."
        >
            {/* ---------- KPI cards ---------------------------------------- */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat, i) => (
                    <StatCard
                        key={stat.label}
                        index={i}
                        icon={statIcons[i]}
                        label={stat.label}
                        value={stat.value}
                        accent={stat.accent}
                        variant="filled"
                        fill={statFills[i]}
                    />
                ))}
            </div>

            {/* ---------- Attendance comparison + focus -------------------- */}
            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <ChartCard
                    title="Class-by-Class Attendance"
                    subtitle="This month's attendance rate per class"
                    className="xl:col-span-2"
                >
                    <BarChartCard
                        data={charts.classAttendance}
                        xKey="class"
                        yKey="rate"
                        color={BRAND.primary}
                        unit="%"
                    />
                </ChartCard>

                <Card
                    title="Today's Focus"
                    subtitle="Largest classes to supervise"
                    delay={0.05}
                    action={
                        <span className="flex items-center gap-1.5 text-xs font-bold text-secondary">
                            <CalendarDays className="h-4 w-4" />
                            Schedule
                        </span>
                    }
                >
                    <ul className="space-y-3">
                        {schedule.map((klass) => (
                            <li key={klass.id} className="rounded-xl bg-canvas p-3">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="truncate text-sm font-bold text-ink">{klass.name}</p>
                                    <Badge tone="secondary">{klass.code}</Badge>
                                </div>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    {klass.students_count} students · {klass.teacher?.name ?? 'No teacher'}
                                </p>
                                <div className="mt-2">
                                    <ProgressBar
                                        value={Math.round(
                                            (klass.students_count / Math.max(1, klass.capacity)) * 100
                                        )}
                                        color="bg-secondary"
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>

            {/* ---------- Class roster table ------------------------------- */}
            <Card
                title="Active Classes"
                subtitle="KG1, KG2, Nursery & Daycare rosters"
                className="mt-6"
                delay={0.1}
            >
                {/* Desktop table */}
                <div className="hidden overflow-x-auto sm:block">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                <th className="pb-3 font-bold">Class</th>
                                <th className="pb-3 font-bold">Code</th>
                                <th className="pb-3 font-bold">Level</th>
                                <th className="pb-3 font-bold">Class Teacher</th>
                                <th className="pb-3 font-bold">Students</th>
                                <th className="pb-3 font-bold">Capacity</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {classes?.map((klass) => (
                                <tr key={klass.id} className="transition hover:bg-canvas">
                                    <td className="py-3 font-bold text-ink">{klass.name}</td>
                                    <td className="py-3">
                                        <Badge tone="secondary">{klass.code}</Badge>
                                    </td>
                                    <td className="py-3 capitalize text-slate-600">{klass.level}</td>
                                    <td className="py-3 text-slate-600">
                                        {klass.teacher?.name ?? <span className="text-amber-600">Unassigned</span>}
                                    </td>
                                    <td className="py-3 font-bold text-ink">{klass.students_count}</td>
                                    <td className="py-3 text-slate-600">{klass.capacity}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Mobile cards */}
                <ul className="space-y-3 sm:hidden">
                    {classes?.map((klass) => (
                        <li key={klass.id} className="rounded-xl bg-canvas p-4">
                            <div className="flex items-center justify-between">
                                <p className="font-bold text-ink">{klass.name}</p>
                                <Badge tone="secondary">{klass.code}</Badge>
                            </div>
                            <p className="mt-1 text-xs capitalize text-slate-500">{klass.level}</p>
                            <p className="mt-2 text-sm text-slate-600">
                                Teacher: {klass.teacher?.name ?? 'Unassigned'} · {klass.students_count}/
                                {klass.capacity} students
                            </p>
                        </li>
                    ))}
                </ul>
            </Card>
        </DashboardLayout>
    );
}

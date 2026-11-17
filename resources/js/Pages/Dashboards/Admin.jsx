import {
    Activity,
    AppWindow,
    CalendarDays,
    ClipboardList,
    ShieldCheck,
    UserPlus,
    Users,
} from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, StatCard } from '../../Components/ui';
import { BarChartCard, ChartCard, TrendChart } from '../../Components/charts';
import { BRAND } from '../../Components/ui';
import { Link } from '@inertiajs/react';

/* ===========================================================================
 * ADMINISTRATOR DASHBOARD — system governance & website CMS
 * ======================================================================== */

export default function Admin({
    stats,
    charts,
    systemHealth,
    recentRegistrations,
    recentLogs,
    recentNews,
    pendingApplications,
    upcomingEvents,
    todayTimetable,
}) {
    const statIcons = [Users, Users, Users, Activity];
    const statFills = ['bg-sky-500', 'bg-emerald-500', 'bg-purple-500', 'bg-amber-500'];

    return (
        <DashboardLayout
            title="System Overview"
            subtitle="Complete governance of God-Win SMS — users, audit trail and website CMS."
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

            {/* ---------- Charts ------------------------------------------- */}
            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <ChartCard
                    title="Monthly Enrollment Growth"
                    subtitle="New students admitted (last 8 months)"
                >
                    <TrendChart data={charts.enrollmentGrowth} xKey="month" yKey="students" />
                </ChartCard>

                <ChartCard title="User Activity Distribution" subtitle="Accounts per role">
                    <BarChartCard
                        data={charts.userActivity}
                        xKey="role"
                        yKey="users"
                        color={BRAND.primary}
                        unit=""
                    />
                </ChartCard>
            </div>

            {/* ---------- Quick action panels ------------------------------ */}
            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                {/* System health */}
                <Card title="System Health" subtitle="Live server status" delay={0.05}>
                    <ul className="space-y-3 text-sm">
                        {[
                            ['PHP', systemHealth.php_version],
                            ['Laravel', systemHealth.laravel_version],
                            ['Database', systemHealth.database],
                            ['Cache', systemHealth.cache_store],
                            ['Sessions', systemHealth.session_driver],
                            ['Free disk', `${systemHealth.disk_free_mb} MB`],
                        ].map(([label, value]) => (
                            <li key={label} className="flex items-center justify-between gap-2">
                                <span className="font-semibold text-slate-500">{label}</span>
                                <span className="truncate font-bold text-ink">{value}</span>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-4 flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">
                        <ShieldCheck className="h-4 w-4" />
                        All systems operational
                    </div>
                </Card>

                {/* Recent registrations */}
                <Card
                    title="Recent Registrations"
                    subtitle="Latest student records"
                    delay={0.1}
                    action={<Badge tone="primary">{pendingApplications} pending</Badge>}
                >
                    <ul className="space-y-2.5">
                        {recentRegistrations?.length ? (
                            recentRegistrations.map((student) => (
                                <li
                                    key={student.id}
                                    className="flex items-center justify-between gap-2 rounded-xl bg-canvas px-3 py-2.5"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-bold text-ink">
                                            {student.first_name} {student.last_name}
                                        </p>
                                        <p className="truncate text-xs text-slate-500">{student.reg_no}</p>
                                    </div>
                                    <UserPlus className="h-4 w-4 shrink-0 text-primary" />
                                </li>
                            ))
                        ) : (
                            <li className="rounded-xl bg-canvas p-4 text-center text-sm text-slate-500">
                                No students registered yet.
                            </li>
                        )}
                    </ul>
                </Card>

                {/* Website CMS manager */}
                <Card
                    title="Website CMS"
                    subtitle="Public site content"
                    delay={0.15}
                    action={<Badge tone="secondary">CMS</Badge>}
                >
                    <ul className="space-y-2.5">
                        {recentNews?.length ? (
                            recentNews.map((post) => (
                                <li
                                    key={post.id}
                                    className="flex items-center justify-between gap-2 rounded-xl bg-canvas px-3 py-2.5"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-bold text-ink">{post.title}</p>
                                        <p className="text-xs capitalize text-slate-500">{post.type}</p>
                                    </div>
                                    <Badge tone={post.is_published ? 'success' : 'muted'}>
                                        {post.is_published ? 'Live' : 'Draft'}
                                    </Badge>
                                </li>
                            ))
                        ) : (
                            <li className="rounded-xl bg-canvas p-4 text-center text-sm text-slate-500">
                                No content yet — publish your first news post.
                            </li>
                        )}
                    </ul>
                </Card>

                {/* Today's Timetable */}
                <Card
                    title="Today's Timetable"
                    subtitle="All scheduled lessons"
                    delay={0.18}
                    action={<Link href="/timetable" className="text-xs font-bold text-primary hover:underline">View all</Link>}
                >
                    <ul className="divide-y divide-slate-100">
                        {todayTimetable?.length ? (
                            todayTimetable.map((slot) => (
                                <li key={slot.id} className="flex items-center justify-between gap-2 py-2.5">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-bold text-ink">{slot.subject_name}</p>
                                        <p className="truncate text-xs text-slate-500">{slot.class_name} · {slot.teacher_name}</p>
                                    </div>
                                    <span className="shrink-0 text-xs font-semibold text-slate-500">
                                        {slot.start_time && slot.end_time ? `${slot.start_time} – ${slot.end_time}` : slot.day}
                                    </span>
                                </li>
                            ))
                        ) : (
                            <li className="py-4 text-center text-sm text-slate-500">No lessons scheduled today.</li>
                        )}
                    </ul>
                </Card>

                {/* Upcoming Events */}
                <Card
                    title="Upcoming Events"
                    subtitle="School calendar"
                    delay={0.2}
                    action={<Link href="/events" className="text-xs font-bold text-primary hover:underline">View all</Link>}
                >
                    <ul className="space-y-2.5">
                        {upcomingEvents?.length ? (
                            upcomingEvents.map((ev) => (
                                <li key={ev.id} className="flex items-center justify-between gap-2 rounded-xl bg-canvas px-3 py-2.5">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-bold text-ink">{ev.title}</p>
                                        <p className="truncate text-xs text-slate-500">
                                            {ev.start_date}{ev.location ? ` · ${ev.location}` : ''}
                                        </p>
                                    </div>
                                    <Badge tone={ev.color ? 'accent' : 'muted'}>{ev.start_date}</Badge>
                                </li>
                            ))
                        ) : (
                            <li className="rounded-xl bg-canvas p-4 text-center text-sm text-slate-500">No upcoming events.</li>
                        )}
                    </ul>
                </Card>
            </div>

            {/* ---------- Audit trail -------------------------------------- */}
            <Card
                title="Audit Log"
                subtitle="Who did what, when — system-wide activity trail"
                className="mt-6"
                delay={0.1}
                action={
                    <span className="flex items-center gap-1.5 text-xs font-bold text-slate-400">
                        <ClipboardList className="h-4 w-4" />
                        {recentLogs?.length ?? 0} entries
                    </span>
                }
            >
                <ul className="divide-y divide-slate-100">
                    {recentLogs?.length ? (
                        recentLogs.map((log) => (
                            <li key={log.id} className="flex items-center gap-3 py-2.5">
                                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                    <AppWindow className="h-4 w-4" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-bold text-ink">
                                        {log.description || log.action}
                                    </p>
                                    <p className="truncate text-xs text-slate-500">
                                        {log.user?.name ?? 'System'} · {log.action}
                                    </p>
                                </div>
                                <time className="shrink-0 text-xs font-semibold text-slate-400">
                                    {new Date(log.created_at).toLocaleString('en-GB', {
                                        day: '2-digit',
                                        month: 'short',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </time>
                            </li>
                        ))
                    ) : (
                        <li className="py-6 text-center text-sm text-slate-500">
                            No activity recorded yet.
                        </li>
                    )}
                </ul>
            </Card>
        </DashboardLayout>
    );
}

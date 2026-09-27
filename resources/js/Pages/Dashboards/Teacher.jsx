import { motion } from 'framer-motion';
import { BookOpen, CalendarCheck, ClipboardCheck, Clock, MapPin, Users } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, ProgressBar, StatCard } from '../../Components/ui';
import { ChartCard, TrendChart } from '../../Components/charts';

/* ===========================================================================
 * TEACHER DASHBOARD — assigned classes only (strict data isolation)
 * ======================================================================== */

const QUICK_ACTIONS = [
    { label: 'Take Attendance', icon: ClipboardCheck, tone: 'bg-primary', hint: 'Mark the register fast' },
    { label: 'Record Assessment', icon: BookOpen, tone: 'bg-secondary', hint: 'Enter marks & milestones' },
    { label: 'Assign Homework', icon: CalendarCheck, tone: 'bg-accent', hint: 'Post to the class' },
];

export default function Teacher({ stats, classes, schedule, charts, today }) {
    const statIcons = [Users, Users, ClipboardCheck, Clock];
    const rollProgress = today.total ? Math.round((today.marked / today.total) * 100) : 0;

    return (
        <DashboardLayout
            title="My Classroom"
            subtitle={`${today.label} · register progress for your classes`}
        >
            {/* ---------- Quick actions ------------------------------------- */}
            <div className="grid gap-4 sm:grid-cols-3">
                {QUICK_ACTIONS.map((action, i) => (
                    <motion.button
                        key={action.label}
                        type="button"
                        initial={{ opacity: 0, y: 14 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: i * 0.06, duration: 0.3 }}
                        className="glass-card group flex items-center gap-4 p-5 text-left transition-all hover:-translate-y-0.5 hover:shadow-card-hover"
                    >
                        <span className={`grid h-12 w-12 shrink-0 place-items-center rounded-xl text-white ${action.tone}`}>
                            <action.icon className="h-6 w-6" strokeWidth={2.2} />
                        </span>
                        <span className="min-w-0">
                            <span className="block font-display text-base font-bold text-ink">
                                {action.label}
                            </span>
                            <span className="block truncate text-xs text-slate-500">{action.hint}</span>
                        </span>
                    </motion.button>
                ))}
            </div>

            {/* ---------- KPI cards ---------------------------------------- */}
            <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat, i) => (
                    <StatCard
                        key={stat.label}
                        index={i}
                        icon={statIcons[i]}
                        label={stat.label}
                        value={stat.value}
                        accent={stat.accent}
                    />
                ))}
            </div>

            {/* ---------- Classes + timetable ------------------------------- */}
            <div className="mt-6 grid gap-6 xl:grid-cols-5">
                <Card
                    title="My Assigned Classes"
                    subtitle="Classes where you are the class teacher"
                    className="xl:col-span-2"
                    delay={0.05}
                >
                    <ul className="space-y-3">
                        {classes?.length ? (
                            classes.map((klass) => (
                                <li key={klass.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="font-display text-sm font-bold text-ink">{klass.name}</p>
                                        <Badge tone="primary">{klass.students_count} students</Badge>
                                    </div>
                                    <p className="mt-1 text-xs uppercase tracking-wider text-slate-400">
                                        {klass.code} · {klass.level}
                                    </p>
                                    <div className="mt-2">
                                        <ProgressBar
                                            value={Math.round(
                                                (klass.students_count / Math.max(1, klass.capacity)) * 100
                                            )}
                                            color="bg-primary"
                                        />
                                    </div>
                                </li>
                            ))
                        ) : (
                            <li className="rounded-xl bg-canvas p-6 text-center text-sm text-slate-500">
                                No classes assigned to you yet.
                            </li>
                        )}
                    </ul>

                    {/* Register progress for today */}
                    <div className="mt-4 rounded-xl border border-primary/20 bg-primary/5 p-4">
                        <ProgressBar value={rollProgress} color="bg-primary" label="Today's register" />
                        <p className="mt-1.5 text-xs font-semibold text-slate-500">
                            {today.marked} of {today.total} students marked
                        </p>
                    </div>
                </Card>

                {/* Daily timetable */}
                <Card
                    title="Today's Timetable"
                    subtitle="Your lessons, times and classrooms"
                    className="xl:col-span-3"
                    delay={0.1}
                    action={<Badge tone="secondary">{schedule?.length ?? 0} slots</Badge>}
                >
                    <ol className="space-y-3">
                        {schedule?.length ? (
                            schedule.map((slot) => (
                                <li
                                    key={slot.id}
                                    className="flex items-center gap-4 rounded-xl border-l-4 border-primary bg-canvas p-3.5"
                                >
                                    <span className="shrink-0 text-center">
                                        <span className="block font-display text-sm font-extrabold text-ink">
                                            {slot.start_time?.slice(0, 5)}
                                        </span>
                                        <span className="block text-[10px] font-bold text-slate-400">
                                            {slot.end_time?.slice(0, 5)}
                                        </span>
                                    </span>
                                    <span className="h-10 w-px bg-slate-200" />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-bold text-ink">
                                            {slot.subject?.name ?? 'Lesson'}
                                        </span>
                                        <span className="block text-xs capitalize text-slate-500">
                                            {slot.day}
                                        </span>
                                    </span>
                                    {slot.room && (
                                        <span className="hidden shrink-0 items-center gap-1 text-xs font-bold text-slate-500 sm:flex">
                                            <MapPin className="h-3.5 w-3.5 text-secondary" />
                                            {slot.room}
                                        </span>
                                    )}
                                </li>
                            ))
                        ) : (
                            <li className="rounded-xl bg-canvas p-6 text-center text-sm text-slate-500">
                                No lessons scheduled for today.
                            </li>
                        )}
                    </ol>
                </Card>
            </div>

            {/* ---------- Weekly attendance trend --------------------------- */}
            <ChartCard
                title="Weekly Class Attendance Trend"
                subtitle="Attendance rate across my classes over the last 7 days"
                className="mt-6"
                delay={0.1}
            >
                <TrendChart data={charts.weeklyTrend} xKey="day" yKey="rate" color="#0288D1" unit="%" />
            </ChartCard>
        </DashboardLayout>
    );
}
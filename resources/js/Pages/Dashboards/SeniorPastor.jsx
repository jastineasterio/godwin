import { BookHeart, CalendarCheck2, Sparkles, Users } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, StatCard } from '../../Components/ui';
import { BarChartCard, ChartCard, RadarChartCard } from '../../Components/charts';
import { BRAND } from '../../Components/ui';

/* ===========================================================================
 * SENIOR PASTOR DASHBOARD — spiritual & educational oversight
 * (read-only strategic view)
 * ======================================================================== */

export default function SeniorPastor({ stats, charts, broadcasts }) {
    const statIcons = [Users, CalendarCheck2, Sparkles, BookHeart];
    const statFills = ['bg-sky-500', 'bg-emerald-500', 'bg-purple-500', 'bg-amber-500'];

    return (
        <DashboardLayout
            title="Oversight Dashboard"
            subtitle="Church & school governance — enrollment, attendance and moral development."
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
                    title="Monthly Attendance Overview"
                    subtitle="General attendance rate across the school"
                >
                    <BarChartCard
                        data={charts.monthlyAttendance}
                        xKey="month"
                        yKey="rate"
                        color={BRAND.primary}
                        unit="%"
                    />
                </ChartCard>

                <ChartCard
                    title="Spiritual Milestone Progress"
                    subtitle="Character & moral development notes recorded"
                >
                    <RadarChartCard
                        data={charts.spiritualRadar}
                        xKey="dimension"
                        series={['count']}
                        color={BRAND.accent}
                    />
                </ChartCard>
            </div>

            {/* ---------- Pastoral broadcast widget ------------------------ */}
            <Card
                title="Pastoral Announcements & Moral Guidance"
                subtitle="Broadcasts to parents and staff"
                className="mt-6"
                delay={0.1}
                action={<Badge tone="accent">Broadcast</Badge>}
            >
                <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {broadcasts?.length ? (
                        broadcasts.map((post) => (
                            <li key={post.id} className="rounded-xl border-l-4 border-primary bg-canvas p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="badge bg-primary/10 text-primary capitalize">
                                        {post.type}
                                    </span>
                                    <time className="text-[11px] font-semibold text-slate-400">
                                        {new Date(post.published_at).toLocaleDateString('en-GB', {
                                            day: '2-digit',
                                            month: 'short',
                                        })}
                                    </time>
                                </div>
                                <h4 className="mt-2 font-display text-sm font-bold text-ink">{post.title}</h4>
                                {post.excerpt && (
                                    <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500">
                                        {post.excerpt}
                                    </p>
                                )}
                            </li>
                        ))
                    ) : (
                        <li className="rounded-xl bg-canvas p-6 text-center text-sm text-slate-500 sm:col-span-2 xl:col-span-3">
                            No broadcasts published yet.
                        </li>
                    )}
                </ul>
            </Card>
        </DashboardLayout>
    );
}

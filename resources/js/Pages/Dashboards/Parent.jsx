import { motion } from 'framer-motion';
import { router } from '@inertiajs/react';
import {
    Banknote,
    Bell,
    BookOpen,
    CalendarCheck2,
    FileText,
    Percent,
    Users,
} from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, StatCard } from '../../Components/ui';
import { BarChartCard, ChartCard, DonutChart } from '../../Components/charts';
import { BRAND } from '../../Components/ui';

/* ===========================================================================
 * PARENT PORTAL — multi-child selector
 * Switching a child re-queries the server (?child=ID) but keeps the user
 * logged in and preserves component state — no re-login, no page reload.
 * ======================================================================== */

const QUICK_LINKS = [
    { label: 'Assessment Results', icon: BookOpen, tone: 'bg-primary' },
    { label: 'Homework Tracker', icon: FileText, tone: 'bg-secondary' },
    { label: 'Fee Statements', icon: Banknote, tone: 'bg-accent' },
    { label: 'Announcements', icon: Bell, tone: 'bg-ink' },
];

const INVOICE_TONES = {
    paid: 'success',
    partial: 'warning',
    unpaid: 'danger',
    overdue: 'danger',
    void: 'muted',
};

const initials = (name = '') =>
    name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('');

export default function Parent({ children, activeChildId, stats, profile, charts, invoices }) {
    const statIcons = [Percent, Users, CalendarCheck2, Banknote];
    const statFills = ['bg-sky-500', 'bg-emerald-500', 'bg-purple-500', 'bg-amber-500'];

    // Instant child switch — preserves state so the dashboard never flashes
    const switchChild = (id) => {
        if (id === activeChildId) return;
        router.get(`/dashboard?child=${id}`, {}, { preserveState: true, preserveScroll: true });
    };

    const attendanceRate = parseFloat(stats?.[0]?.value ?? '0') || 0;

    // Friendly empty state — account exists but no children are linked yet
    if (!children?.length || !profile) {
        return (
            <DashboardLayout
                title="Parent Portal"
                subtitle="Welcome — no children are linked to this account yet."
            >
                <Card className="py-14 text-center">
                    <span className="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <Users className="h-8 w-8" />
                    </span>
                    <h2 className="mt-5 font-display text-xl font-extrabold text-ink">
                        No children linked yet
                    </h2>
                    <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">
                        Our office links children to parent accounts during enrolment. Please contact the
                        school office to have your child added to this account.
                    </p>
                </Card>
            </DashboardLayout>
        );
    }

    return (
        <DashboardLayout
            title="Parent Portal"
            subtitle="Welcome — here's how your children are doing today."
        >
            {/* ---------- Child selector (My Children) --------------------- */}
            <div className="glass-card p-4 sm:p-5">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="mr-2 font-display text-sm font-bold uppercase tracking-wider text-slate-400">
                        My Children
                    </h2>
                    {children.map((child) => {
                        const active = child.id === activeChildId;

                        return (
                            <button
                                key={child.id}
                                type="button"
                                onClick={() => switchChild(child.id)}
                                className={`flex items-center gap-2.5 rounded-xl border px-3.5 py-2.5 text-left transition-all ${
                                    active
                                        ? 'border-primary bg-primary text-white shadow-lg shadow-primary/25'
                                        : 'border-slate-200 bg-white text-ink hover:border-primary/40'
                                }`}
                            >
                                <span
                                    className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg text-xs font-extrabold ${
                                        active ? 'bg-white/20' : 'bg-primary/10 text-primary'
                                    }`}
                                >
                                    {initials(child.name)}
                                </span>
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-bold">{child.name}</span>
                                    <span
                                        className={`block truncate text-[11px] ${
                                            active ? 'text-white/80' : 'text-slate-500'
                                        }`}
                                    >
                                        {child.class ?? 'Unassigned'}
                                    </span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ---------- Child profile card ------------------------------- */}
            <motion.div
                key={activeChildId}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3 }}
                className="mt-6 overflow-hidden rounded-2xl bg-gradient-to-br from-[#013157] via-[#01579B] to-[#0288D1] p-5 text-white shadow-card sm:p-6"
            >
                <div className="flex flex-wrap items-center gap-5">
                    <span className="grid h-20 w-20 shrink-0 place-items-center rounded-2xl bg-white/15 font-display text-2xl font-extrabold backdrop-blur">
                        {initials(profile.name)}
                    </span>
                    <div className="min-w-0 flex-1">
                        <h2 className="font-display text-xl font-extrabold sm:text-2xl">{profile.name}</h2>
                        <p className="mt-1 text-sm text-white/80">
                            {profile.class} · Reg No. {profile.reg_no}
                        </p>
                        {profile.recentResult && (
                            <span className="badge mt-2 bg-accent text-ink">
                                Latest: {profile.recentResult.title} — {profile.recentResult.percentage}%
                            </span>
                        )}
                    </div>
                    <div className="w-full sm:w-56">
                        <p className="text-xs font-bold uppercase tracking-wider text-white/70">
                            Attendance (30 days)
                        </p>
                        <p className="font-display text-3xl font-extrabold">{attendanceRate}%</p>
                        <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-white/25">
                            <motion.div
                                initial={{ width: 0 }}
                                animate={{ width: `${Math.min(100, attendanceRate)}%` }}
                                transition={{ duration: 0.8 }}
                                className="h-full rounded-full bg-accent"
                            />
                        </div>
                    </div>
                </div>
            </motion.div>

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
                        variant="filled"
                        fill={statFills[i]}
                    />
                ))}
            </div>

            {/* ---------- Child analytics charts --------------------------- */}
            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <ChartCard
                    title="Subject Performance"
                    subtitle={`Average score per subject — ${profile.name}`}
                    delay={0.05}
                >
                    <BarChartCard
                        data={charts.subjectGrades}
                        xKey="subject"
                        yKey="score"
                        color={BRAND.primary}
                        unit="%"
                    />
                </ChartCard>

                <ChartCard
                    title="Monthly Attendance"
                    subtitle="Last 30 days breakdown"
                    delay={0.1}
                >
                    <DonutChart
                        data={charts.attendanceDonut}
                        colors={[BRAND.primary, BRAND.accent, BRAND.secondary, '#94A3B8']}
                    />
                </ChartCard>
            </div>

            {/* ---------- Quick links + fee statements --------------------- */}
            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <Card title="Quick Links" subtitle="Everything about your child" delay={0.1}>
                    <div className="grid grid-cols-2 gap-3">
                        {QUICK_LINKS.map((link) => (
                            <button
                                key={link.label}
                                type="button"
                                className="group flex flex-col items-center gap-2 rounded-xl bg-canvas p-4 text-center transition hover:-translate-y-0.5 hover:shadow-card"
                            >
                                <span
                                    className={`grid h-10 w-10 place-items-center rounded-xl text-white ${link.tone}`}
                                >
                                    <link.icon className="h-5 w-5" />
                                </span>
                                <span className="text-xs font-bold text-ink">{link.label}</span>
                            </button>
                        ))}
                    </div>
                </Card>

                <Card
                    title="Fee Statements"
                    subtitle="Recent invoices & balances"
                    className="xl:col-span-2"
                    delay={0.15}
                    action={<Badge tone="primary">{invoices?.length ?? 0} invoices</Badge>}
                >
                    <ul className="space-y-2.5">
                        {invoices?.length ? (
                            invoices.map((invoice) => (
                                <li
                                    key={invoice.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-canvas px-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-mono text-sm font-bold text-ink">
                                            {invoice.invoice_number}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            Due {new Date(invoice.due_date).toLocaleDateString('en-GB')}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="font-display text-base font-extrabold text-ink">
                                            TZS {Number(invoice.total_amount).toLocaleString()}
                                        </span>
                                        <Badge tone={INVOICE_TONES[invoice.status] ?? 'muted'}>
                                            {invoice.status}
                                        </Badge>
                                    </div>
                                </li>
                            ))
                        ) : (
                            <li className="rounded-xl bg-canvas p-6 text-center text-sm text-slate-500">
                                No invoices issued yet.
                            </li>
                        )}
                    </ul>
                </Card>
            </div>
        </DashboardLayout>
    );
}

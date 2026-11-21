import { useState } from 'react';
import { Banknote, ChevronRight, Clock, CreditCard, FileText, TrendingDown, TrendingUp, Users } from 'lucide-react';
import { Link, router, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState, StatCard } from '../../Components/ui';
import { BarChartCard, ChartCard, DonutChart, GroupedBarChart, TrendChart } from '../../Components/charts';
import { PageHeader, PrimaryAction } from '../../Components/page';

const PERIODS = [
    { value: 'current_week', label: 'Current Week' },
    { value: 'previous_week', label: 'Previous Week' },
    { value: 'current_month', label: 'Current Month' },
    { value: 'previous_month', label: 'Previous Month' },
    { value: 'current_year', label: 'Current Year' },
    { value: 'previous_year', label: 'Previous Year' },
    { value: 'custom', label: 'Custom Range' },
];

const money = (value) => `TZS ${Number(value ?? 0).toLocaleString()}`;

export default function FinanceDashboard({ stats, charts, range, filters }) {
    const [period, setPeriod] = useState(filters.period ?? 'current_month');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const applyRange = () => {
        const params = { period };
        if (period === 'custom') {
            params.from = from;
            params.to = to;
        }
        router.get('/finance', params, { preserveState: true, replace: true });
    };

    const isCustom = period === 'custom';

    return (
        <DashboardLayout
            title="Finance Management"
            subtitle="School income, expenses and financial reporting"
        >
            <PageHeader
                title="Finance Management"
                subtitle={`Reporting period: ${range.period.replace(/_/g, ' ')}`}
                action={<PrimaryAction href="/expenses/create">Record Expense</PrimaryAction>}
            />

            {/* ---------- KPI cards ---------- */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    index={0}
                    icon={CreditCard}
                    label="Total Income"
                    value={money(stats.total_income)}
                    accent="secondary"
                    hint="Completed payments this period"
                />
                <StatCard
                    index={1}
                    icon={FileText}
                    label="Total Expenses"
                    value={money(stats.total_expenses)}
                    accent="accent"
                    hint="Recorded expenses this period"
                />
                <StatCard
                    index={2}
                    icon={stats.net_balance >= 0 ? TrendingUp : TrendingDown}
                    label="Net Balance"
                    value={money(stats.net_balance)}
                    accent={stats.net_balance >= 0 ? 'secondary' : 'danger'}
                    hint="Income minus expenses"
                />
                <StatCard
                    index={3}
                    icon={Users}
                    label="Outstanding Fees"
                    value={money(stats.outstanding_fees)}
                    accent="primary"
                    hint="Unpaid invoice balances"
                />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mt-4">
                <StatCard
                    index={4}
                    icon={Clock}
                    label="Fees Collected"
                    value={money(stats.fees_collected)}
                    accent="secondary"
                />
                <StatCard
                    index={5}
                    icon={Banknote}
                    label="Pending Payments"
                    value={stats.pending_payments}
                    accent="warning"
                    hint="Invoices awaiting settlement"
                />
                <StatCard
                    index={6}
                    icon={FileText}
                    label="Transactions"
                    value={stats.transaction_count}
                    accent="primary"
                    hint="Payments + expenses this period"
                />
                <StatCard
                    index={7}
                    icon={ChevronRight}
                    label="Period"
                    value={
                        <span className="font-mono text-xs text-slate-600">
                            {range.from} → {range.to}
                        </span>
                    }
                    accent="muted"
                    variant="filled"
                    fill="bg-slate-100 text-ink"
                />
            </div>

            {/* ---------- Period selector ---------- */}
            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div className="flex-1">
                    <p className="mb-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        Reporting Period
                    </p>
                    <div className="flex gap-2 flex-wrap">
                        {PERIODS.map((p) => (
                            <button
                                key={p.value}
                                onClick={() => {
                                    setPeriod(p.value);
                                    if (p.value !== 'custom') {
                                        setFrom('');
                                        setTo('');
                                    }
                                }}
                                className={`px-3 py-1.5 rounded-lg text-sm font-medium border transition ${
                                    period === p.value
                                        ? 'bg-primary text-white border-primary'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-primary/40'
                                }`}
                            >
                                {p.label}
                            </button>
                        ))}
                    </div>
                </div>
                {isCustom && (
                    <div className="flex gap-2 w-auto">
                        <input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            className="input"
                        />
                        <input
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                            className="input"
                        />
                        <button
                            onClick={applyRange}
                            className="btn-primary px-4"
                        >
                            Apply
                        </button>
                    </div>
                )}
            </div>

            {/* ---------- Charts ---------- */}
            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <ChartCard
                    title="Income vs Expenses"
                    subtitle="Monthly comparison"
                    className="xl:col-span-2"
                >
                    <GroupedBarChart
                        data={charts.income_vs_expenses ?? []}
                        xKey="month"
                        series={[
                            { key: 'income', color: '#01579B' },
                            { key: 'expenses', color: '#FBC02D' },
                        ]}
                        unit="TZS"
                    />
                </ChartCard>

                <ChartCard title="Monthly Income" subtitle="Income trend by month">
                    <TrendChart
                        data={charts.monthly_income ?? []}
                        xKey="month"
                        yKey="value"
                        color="#01579B"
                        unit="TZS"
                    />
                </ChartCard>

                <ChartCard title="Monthly Expenses" subtitle="Expense trend by month">
                    <TrendChart
                        data={charts.monthly_expenses ?? []}
                        xKey="month"
                        yKey="value"
                        color="#FBC02D"
                        unit="TZS"
                    />
                </ChartCard>

                <ChartCard title="Net Balance Trend" subtitle="Income minus expenses by month">
                    <TrendChart
                        data={charts.net_balance_trend ?? []}
                        xKey="month"
                        yKey="value"
                        color="#1E88E5"
                        unit="TZS"
                    />
                </ChartCard>
            </div>

            <div className="mt-6 grid gap-6 sm:grid-cols-2">
                <ChartCard title="Income by Category" subtitle="Where money comes from">
                    <DonutChart data={charts.income_by_category ?? []} />
                </ChartCard>

                <ChartCard title="Expenses by Category" subtitle="Where money goes">
                    <DonutChart data={charts.expenses_by_category ?? []} />
                </ChartCard>
            </div>

            {/* ---------- Quick actions ---------- */}
            <div className="mt-6 flex gap-3">
                <Link
                    href="/expenses"
                    className="flex-1 btn-primary text-center py-3"
                >
                    <FileText className="h-4 w-4 mr-2" /> View Expenses
                </Link>
                <Link
                    href="/invoices"
                    className="flex-1 btn-primary text-center py-3"
                >
                    <CreditCard className="h-4 w-4 mr-2" /> View Invoices
                </Link>
                <Link
                    href={
                        '/finance/report?period=' +
                        range.period +
                        (range.from ? `&from=${range.from}&to=${range.to}` : '')
                    }
                    className="flex-1 btn-primary text-center py-3"
                >
                    <Banknote className="h-4 w-4 mr-2" /> Full Report
                </Link>
            </div>
        </DashboardLayout>
    );
}

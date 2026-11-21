import { useState } from 'react';
import { Banknote, ChevronRight, Download, FileText, Printer, TrendingUp } from 'lucide-react';
import { router } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState, StatCard } from '../../Components/ui';
import { DonutChart } from '../../Components/charts';
import { PageHeader } from '../../Components/page';

const money = (value) => `TZS ${Number(value ?? 0).toLocaleString()}`;

const PERIODS = [
    { value: 'current_week', label: 'Current Week' },
    { value: 'previous_week', label: 'Previous Week' },
    { value: 'current_month', label: 'Current Month' },
    { value: 'previous_month', label: 'Previous Month' },
    { value: 'current_year', label: 'Current Year' },
    { value: 'previous_year', label: 'Previous Year' },
    { value: 'custom', label: 'Custom Range' },
];

export default function FinanceReport({ range, stats, income_by_category, expenses_by_category, transactions }) {
    const [period, setPeriod] = useState(range.period ?? 'current_month');
    const [from, setFrom] = useState(range.from ?? '');
    const [to, setTo] = useState(range.to ?? '');

    const applyRange = () => {
        const params = { period };
        if (period === 'custom') {
            params.from = from;
            params.to = to;
        }
        router.get('/finance/report', params, { preserveState: true, replace: true });
    };

    const isCustom = period === 'custom';

    const typeTone = { income: 'success', expense: 'danger' };

    return (
        <DashboardLayout
            title="Financial Report"
            subtitle="Income, expenses and transaction breakdown for the selected period"
        >
            <PageHeader
                title="Financial Report"
                subtitle={`Period: ${range.period.replace(/_/g, ' ')} (${range.from} → ${range.to})`}
                action={
                    <div className="flex gap-2">
                        <button
                            onClick={() => router.get('/finance/report/pdf', {
                                period,
                                ...(isCustom ? { from, to } : {}),
                            })}
                            className="btn-ghost flex items-center gap-2 text-sm"
                            title="Download PDF"
                        >
                            <Download className="h-4 w-4" /> PDF
                        </button>
                        <button
                            onClick={() => router.get('/finance/report/csv', {
                                period,
                                ...(isCustom ? { from, to } : {}),
                            })}
                            className="btn-ghost flex items-center gap-2 text-sm"
                            title="Export CSV"
                        >
                            <FileText className="h-4 w-4" /> CSV
                        </button>
                        <button
                            onClick={() => window.print()}
                            className="btn-ghost flex items-center gap-2 text-sm"
                            title="Print"
                        >
                            <Printer className="h-4 w-4" /> Print
                        </button>
                    </div>
                }
            />

            {/* ---------- Summary ---------- */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard
                    index={0}
                    icon={TrendingUp}
                    label="Opening Balance"
                    value={money(stats.opening_balance)}
                    accent="secondary"
                />
                <StatCard
                    index={1}
                    icon={Banknote}
                    label="Total Income"
                    value={money(stats.total_income)}
                    accent="secondary"
                />
                <StatCard
                    index={2}
                    icon={FileText}
                    label="Total Expenses"
                    value={money(stats.total_expenses)}
                    accent="accent"
                />
                <StatCard
                    index={3}
                    icon={ChevronRight}
                    label="Net Movement"
                    value={money(stats.net_movement)}
                    accent={stats.net_movement >= 0 ? 'secondary' : 'danger'}
                />
                <StatCard
                    index={4}
                    icon={ChevronRight}
                    label="Closing Balance"
                    value={money(stats.closing_balance)}
                    accent={stats.closing_balance >= 0 ? 'secondary' : 'danger'}
                />
            </div>

            <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
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

            {/* ---------- Category breakdown ---------- */}
            <div className="mt-6 grid gap-6 sm:grid-cols-2">
                <div className="glass-card p-5 sm:p-6">
                    <h3 className="font-display text-base font-bold text-ink mb-4">Income by Category</h3>
                    {(income_by_category ?? []).length ? (
                        <DonutChart data={income_by_category} />
                    ) : (
                        <EmptyState title="No income by category" hint="Record some fee payments first." />
                    )}
                </div>
                <div className="glass-card p-5 sm:p-6">
                    <h3 className="font-display text-base font-bold text-ink mb-4">Expenses by Category</h3>
                    {(expenses_by_category ?? []).length ? (
                        <DonutChart data={expenses_by_category} />
                    ) : (
                        <EmptyState title="No expenses by category" hint="Record some expenses first." />
                    )}
                </div>
            </div>

            {/* ---------- Outstanding fees ---------- */}
            {stats.outstanding_fees > 0 && (
                <div className="mt-6 glass-card p-5 sm:p-6">
                    <h3 className="font-display text-base font-bold text-ink mb-3">Outstanding Fees</h3>
                    <div className="text-2xl font-bold text-rose-600">
                        {money(stats.outstanding_fees)} still outstanding
                    </div>
                    <p className="mt-1 text-sm text-slate-500">
                        Unpaid invoice balances across all students.
                    </p>
                </div>
            )}

            {/* ---------- Transaction breakdown ---------- */}
            <div className="mt-6 glass-card p-5 sm:p-6">
                <header className="mb-4 flex items-center justify-between">
                    <div>
                        <h3 className="font-display text-base font-bold text-ink">Transaction Breakdown</h3>
                        <p className="text-sm text-slate-500">
                            All payments and expenses for the selected period.
                        </p>
                    </div>
                </header>

                {transactions.length === 0 ? (
                    <EmptyState
                        title="No transactions in this period"
                        hint="Income and expenses appear here once recorded."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Date</th>
                                    <th className="pb-3 font-bold">Type</th>
                                    <th className="pb-3 font-bold">Category</th>
                                    <th className="pb-3 font-bold">Reference</th>
                                    <th className="pb-3 font-bold">Description</th>
                                    <th className="pb-3 font-bold text-right">Amount</th>
                                    <th className="pb-3 font-bold">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {transactions.map((t) => (
                                    <tr key={`${t.date}-${t.reference}-${t.amount}`} className="transition hover:bg-canvas/60">
                                        <td className="py-3 text-slate-600">{t.date}</td>
                                        <td className="py-3">
                                            <Badge tone={typeTone[t.type] ?? 'muted'}>{t.type}</Badge>
                                        </td>
                                        <td className="py-3 text-slate-600">{t.category}</td>
                                        <td className="py-3 font-mono text-xs text-slate-600">{t.reference ?? '—'}</td>
                                        <td className="py-3 text-slate-600 truncate max-w-xs">{t.description}</td>
                                        <td className="py-3 text-right font-semibold">
                                            {t.type === 'income' ? (
                                                <span className="text-emerald-600">+{money(t.amount)}</span>
                                            ) : (
                                                <span className="text-rose-600">−{money(t.amount)}</span>
                                            )}
                                        </td>
                                        <td className="py-3 text-slate-600">{t.recorded_by ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}

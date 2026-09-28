import { Banknote, Receipt, TrendingDown, Wallet } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, ProgressBar, StatCard } from '../../Components/ui';
import { ChartCard, DonutChart, GroupedBarChart } from '../../Components/charts';
import { BRAND } from '../../Components/ui';

/* ===========================================================================
 * ACCOUNTANT DASHBOARD — dedicated financial module
 * ======================================================================== */

const METHOD_LABELS = {
    cash: 'Cash',
    bank_transfer: 'Bank Transfer',
    mobile_money: 'Mobile Money',
    card: 'Card',
    cheque: 'Cheque',
};

const STATUS_TONES = {
    completed: 'success',
    pending: 'warning',
    failed: 'danger',
    refunded: 'muted',
};

export default function Accountant({ stats, charts, transactions, currency }) {
    const statIcons = [Banknote, Wallet, Receipt, TrendingDown];
    const statFills = ['bg-emerald-500', 'bg-amber-500', 'bg-sky-500', 'bg-purple-500'];

    // Collection progress from the doughnut slices
    const collected = charts.collection.find((c) => c.name === 'Collected')?.value ?? 0;
    const outstanding = charts.collection.find((c) => c.name === 'Outstanding')?.value ?? 0;
    const collectionRate = collected + outstanding
        ? Math.round((collected / (collected + outstanding)) * 100)
        : 0;

    const money = (value) => `${currency} ${Number(value ?? 0).toLocaleString()}`;

    return (
        <DashboardLayout
            title="Financial Management"
            subtitle="Fee collection, invoicing, receipts and expenses — accountant only."
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
            <div className="mt-6 grid gap-6 xl:grid-cols-5">
                <ChartCard
                    title="Monthly Income vs Expenses"
                    subtitle="Last 6 months of cash flow"
                    className="xl:col-span-3"
                >
                    <GroupedBarChart
                        data={charts.incomeVsExpense}
                        xKey="month"
                        unit={currency}
                            series={[
                                { key: 'income', color: BRAND.primary },
                                { key: 'expenses', color: BRAND.secondary },
                            ]}
                    />
                </ChartCard>

                <ChartCard
                    title="Fee Collection"
                    subtitle="Collected vs outstanding"
                    className="xl:col-span-2"
                    delay={0.05}
                >
                    <DonutChart data={charts.collection} />
                    <div className="mt-3">
                        <ProgressBar
                            value={collectionRate}
                            color="bg-primary"
                            label="Overall collection rate"
                        />
                    </div>
                </ChartCard>
            </div>

            {/* ---------- Recent transactions ------------------------------ */}
            <Card
                title="Recent Transactions"
                subtitle="Latest payments received from parents"
                className="mt-6"
                delay={0.1}
                action={<Badge tone="success">{transactions?.length ?? 0} records</Badge>}
            >
                {/* Desktop table */}
                <div className="hidden overflow-x-auto md:block">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                <th className="pb-3 font-bold">Student</th>
                                <th className="pb-3 font-bold">Amount</th>
                                <th className="pb-3 font-bold">Receipt No.</th>
                                <th className="pb-3 font-bold">Method</th>
                                <th className="pb-3 font-bold">Status</th>
                                <th className="pb-3 font-bold">Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {transactions?.map((txn) => (
                                <tr key={txn.id} className="transition hover:bg-canvas">
                                    <td className="py-3 font-bold text-ink">
                                        {txn.student
                                            ? `${txn.student.first_name} ${txn.student.last_name}`
                                            : 'Unknown'}
                                        <span className="block text-xs font-semibold text-slate-400">
                                            {txn.student?.reg_no}
                                        </span>
                                    </td>
                                    <td className="py-3 font-bold text-primary">
                                        {currency} {Number(txn.amount).toLocaleString()}
                                    </td>
                                    <td className="py-3 font-mono text-xs text-slate-600">
                                        {txn.receipt_number}
                                    </td>
                                    <td className="py-3 text-slate-600">
                                        {METHOD_LABELS[txn.method] ?? txn.method}
                                    </td>
                                    <td className="py-3">
                                        <Badge tone={STATUS_TONES[txn.status] ?? 'muted'}>
                                            {txn.status}
                                        </Badge>
                                    </td>
                                    <td className="py-3 text-xs text-slate-500">
                                        {new Date(txn.paid_at).toLocaleDateString('en-GB')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Mobile cards */}
                <ul className="space-y-3 md:hidden">
                    {transactions?.map((txn) => (
                        <li key={txn.id} className="rounded-xl bg-canvas p-4">
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-bold text-ink">
                                        {txn.student
                                            ? `${txn.student.first_name} ${txn.student.last_name}`
                                            : 'Unknown'}
                                    </p>
                                    <p className="truncate text-xs font-mono text-slate-500">
                                        {txn.receipt_number}
                                    </p>
                                </div>
                                <Badge tone={STATUS_TONES[txn.status] ?? 'muted'}>{txn.status}</Badge>
                            </div>
                            <div className="mt-2 flex items-center justify-between">
                                <span className="font-display text-lg font-extrabold text-primary">
                                    {currency} {Number(txn.amount).toLocaleString()}
                                </span>
                                <span className="text-xs font-semibold text-slate-500">
                                    {METHOD_LABELS[txn.method] ?? txn.method} ·{' '}
                                    {new Date(txn.paid_at).toLocaleDateString('en-GB')}
                                </span>
                            </div>
                        </li>
                    ))}
                </ul>
            </Card>


        </DashboardLayout>
    );
}

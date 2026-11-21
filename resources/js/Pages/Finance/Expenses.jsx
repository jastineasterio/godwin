import { useState } from 'react';
import { ChevronRight, FileText, Plus, Search } from 'lucide-react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState, StatCard } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { PageHeader, PrimaryAction } from '../../Components/page';

const money = (value) => `TZS ${Number(value ?? 0).toLocaleString()}`;

const CATEGORY_TONE = {
    salaries: 'danger',
    utilities: 'warning',
    maintenance: 'warning',
    supplies: 'secondary',
    food: 'secondary',
    transport: 'secondary',
    other: 'muted',
};

export default function Expenses({ expenses, categories, filters, total }) {
    const [deleting, setDeleting] = useState(null);

    const apply = (extra = {}) =>
        router.get(
            '/expenses',
            {
                search: filters.search ?? '',
                category: filters.category ?? '',
                from: filters.from ?? '',
                to: filters.to ?? '',
                ...extra,
            },
            { preserveState: true, replace: true }
        );

    return (
        <DashboardLayout
            title="Expenses"
            subtitle="School operating expenses and spending records"
        >
            <PageHeader
                title="Expenses"
                subtitle={`${expenses.total ?? 0} expense(s) · ${money(total)} spent`}
                action={<PrimaryAction href="/expenses/create">Record Expense</PrimaryAction>}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    index={0}
                    icon={FileText}
                    label="Total Expenses"
                    value={money(total)}
                    accent="accent"
                />
                <StatCard
                    index={1}
                    icon={Plus}
                    label="This Period"
                    value={expenses.total ?? 0}
                    accent="primary"
                />
                <StatCard
                    index={2}
                    icon={ChevronRight}
                    label="Categories Used"
                    value={categories.length}
                    accent="secondary"
                />
            </div>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply();
                    }}
                    className="relative flex-1"
                >
                    <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        className="input pl-10"
                        defaultValue={filters.search ?? ''}
                        onChange={(e) => apply({ search: e.target.value })}
                        placeholder="Search by title, reference or description…"
                    />
                </form>
                <select
                    className="input sm:w-48"
                    value={filters.category ?? ''}
                    onChange={(e) => apply({ category: e.target.value })}
                >
                    <option value="">All categories</option>
                    {categories.map((c) => (
                        <option key={c.value} value={c.value}>
                            {c.label}
                        </option>
                    ))}
                </select>
                <div className="flex gap-2 sm:w-auto">
                    <input
                        type="date"
                        value={filters.from ?? ''}
                        onChange={(e) => apply({ from: e.target.value })}
                        className="input sm:w-48"
                    />
                    <input
                        type="date"
                        value={filters.to ?? ''}
                        onChange={(e) => apply({ to: e.target.value })}
                        className="input sm:w-48"
                    />
                </div>
            </div>

            <div className="mt-4 glass-card p-4 sm:p-5">
                {expenses.data.length === 0 ? (
                    <EmptyState
                        title="No expenses found"
                        hint="Record an expense to begin tracking school spending."
                    />
                ) : (
                    <>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        <th className="pb-3 font-bold">Title</th>
                                        <th className="pb-3 font-bold">Category</th>
                                        <th className="pb-3 font-bold text-center">Date</th>
                                        <th className="pb-3 font-bold text-right">Amount</th>
                                        <th className="pb-3 font-bold">Reference</th>
                                        <th className="pb-3 font-bold">Recorded By</th>
                                        <th className="pb-3 font-bold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {expenses.data.map((e) => (
                                        <tr key={e.id} className="transition hover:bg-canvas/60">
                                            <td className="py-3 font-bold text-ink">
                                                {e.title}
                                                <span className="block text-[11px] text-slate-400 mt-0.5">
                                                    {e.description ?? 'No description'}
                                                </span>
                                            </td>
                                            <td className="py-3">
                                                <Badge tone={CATEGORY_TONE[e.category] ?? 'muted'}>
                                                    {e.category_label}
                                                </Badge>
                                            </td>
                                            <td className="py-3 text-center text-slate-600">
                                                {e.incurred_on}
                                            </td>
                                            <td className="py-3 text-right font-semibold text-rose-600">
                                                {money(e.amount)}
                                            </td>
                                            <td className="py-3 font-mono text-xs text-slate-600">
                                                {e.reference ?? '—'}
                                            </td>
                                            <td className="py-3 text-slate-600">
                                                {e.recorded_by ?? '—'}
                                            </td>
                                            <td className="py-3 text-right">
                                                <Link
                                                    href={`/expenses/${e.id}/edit`}
                                                    className="text-xs text-primary hover:underline mr-2"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() => setDeleting(e)}
                                                    className="text-xs text-rose-600 hover:underline"
                                                >
                                                    Void
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="space-y-3 sm:hidden">
                            {expenses.data.map((e) => (
                                <li key={e.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-bold text-ink">{e.title}</p>
                                            <p className="text-xs text-slate-500">{e.description ?? 'No description'}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-bold text-rose-600">{money(e.amount)}</p>
                                            <p className="text-[10px] text-slate-500">{e.incurred_on}</p>
                                        </div>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between">
                                        <Badge tone={CATEGORY_TONE[e.category] ?? 'muted'}>
                                            {e.category_label}
                                        </Badge>
                                        <span className="text-[10px] text-slate-400">
                                            by {e.recorded_by ?? '—'}
                                        </span>
                                    </div>
                                    <div className="mt-3 flex gap-2">
                                        <Link
                                            href={`/expenses/${e.id}/edit`}
                                            className="flex-1 btn-ghost py-1.5 text-xs text-center"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => setDeleting(e)}
                                            className="flex-1 btn-ghost py-1.5 text-xs text-center text-rose-600"
                                        >
                                            Void
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="mt-4">
                            {expenses.meta && (
                                <div className="flex items-center justify-between gap-1">
                                    <button
                                        onClick={() => {
                                            if (expenses.meta.current_page > 1)
                                                apply({ page: expenses.meta.current_page - 1 });
                                        }}
                                        disabled={expenses.meta.current_page <= 1}
                                        className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                                    </button>
                                    <span className="px-3 text-xs font-bold text-ink">
                                        {expenses.meta.current_page} / {expenses.meta.last_page}
                                    </span>
                                    <button
                                        onClick={() => {
                                            if (expenses.meta.current_page < expenses.meta.last_page)
                                                apply({ page: expenses.meta.current_page + 1 });
                                        }}
                                        disabled={expenses.meta.current_page >= expenses.meta.last_page}
                                        className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                                    </button>
                                </div>
                            )}
                        </div>
                    </>
                )}
            </div>

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    router.delete(`/expenses/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Void expense?"
                message={`"${deleting?.title ?? ''}" will be voided (soft-deleted with audit trail).`}
                confirmLabel="Void"
            />
        </DashboardLayout>
    );
}

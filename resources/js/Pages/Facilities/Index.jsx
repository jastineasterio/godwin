import { useState } from 'react';
import { AlertTriangle, ChevronRight, Package, Search, TrendingUp } from 'lucide-react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState, StatCard } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { PageHeader, PrimaryAction } from '../../Components/page';

const money = (value) => `TZS ${Number(value ?? 0).toLocaleString()}`;

const CATEGORY_LABEL = {
    furniture: 'Furniture', electronics: 'Electronics', teaching: 'Teaching Materials',
    sports: 'Sports Equipment', kitchen: 'Kitchen Equipment', bedding: 'Beds & Mattresses',
    toys: 'Toys', other: 'Other',
};

const CONDITION_TONE = { new: 'success', good: 'secondary', fair: 'warning', damaged: 'danger', missing: 'danger' };
const STATUS_TONE = { active: 'success', in_use: 'secondary', maintenance: 'warning', disposed: 'muted', missing: 'danger' };

export default function FacilitiesIndex({ assets, summary, filters, categories, conditions, statuses }) {
    const [deleting, setDeleting] = useState(null);

    const apply = (extra = {}) =>
        router.get(
            '/facilities',
            {
                search: filters.search ?? '',
                category: filters.category ?? '',
                condition: filters.condition ?? '',
                status: filters.status ?? '',
                location: filters.location ?? '',
                ...extra,
            },
            { preserveState: true, replace: true }
        );

    return (
        <DashboardLayout
            title="School Facilities & Assets"
            subtitle="Inventory of school property and teaching materials"
        >
            <PageHeader
                title="School Facilities & Assets"
                subtitle={`${assets.total ?? 0} asset(s) · ${summary.total_quantity} units registered`}
                action={<PrimaryAction href="/facilities/create">Register Asset</PrimaryAction>}
            />

            {/* ---------- KPI cards ---------- */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    index={0}
                    icon={Package}
                    label="Total Assets"
                    value={summary.total_assets}
                    accent="primary"
                />
                <StatCard
                    index={1}
                    icon={TrendingUp}
                    label="Total Units"
                    value={summary.total_quantity}
                    accent="secondary"
                />
                <StatCard
                    index={2}
                    icon={AlertTriangle}
                    label="Damaged"
                    value={summary.damaged}
                    accent={summary.damaged ? 'danger' : 'secondary'}
                />
                <StatCard
                    index={3}
                    icon={ChevronRight}
                    label="Missing"
                    value={summary.missing}
                    accent={summary.missing ? 'danger' : 'secondary'}
                />
            </div>

            <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
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
                        placeholder="Search by name, code or description…"
                    />
                </form>
                <select className="input sm:w-48" value={filters.category ?? ''}
                    onChange={(e) => apply({ category: e.target.value })}>
                    <option value="">All categories</option>
                    {categories.map((c) => (
                        <option key={c.value} value={c.value}>{c.label}</option>
                    ))}
                </select>
                <select className="input sm:w-48" value={filters.condition ?? ''}
                    onChange={(e) => apply({ condition: e.target.value })}>
                    <option value="">All conditions</option>
                    {conditions.map((c) => (
                        <option key={c.value} value={c.value}>{c.label}</option>
                    ))}
                </select>
                <select className="input sm:w-48" value={filters.status ?? ''}
                    onChange={(e) => apply({ status: e.target.value })}>
                    <option value="">All statuses</option>
                    {statuses.map((s) => (
                        <option key={s.value} value={s.value}>{s.label}</option>
                    ))}
                </select>
            </div>

            <div className="mt-4 glass-card p-4 sm:p-5">
                {assets.data.length === 0 ? (
                    <EmptyState
                        title="No assets found"
                        hint="Register an asset to begin tracking school property."
                    />
                ) : (
                    <>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        <th className="pb-3 font-bold">Asset</th>
                                        <th className="pb-3 font-bold">Code</th>
                                        <th className="pb-3 font-bold">Category</th>
                                        <th className="pb-3 font-bold text-center">Qty</th>
                                        <th className="pb-3 font-bold text-center">Condition</th>
                                        <th className="pb-3 font-bold text-center">Status</th>
                                        <th className="pb-3 font-bold text-right">Value</th>
                                        <th className="pb-3 font-bold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {assets.data.map((a) => (
                                        <tr key={a.id} className="transition hover:bg-canvas/60">
                                            <td className="py-3 font-bold text-ink">
                                                {a.name}
                                                <span className="block text-[11px] text-slate-400 mt-0.5">
                                                    {a.location ?? 'No location'}
                                                </span>
                                            </td>
                                            <td className="py-3 font-mono text-xs">{a.asset_code ?? '—'}</td>
                                            <td className="py-3 text-slate-600 capitalize">{CATEGORY_LABEL[a.category] ?? a.category}</td>
                                            <td className="py-3 text-center">{a.quantity}</td>
                                            <td className="py-3 text-center">
                                                <Badge tone={CONDITION_TONE[a.condition] ?? 'muted'}>
                                                    {a.condition}
                                                </Badge>
                                            </td>
                                            <td className="py-3 text-center">
                                                <Badge tone={STATUS_TONE[a.status] ?? 'muted'}>
                                                    {a.status.replace('_', ' ')}
                                                </Badge>
                                            </td>
                                            <td className="py-3 text-right text-slate-600">
                                                {a.purchase_price ? money(a.purchase_price * a.quantity) : '—'}
                                            </td>
                                            <td className="py-3 text-right">
                                                <Link
                                                    href={`/facilities/${a.id}`}
                                                    className="text-xs text-primary hover:underline mr-2"
                                                >
                                                    View
                                                </Link>
                                                <Link
                                                    href={`/facilities/${a.id}/edit`}
                                                    className="text-xs text-primary hover:underline mr-2"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() => setDeleting(a)}
                                                    className="text-xs text-rose-600 hover:underline"
                                                >
                                                    Dispose
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="space-y-3 sm:hidden">
                            {assets.data.map((a) => (
                                <li key={a.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-bold text-ink">{a.name}</p>
                                            <p className="text-xs text-slate-500">{a.asset_code ?? 'No code'} · {CATEGORY_LABEL[a.category] ?? a.category}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-bold text-slate-600">{a.quantity} units</p>
                                            <Badge tone={CONDITION_TONE[a.condition] ?? 'muted'} className="text-[10px]">
                                                {a.condition}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between">
                                        <Badge tone={STATUS_TONE[a.status] ?? 'muted'} className="text-[10px]">
                                            {a.status.replace('_', ' ')}
                                        </Badge>
                                        <span className="text-[10px] text-slate-400">{a.location ?? '—'}</span>
                                    </div>
                                    <div className="mt-3 flex gap-2">
                                        <Link
                                            href={`/facilities/${a.id}`}
                                            className="flex-1 btn-ghost py-1.5 text-xs text-center"
                                        >
                                            View
                                        </Link>
                                        <Link
                                            href={`/facilities/${a.id}/edit`}
                                            className="flex-1 btn-ghost py-1.5 text-xs text-center"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => setDeleting(a)}
                                            className="flex-1 btn-ghost py-1.5 text-xs text-center text-rose-600"
                                        >
                                            Dispose
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="mt-4">
                            {assets.meta && (
                                <div className="flex items-center justify-between gap-1">
                                    <button
                                        onClick={() => {
                                            if (assets.meta.current_page > 1)
                                                apply({ page: assets.meta.current_page - 1 });
                                        }}
                                        disabled={assets.meta.current_page <= 1}
                                        className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                                    </button>
                                    <span className="px-3 text-xs font-bold text-ink">
                                        {assets.meta.current_page} / {assets.meta.last_page}
                                    </span>
                                    <button
                                        onClick={() => {
                                            if (assets.meta.current_page < assets.meta.last_page)
                                                apply({ page: assets.meta.current_page + 1 });
                                        }}
                                        disabled={assets.meta.current_page >= assets.meta.last_page}
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
                    router.delete(`/facilities/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Dispose asset?"
                message={`"${deleting?.name ?? ''}" will be marked as disposed (history preserved).`}
                confirmLabel="Dispose"
            />
        </DashboardLayout>
    );
}

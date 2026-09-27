import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState, ProgressBar, StatCard } from '../../Components/ui';
import { PageHeader, Pagination, PrimaryAction } from '../../Components/page';

const TONES = {
    paid: 'success', partial: 'warning', unpaid: 'danger',
    overdue: 'danger', void: 'muted',
};

const money = (value) => `TZS ${Number(value ?? 0).toLocaleString()}`;

export default function Invoices({ invoices, filters, statuses, summary }) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (extra = {}) =>
        router.get(
            '/invoices',
            { search, status: filters.status ?? '', ...extra },
            { preserveState: true, replace: true }
        );

    return (
        <DashboardLayout title="Finance" subtitle="Invoicing, payments and receipts.">
            <PageHeader
                title="Invoices"
                subtitle={`${invoices.total ?? 0} invoice(s)`}
                action={<PrimaryAction href="/invoices/create">Generate Invoice</PrimaryAction>}
            />

            {/* ---------- Collection summary ---------- */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard index={0} label="Total Billed" value={money(summary.expected)} accent="primary" />
                <StatCard index={1} label="Total Collected" value={money(summary.paid)} accent="secondary" />
                <StatCard index={2} label="Outstanding" value={money(summary.outstanding)} accent="accent" />
            </div>

            <div className="mt-5">
                <ProgressBar
                    value={summary.expected ? Math.round((summary.paid / summary.expected) * 100) : 0}
                    color="bg-primary"
                    label="Overall collection rate"
                />
            </div>

            {/* ---------- Filters ---------- */}
            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                <form onSubmit={(e) => { e.preventDefault(); apply(); }} className="flex-1">
                    <input
                        className="input"
                        placeholder="Search by student name or reg. number…"
                        defaultValue={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </form>
                <select className="input sm:w-48" value={filters.status ?? ''}
                    onChange={(e) => apply({ status: e.target.value })}>
                    <option value="">All statuses</option>
                    {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
            </div>

            <div className="mt-4 glass-card p-4 sm:p-5">
                {invoices.data.length === 0 ? (
                    <EmptyState title="No invoices found" hint="Generate an invoice to start billing a parent." />
                ) : (
                    <>
                        {/* ---------------- Desktop table ---------------- */}
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        {['Invoice', 'Student', 'Total', 'Paid', 'Balance', 'Status', ''].map((h) => (
                                            <th key={h} className="whitespace-nowrap pb-3 font-bold">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {invoices.data.map((invoice) => (
                                        <tr key={invoice.id} className="transition hover:bg-canvas/60">
                                            <td className="py-3">
                                                <Link href={`/invoices/${invoice.id}`}
                                                    className="font-mono text-xs font-bold text-primary hover:underline">
                                                    {invoice.invoice_number}
                                                </Link>
                                                <span className="block text-[11px] text-slate-400">Due {invoice.due_date}</span>
                                            </td>
                                            <td className="py-3 font-bold text-ink">{invoice.student}</td>
                                            <td className="py-3 font-bold text-ink">{money(invoice.total_amount)}</td>
                                            <td className="py-3 text-emerald-600">{money(invoice.amount_paid)}</td>
                                            <td className="py-3 font-bold text-rose-600">{money(invoice.balance)}</td>
                                            <td className="py-3">
                                                <Badge tone={TONES[invoice.status] ?? 'muted'}>{invoice.status}</Badge>
                                            </td>
                                            <td className="py-3">
                                                <Link href={`/invoices/${invoice.id}`} title="Open invoice"
                                                    className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-primary">
                                                    <Eye className="h-4 w-4" />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* ---------------- Mobile cards ---------------- */}
                        <ul className="space-y-3 sm:hidden">
                            {invoices.data.map((invoice) => (
                                <li key={invoice.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-mono text-xs font-bold text-primary">{invoice.invoice_number}</p>
                                            <p className="truncate font-bold text-ink">{invoice.student}</p>
                                        </div>
                                        <Badge tone={TONES[invoice.status] ?? 'muted'}>{invoice.status}</Badge>
                                    </div>
                                    <div className="mt-2 grid grid-cols-3 gap-2 text-center text-xs">
                                        <div>
                                            <p className="text-slate-400">Total</p>
                                            <p className="font-bold text-ink">{money(invoice.total_amount)}</p>
                                        </div>
                                        <div>
                                            <p className="text-slate-400">Paid</p>
                                            <p className="font-bold text-emerald-600">{money(invoice.amount_paid)}</p>
                                        </div>
                                        <div>
                                            <p className="text-slate-400">Balance</p>
                                            <p className="font-bold text-rose-600">{money(invoice.balance)}</p>
                                        </div>
                                    </div>
                                    <Link href={`/invoices/${invoice.id}`} className="btn-ghost mt-3 w-full py-1.5 text-xs">
                                        Open invoice
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <Pagination meta={invoices.meta} onPage={(page) => apply({ page })} />
            </div>
        </DashboardLayout>
    );
}
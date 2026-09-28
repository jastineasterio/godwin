import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Eye, Pencil, Printer, Trash2 } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { FilterBar, PageHeader, Pagination, PrimaryAction } from '../../Components/page';

const TONES = {
    active: 'success', transferred: 'warning', graduated: 'secondary',
    withdrawn: 'muted', inactive: 'muted',
};

export default function StudentsIndex({ students, classes, filters, statuses }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleting, setDeleting] = useState(null);

    // Filters run server-side so the result set stays paginated
    const apply = (extra = {}) => {
        router.get(
            '/students',
            { search, class_id: filters.class_id ?? '', status: filters.status ?? '', ...extra },
            { preserveState: true, replace: true }
        );
    };

    return (
        <DashboardLayout title="Student Management" subtitle="Registry of every enrolled child.">
            <PageHeader
                title="All Students"
                subtitle={`${students.total ?? 0} record(s) found`}
                action={<PrimaryAction href="/students/create">Register Student</PrimaryAction>}
            />

            <FilterBar
                value={search}
                onChange={setSearch}
                onSubmit={() => apply()}
                placeholder="Search name or reg. number…"
            >
                <select
                    className="input w-auto min-w-[150px]"
                    value={filters.class_id ?? ''}
                    onChange={(e) => apply({ class_id: e.target.value })}
                >
                    <option value="">All classes</option>
                    {classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>

                <select
                    className="input w-auto min-w-[140px]"
                    value={filters.status ?? ''}
                    onChange={(e) => apply({ status: e.target.value })}
                >
                    <option value="">All statuses</option>
                    {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
            </FilterBar>

            <div className="glass-card p-4 sm:p-5">
                {students.data.length === 0 ? (
                    <EmptyState title="No students match your filters" hint="Try another search term or clear the filters." />
                ) : (
                    <>
                        {/* ---------------- Desktop table ---------------- */}
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        {['Student', 'Reg No.', 'Class', 'Gender', 'Status', ''].map((h) => (
                                            <th key={h} className="whitespace-nowrap pb-3 font-bold">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {students.data.map((s) => (
                                        <Row key={s.id} student={s} onDelete={() => setDeleting(s)} />
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* ---------------- Mobile cards ---------------- */}
                        <ul className="space-y-3 sm:hidden">
                            {students.data.map((s) => (
                                <li key={s.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate font-bold text-ink">{s.name}</p>
                                            <p className="font-mono text-xs text-slate-500">{s.reg_no}</p>
                                        </div>
                                        <Badge tone={TONES[s.status] ?? 'muted'}>{s.status}</Badge>
                                    </div>
                                    <p className="mt-2 text-sm text-slate-600">
                                        {s.class ?? 'No class'} · <span className="capitalize">{s.gender}</span>
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Link href={`/students/${s.id}`} className="btn-ghost px-3 py-1.5 text-xs">
                                            <Eye className="h-3.5 w-3.5" /> Profile
                                        </Link>
                                        <Link href={`/students/${s.id}/edit`} className="btn-ghost px-3 py-1.5 text-xs">
                                            <Pencil className="h-3.5 w-3.5" /> Edit
                                        </Link>
                                        <a href={`/students/${s.id}/report-card`} target="_blank" rel="noreferrer"
                                            className="btn-ghost px-3 py-1.5 text-xs">
                                            <Printer className="h-3.5 w-3.5" /> Report
                                        </a>
                                        <button type="button" onClick={() => setDeleting(s)}
                                            className="btn bg-rose-50 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-100">
                                            <Trash2 className="h-3.5 w-3.5" /> Remove
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <Pagination meta={students.meta} onPage={(page) => apply({ page })} />
            </div>

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    router.delete(`/students/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Remove student record?"
                message={`${deleting?.name ?? 'This student'} will be removed from the registry. The record is archived, not erased.`}
                confirmLabel="Remove Student"
            />
        </DashboardLayout>
    );
}

/** Desktop table row with quick actions. */
function Row({ student, onDelete }) {
    const btn = 'grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition';

    return (
        <tr className="transition hover:bg-canvas/60">
            <td className="py-3">
                <Link href={`/students/${student.id}`} className="font-bold text-ink hover:text-primary">
                    {student.name}
                </Link>
            </td>
            <td className="py-3 font-mono text-xs text-slate-500">{student.reg_no}</td>
            <td className="py-3 text-slate-600">{student.class ?? '—'}</td>
            <td className="py-3 capitalize text-slate-600">{student.gender}</td>
            <td className="py-3">
                <Badge tone={TONES[student.status] ?? 'muted'}>{student.status}</Badge>
            </td>
            <td className="py-3">
                <div className="flex items-center justify-end gap-1">
                    <a href={`/students/${student.id}/report-card`} target="_blank" rel="noreferrer" title="Report"
                        className={`${btn} hover:bg-slate-100 hover:text-secondary`}>
                        <Printer className="h-4 w-4" />
                    </a>
                    <Link href={`/students/${student.id}/edit`} title="Edit" className={`${btn} hover:bg-slate-100 hover:text-primary`}>
                        <Pencil className="h-4 w-4" />
                    </Link>
                    <button type="button" onClick={onDelete} title="Remove" className={`${btn} hover:bg-rose-50 hover:text-rose-600`}>
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            </td>
        </tr>
    );
}

import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Printer, Trash2 } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { PageHeader, Pagination, PrimaryAction } from '../../Components/page';

const TONES = {
    exam: 'primary', test: 'secondary', milestone: 'accent',
    homework: 'muted', project: 'secondary', participation: 'muted',
};

const gradeTone = (pct) => (pct >= 75 ? 'success' : pct >= 50 ? 'warning' : 'danger');

export default function AssessmentsIndex({ assessments, classes, filters, types }) {
    const [deleting, setDeleting] = useState(null);

    const apply = (extra = {}) =>
        router.get(
            '/assessments',
            { class_id: filters.class_id ?? '', type: filters.type ?? '', ...extra },
            { preserveState: true, replace: true }
        );

    return (
        <DashboardLayout title="Academics" subtitle="Assessments, tests and developmental milestones.">
            <PageHeader
                title="Assessments"
                subtitle={`${assessments.total ?? 0} record(s)`}
                action={<PrimaryAction href="/assessments/create">Record Assessment</PrimaryAction>}
            />

            <div className="mb-4 flex flex-wrap gap-2">
                <select className="input w-auto min-w-[150px]" value={filters.class_id ?? ''}
                    onChange={(e) => apply({ class_id: e.target.value })}>
                    <option value="">All my classes</option>
                    {classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                <select className="input w-auto min-w-[140px]" value={filters.type ?? ''}
                    onChange={(e) => apply({ type: e.target.value })}>
                    <option value="">All types</option>
                    {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                </select>
            </div>

            <div className="glass-card p-4 sm:p-5">
                {assessments.data.length === 0 ? (
                    <EmptyState title="No assessments recorded yet" hint="Record a test, exam or milestone to get started." />
                ) : (
                    <>
                        {/* ---------------- Desktop ---------------- */}
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        {['Title', 'Student', 'Subject', 'Type', 'Score', 'Date', ''].map((h) => (
                                            <th key={h} className="whitespace-nowrap pb-3 font-bold">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {assessments.data.map((a) => (
                                        <tr key={a.id} className="transition hover:bg-canvas/60">
                                            <td className="py-3 font-bold text-ink">{a.title}</td>
                                            <td className="py-3 text-slate-600">{a.student}</td>
                                            <td className="py-3 text-slate-600">{a.subject ?? '—'}</td>
                                            <td className="py-3"><Badge tone={TONES[a.type] ?? 'muted'}>{a.type}</Badge></td>
                                            <td className="py-3">
                                                <span className="font-bold text-ink">{a.score}/{a.max_score}</span>
                                                <span className="ml-2"><Badge tone={gradeTone(a.percentage)}>{a.percentage}%</Badge></span>
                                            </td>
                                            <td className="py-3 text-xs text-slate-500">{a.obtained_at}</td>
                                            <td className="py-3"><RowActions item={a} onDelete={() => setDeleting(a)} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* ---------------- Mobile ---------------- */}
                        <ul className="space-y-3 sm:hidden">
                            {assessments.data.map((a) => (
                                <li key={a.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate font-bold text-ink">{a.title}</p>
                                            <p className="text-xs text-slate-500">{a.student} · {a.subject ?? 'General'}</p>
                                        </div>
                                        <Badge tone={gradeTone(a.percentage)}>{a.percentage}%</Badge>
                                    </div>
                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                        <Badge tone={TONES[a.type] ?? 'muted'}>{a.type}</Badge>
                                        <span className="text-xs text-slate-500">
                                            {a.score}/{a.max_score} · {a.obtained_at}
                                        </span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Link href={`/assessments/${a.id}/edit`} className="btn-ghost px-3 py-1.5 text-xs">Edit</Link>
                                        <a href={`/students/${a.student_id}/report-card`} target="_blank" rel="noreferrer"
                                            className="btn-ghost px-3 py-1.5 text-xs">Report</a>
                                        <button type="button" onClick={() => setDeleting(a)}
                                            className="btn bg-rose-50 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-100">
                                            Delete
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <Pagination meta={assessments.meta} onPage={(page) => apply({ page })} />
            </div>

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    router.delete(`/assessments/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Delete assessment?"
                message={`"${deleting?.title ?? ''}" will be removed from the academic record.`}
                confirmLabel="Delete"
            />
        </DashboardLayout>
    );
}

/** Desktop row actions: printable report, edit, delete. */
function RowActions({ item, onDelete }) {
    const btn = 'grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition';

    return (
        <div className="flex items-center justify-end gap-1">
            {item.student_id && (
                <a href={`/students/${item.student_id}/report-card`} target="_blank" rel="noreferrer" title="Report"
                    className={`${btn} hover:bg-slate-100 hover:text-secondary`}>
                    <Printer className="h-4 w-4" />
                </a>
            )}
            <Link href={`/assessments/${item.id}/edit`} title="Edit" className={`${btn} hover:bg-slate-100 hover:text-primary`}>
                <Pencil className="h-4 w-4" />
            </Link>
            <button type="button" onClick={onDelete} title="Delete" className={`${btn} hover:bg-rose-50 hover:text-rose-600`}>
                <Trash2 className="h-4 w-4" />
            </button>
        </div>
    );
}
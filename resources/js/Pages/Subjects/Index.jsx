import { useState } from 'react';
import { BookOpen, ChevronRight, Plus, Search, UserRound } from 'lucide-react';
import { Link, router, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState, StatCard } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { PageHeader, PrimaryAction } from '../../Components/page';

export default function SubjectsIndex({ subjects, classes, filters }) {
    const [deleting, setDeleting] = useState(null);

    const apply = (extra = {}) =>
        router.get(
            '/subjects',
            {
                search: filters.search ?? '',
                class_id: filters.class_id ?? '',
                status: filters.status ?? '',
                ...extra,
            },
            { preserveState: true, replace: true }
        );

    const totalClasses = classes?.length ?? 0;
    const activeCount = subjects.data?.filter((s) => s.is_active).length ?? 0;

    return (
        <DashboardLayout
            title="Subjects Management"
            subtitle="Register subjects and assign them to classes"
        >
            <PageHeader
                title="Subjects Management"
                subtitle={`${subjects.total ?? 0} subject(s) across ${totalClasses} class(es)`}
                action={<PrimaryAction href="/subjects/create">Register Subject</PrimaryAction>}
            />

            {/* ---------- KPI cards ---------- */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    index={0}
                    label="Total Subjects"
                    value={subjects.total ?? 0}
                    accent="primary"
                />
                <StatCard
                    index={1}
                    label="Active Subjects"
                    value={activeCount}
                    accent="secondary"
                    hint="Eligible for teaching and exams"
                />
                <StatCard
                    index={2}
                    label="Classes Served"
                    value={totalClasses}
                    accent="accent"
                />
            </div>

            {/* ---------- Filters ---------- */}
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
                        placeholder="Search by subject name or code…"
                    />
                </form>
                <select
                    className="input sm:w-48"
                    value={filters.class_id ?? ''}
                    onChange={(e) => apply({ class_id: e.target.value })}
                >
                    <option value="">All classes</option>
                    {classes.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name} ({c.code}) — {c.level}
                        </option>
                    ))}
                </select>
                <select
                    className="input sm:w-48"
                    value={filters.status ?? ''}
                    onChange={(e) => apply({ status: e.target.value })}
                >
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div className="mt-4 glass-card p-4 sm:p-5">
                {subjects.data.length === 0 ? (
                    <EmptyState
                        title="No subjects found"
                        hint="Register a subject to begin assigning it to classes."
                    />
                ) : (
                    <>
                        {/* Desktop table */}
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        <th className="pb-3 font-bold">Subject</th>
                                        <th className="pb-3 font-bold">Code</th>
                                        <th className="pb-3 font-bold">Classes</th>
                                        <th className="pb-3 font-bold text-center">Status</th>
                                        <th className="pb-3 font-bold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {subjects.data.map((s) => (
                                        <tr key={s.id} className="transition hover:bg-canvas/60">
                                            <td className="py-3 font-bold text-ink">
                                                {s.name}
                                                <span className="block text-[11px] text-slate-400 mt-0.5">
                                                    {s.description ?? 'No description'}
                                                </span>
                                            </td>
                                            <td className="py-3 font-mono text-xs">
                                                {s.code ?? '—'}
                                            </td>
                                            <td className="py-3 text-slate-600">
                                                {s.classes?.length ? (
                                                    <ul className="list-disc list-inside text-xs space-y-0.5">
                                                        {s.classes.map((c) => (
                                                            <li key={c.id}>
                                                                {c.name} ({c.code})
                                                            </li>
                                                        ))}
                                                    </ul>
                                                ) : (
                                                    <span className="text-slate-400">Not assigned</span>
                                                )}
                                            </td>
                                            <td className="py-3 text-center">
                                                <Badge
                                                    tone={s.is_active ? 'success' : 'muted'}
                                                >
                                                    {s.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="py-3 text-right">
                                                <Link
                                                    href={`/subjects/${s.id}/edit`}
                                                    className="text-xs text-primary hover:underline mr-2"
                                                >
                                                    Edit
                                                </Link>
                                                {s.class_count === 0 && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setDeleting(s)}
                                                        className="text-xs text-rose-600 hover:underline"
                                                    >
                                                        Delete
                                                    </button>
                                                )}
                                                {s.class_count > 0 && (
                                                    <span className="text-xs text-slate-400">
                                                        In use
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Mobile cards */}
                        <ul className="space-y-3 sm:hidden">
                            {subjects.data.map((s) => (
                                <li key={s.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-bold text-ink">{s.name}</p>
                                            <p className="text-xs text-slate-400">{s.code ?? 'No code'}</p>
                                        </div>
                                        <Badge tone={s.is_active ? 'success' : 'muted'}>
                                            {s.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-xs text-slate-500">
                                        {s.description ?? 'No description'}
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <Link
                                            href={`/subjects/${s.id}/edit`}
                                            className="flex-1 btn-ghost py-1.5 text-xs text-center"
                                        >
                                            Edit
                                        </Link>
                                        {s.class_count === 0 && (
                                            <button
                                                type="button"
                                                onClick={() => setDeleting(s)}
                                                className="flex-1 btn-ghost py-1.5 text-xs text-center text-rose-600"
                                            >
                                                Delete
                                            </button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="mt-4">
                            {subjects.meta && (
                                <div className="flex items-center justify-between gap-1">
                                    <button
                                        onClick={() => {
                                            if (subjects.meta.current_page > 1) apply({ page: subjects.meta.current_page - 1 });
                                        }}
                                        disabled={subjects.meta.current_page <= 1}
                                        className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                                    </button>
                                    <span className="px-3 text-xs font-bold text-ink">
                                        {subjects.meta.current_page} / {subjects.meta.last_page}
                                    </span>
                                    <button
                                        onClick={() => {
                                            if (subjects.meta.current_page < subjects.meta.last_page) apply({ page: subjects.meta.current_page + 1 });
                                        }}
                                        disabled={subjects.meta.current_page >= subjects.meta.last_page}
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
                    router.delete(`/subjects/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Delete subject?"
                message={`"${deleting?.name ?? ''}" will be removed.`}
                confirmLabel="Delete"
            />
        </DashboardLayout>
    );
}

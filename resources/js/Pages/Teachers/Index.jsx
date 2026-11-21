import { useState } from 'react';
import { ChevronRight, Plus, Search } from 'lucide-react';
import { Link, router, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState, StatCard } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { PageHeader, PrimaryAction } from '../../Components/page';

export default function TeachersIndex({ teachers, filters }) {
    const [deleting, setDeleting] = useState(null);

    const apply = (extra = {}) =>
        router.get(
            '/teachers',
            {
                search: filters.search ?? '',
                status: filters.status ?? '',
                ...extra,
            },
            { preserveState: true, replace: true }
        );

    const activeCount = teachers.data?.filter((t) => t.status === 'active').length ?? 0;

    return (
        <DashboardLayout
            title="Teachers Management"
            subtitle="Register teachers and manage teaching assignments"
        >
            <PageHeader
                title="Teachers Management"
                subtitle={`${teachers.total ?? 0} teacher(s) · ${activeCount} active`}
                action={<PrimaryAction href="/teachers/create">Register Teacher</PrimaryAction>}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    index={0}
                    label="Total Teachers"
                    value={teachers.total ?? 0}
                    accent="primary"
                />
                <StatCard
                    index={1}
                    label="Active Teachers"
                    value={activeCount}
                    accent="secondary"
                    hint="Eligible for assignments"
                />
                <StatCard
                    index={2}
                    label="Inactive / Suspended"
                    value={(teachers.total ?? 0) - activeCount}
                    accent="muted"
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
                        placeholder="Search by name or email…"
                    />
                </form>
                <select
                    className="input sm:w-48"
                    value={filters.status ?? ''}
                    onChange={(e) => apply({ status: e.target.value })}
                >
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>

            <div className="mt-4 glass-card p-4 sm:p-5">
                {teachers.data.length === 0 ? (
                    <EmptyState
                        title="No teachers found"
                        hint="Register a teacher to begin assigning classes and subjects."
                    />
                ) : (
                    <>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        <th className="pb-3 font-bold">Teacher</th>
                                        <th className="pb-3 font-bold">Email</th>
                                        <th className="pb-3 font-bold">Phone</th>
                                        <th className="pb-3 font-bold text-center">Status</th>
                                        <th className="pb-3 font-bold text-center">Classes</th>
                                        <th className="pb-3 font-bold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {teachers.data.map((t) => (
                                        <tr key={t.id} className="transition hover:bg-canvas/60">
                                            <td className="py-3 font-bold text-ink">
                                                {t.name}
                                                <span className="block text-[11px] text-slate-400 mt-0.5">
                                                    {t.email}
                                                </span>
                                            </td>
                                            <td className="py-3 text-slate-600">{t.email}</td>
                                            <td className="py-3 text-slate-600">{t.phone ?? '—'}</td>
                                            <td className="py-3 text-center">
                                                <Badge
                                                    tone={
                                                        t.status === 'active'
                                                            ? 'success'
                                                            : t.status === 'suspended'
                                                                ? 'warning'
                                                                : 'muted'
                                                    }
                                                >
                                                    {t.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3 text-center font-bold text-ink">
                                                {t.classes_taught}
                                            </td>
                                            <td className="py-3 text-right">
                                                <Link
                                                    href={`/teachers/${t.id}`}
                                                    className="text-xs text-primary hover:underline mr-2"
                                                >
                                                    Profile
                                                </Link>
                                                <Link
                                                    href={`/teachers/${t.id}/edit`}
                                                    className="text-xs text-primary hover:underline mr-2"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() => setDeleting(t)}
                                                    className="text-xs text-rose-600 hover:underline"
                                                >
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="space-y-3 sm:hidden">
                            {teachers.data.map((t) => (
                                <li key={t.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-bold text-ink">{t.name}</p>
                                            <p className="text-xs text-slate-400">{t.email}</p>
                                        </div>
                                        <Badge
                                            tone={
                                                t.status === 'active'
                                                    ? 'success'
                                                    : t.status === 'suspended'
                                                        ? 'warning'
                                                        : 'muted'
                                            }
                                        >
                                            {t.status}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-xs text-slate-500">
                                        {t.phone ?? 'No phone'} · {t.classes_taught} class(es) assigned
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <Link
                                            href={`/teachers/${t.id}`}
                                            className="flex-1 btn-ghost py-1.5 text-xs text-center"
                                        >
                                            Profile
                                        </Link>
                                        <Link
                                            href={`/teachers/${t.id}/edit`}
                                            className="flex-1 btn-ghost py-1.5 text-xs text-center"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => setDeleting(t)}
                                            className="flex-1 btn-ghost py-1.5 text-xs text-center text-rose-600"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="mt-4">
                            {teachers.meta && (
                                <div className="flex items-center justify-between gap-1">
                                    <button
                                        onClick={() => {
                                            if (teachers.meta.current_page > 1)
                                                apply({ page: teachers.meta.current_page - 1 });
                                        }}
                                        disabled={teachers.meta.current_page <= 1}
                                        className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                                    </button>
                                    <span className="px-3 text-xs font-bold text-ink">
                                        {teachers.meta.current_page} / {teachers.meta.last_page}
                                    </span>
                                    <button
                                        onClick={() => {
                                            if (teachers.meta.current_page < teachers.meta.last_page)
                                                apply({ page: teachers.meta.current_page + 1 });
                                        }}
                                        disabled={teachers.meta.current_page >= teachers.meta.last_page}
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
                    router.delete(`/teachers/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Remove teacher?"
                message={`"${deleting?.name ?? ''}" will be removed.`}
                confirmLabel="Remove"
            />
        </DashboardLayout>
    );
}

import { useState } from 'react';
import { BookOpen, ChevronRight, ClipboardList, GraduationCap, Plus, RefreshCw } from 'lucide-react';
import { Link, router, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { PageHeader, PrimaryAction } from '../../Components/page';

const STATUS_TONE = { draft: 'muted', published: 'secondary', closed: 'danger' };

const ACTION_LABELS = {
    publish: { label: 'Publish', confirm: 'Publish this examination?' },
    reopen: { label: 'Reopen to Draft', confirm: 'Return this examination to draft?' },
    close: { label: 'Close', confirm: 'Close this examination?' },
};

export default function ExaminationsIndex({ examinations, filters }) {
    const [deleting, setDeleting] = useState(null);
    const [publishing, setPublishing] = useState(null);
    const [reopening, setReopening] = useState(null);
    const [closing, setClosing] = useState(null);

    const apply = (extra = {}) =>
        router.get(
            '/examinations',
            { search: filters.search ?? '', status: filters.status ?? '', ...extra },
            { preserveState: true, replace: true }
        );

    const actionFor = (status) => {
        if (status === 'draft') {
            return 'publish';
        }
        if (status === 'published') {
            return 'reopen';
        }
        return null;
    };

    return (
        <DashboardLayout
            title="Examination Management"
            subtitle="Create and configure school examinations"
        >
            <PageHeader
                title="Examination Management"
                subtitle={`${examinations.total ?? 0} examination(s)`}
                action={<PrimaryAction href="/examinations/create">Create Examination</PrimaryAction>}
            />

            <div className="mb-4 flex flex-wrap gap-2">
                <input
                    type="search"
                    placeholder="Search examinations…"
                    value={filters.search ?? ''}
                    onChange={(e) => apply({ search: e.target.value })}
                    className="input pl-10 max-w-md"
                />
                <select
                    className="input w-auto min-w-[150px]"
                    value={filters.status ?? ''}
                    onChange={(e) => apply({ status: e.target.value })}
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            <div className="glass-card p-4 sm:p-5">
                {examinations.data.length === 0 ? (
                    <EmptyState
                        title="No examinations yet"
                        hint="Create your first examination to begin recording results."
                    />
                ) : (
                    <>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        <th className="pb-3 font-bold">Name</th>
                                        <th className="pb-3 font-bold">Academic Year</th>
                                        <th className="pb-3 font-bold">Term</th>
                                        <th className="pb-3 font-bold">Status</th>
                                        <th className="pb-3 font-bold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {examinations.data.map((e) => {
                                        const action = actionFor(e.status);
                                        const isDraft = e.status === 'draft';
                                        return (
                                            <tr key={e.id} className="transition hover:bg-canvas/60">
                                                <td className="py-3 font-bold text-ink">{e.name}</td>
                                                <td className="py-3 text-slate-600">{e.academic_year ?? '—'}</td>
                                                <td className="py-3 text-slate-600">{e.term ?? '—'}</td>
                                                <td className="py-3">
                                                    <Badge tone={STATUS_TONE[e.status] ?? 'muted'}>
                                                        {e.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 text-right">
                                                    <Link
                                                        href={`/examinations/${e.id}/edit`}
                                                        className="text-xs text-primary hover:underline mr-2"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <Link
                                                        href={`/examinations/${e.id}/progress`}
                                                        className="text-xs text-secondary hover:underline mr-2"
                                                    >
                                                        Progress
                                                    </Link>
                                                    {action && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                if (action === 'publish') setPublishing(e);
                                                                if (action === 'reopen') setReopening(e);
                                                                if (action === 'close') setClosing(e);
                                                            }}
                                                            className="text-xs font-medium hover:underline"
                                                            style={{
                                                                color:
                                                                    action === 'close'
                                                                        ? '#dc2626'
                                                                        : action === 'publish'
                                                                        ? '#15803d'
                                                                        : '#0f766e',
                                                            }}
                                                        >
                                                            {ACTION_LABELS[action].label}
                                                        </button>
                                                    )}
                                                    {isDraft && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setDeleting(e)}
                                                            className="text-xs text-rose-600 hover:underline ml-2"
                                                        >
                                                            Delete
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    router.delete(`/examinations/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Delete examination?"
                message={`"${deleting?.name ?? ''}" will be removed.`}
                confirmLabel="Delete"
            />

            <ConfirmDialog
                open={!!publishing}
                onClose={() => setPublishing(null)}
                onConfirm={() => {
                    router.post(`/examinations/${publishing.id}/publish`, { preserveScroll: true });
                    setPublishing(null);
                }}
                title={ACTION_LABELS.publish.confirm}
                message={`"${publishing?.name ?? ''}" will be published and made available to teachers.`}
                confirmLabel="Publish"
            />

            <ConfirmDialog
                open={!!reopening}
                onClose={() => setReopening(null)}
                onConfirm={() => {
                    router.post(`/examinations/${reopening.id}/reopen`, { preserveScroll: true });
                    setReopening(null);
                }}
                title={ACTION_LABELS.reopen.confirm}
                message={`"${reopening?.name ?? ''}" will return to draft. This is not allowed once results have been submitted or approved.`}
                confirmLabel="Reopen"
            />

            <ConfirmDialog
                open={!!closing}
                onClose={() => setClosing(null)}
                onConfirm={() => {
                    router.post(`/examinations/${closing.id}/close`, { preserveScroll: true });
                    setClosing(null);
                }}
                title={ACTION_LABELS.close.confirm}
                message={`"${closing?.name ?? ''}" will be closed. No further marks can be entered.`}
                confirmLabel="Close"
            />
        </DashboardLayout>
    );
}

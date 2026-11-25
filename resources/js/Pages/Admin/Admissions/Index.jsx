import { useState } from 'react';
import { motion } from 'framer-motion';
import { Link, router } from '@inertiajs/react';
import { Eye, Plus, RefreshCw, X } from 'lucide-react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import { Badge } from '../../../Components/ui';
import { ConfirmDialog } from '../../../Components/modal';
import { FilterBar, PageHeader, Pagination } from '../../../Components/page';

const STATUS_TONES = {
    submitted: 'warning',
    under_review: 'warning',
    accepted: 'success',
    rejected: 'danger',
    enrolled: 'primary',
};

export default function AdmissionsIndex({ applications, filters, statuses }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [reviewing, setReviewing] = useState(null);
    const [actionOpen, setActionOpen] = useState(null);
    const [pendingAction, setPendingAction] = useState(null);

    const apply = (extra = {}) => {
        router.get(
            '/admissions',
            { search, status: statusFilter, ...extra },
            { preserveState: true, replace: true }
        );
    };

    const handleAction = (applicationId, action, notes = '') => {
        setPendingAction({ applicationId, action, notes });
        setActionOpen(true);
        setReviewing(null);
    };

    const confirmAction = () => {
        if (!pendingAction) return;
        const { applicationId, action, notes } = pendingAction;
        const routeMap = {
            review: `/admissions/${applicationId}/review`,
            accept: `/admissions/${applicationId}/accept`,
            reject: `/admissions/${applicationId}/reject`,
        };
        router.post(routeMap[action], {
            review_notes: notes,
        }, {
            preserveScroll: true,
            onSuccess: () => setActionOpen(false),
            onError: () => setActionOpen(false),
        });
        setPendingAction(null);
    };

    const isTerminal = (status) =>
        status === 'accepted' || status === 'rejected' || status === 'enrolled';

    const canReview = (status) => status === 'submitted' || status === 'under_review';

    return (
        <DashboardLayout title="Admissions" subtitle="Online admission applications submitted by parents.">
            <PageHeader
                title="Admission Applications"
                subtitle={`${applications.total ?? 0} application(s) found`}
                action={
                    <button
                        type="button"
                        onClick={() => apply()}
                        className="btn-ghost"
                    >
                        <RefreshCw className="h-4 w-4" /> Refresh
                    </button>
                }
            />

            <FilterBar
                value={search}
                onChange={setSearch}
                onSubmit={() => apply()}
                placeholder="Search child, parent or reference no…"
            >
                <select
                    className="input w-auto min-w-[150px]"
                    value={statusFilter}
                    onChange={(e) => apply({ status: e.target.value })}
                >
                    <option value="">All statuses</option>
                    {statuses.map((s) => (
                        <option key={s.value} value={s.value}>{s.label}</option>
                    ))}
                </select>
            </FilterBar>

            <div className="glass-card p-4 sm:p-5">
                {applications.data.length === 0 ? (
                    <EmptyStatePrompt
                        search={search}
                        status={statusFilter}
                        onClear={() => {
                            setSearch('');
                            setStatusFilter('');
                            apply();
                        }}
                    />
                ) : (
                    <>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        {['Reference', 'Child', 'Parent', 'Phone', 'Gender', 'DOB', 'Class', 'Submitted', 'Status', ''].map((h) => (
                                            <th key={h} className="whitespace-nowrap pb-3 font-bold">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {applications.data.map((a) => (
                                        <Row key={a.id} app={a} isTerminal={isTerminal(a.status)} canReview={canReview(a.status)} onAction={handleAction} />
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="space-y-3 sm:hidden">
                            {applications.data.map((a) => (
                                <li key={a.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate font-bold text-ink">{a.child_name}</p>
                                            <p className="font-mono text-xs text-slate-500">{a.reference_no}</p>
                                            <p className="text-xs text-slate-500">{a.parent_name} · {a.parent_phone}</p>
                                        </div>
                                        <Badge tone={STATUS_TONES[a.status] ?? 'muted'}>{a.status_label}</Badge>
                                    </div>
                                    <div className="mt-2 flex flex-wrap gap-2 text-sm text-slate-600">
                                        <span>{a.gender_label} · {a.dob ?? '—'}</span>
                                        <span>{a.class ?? '—'} ({a.class_code ?? ''})</span>
                                        <span>{a.submitted_at ?? '—'}</span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Link href={`/admissions/${a.id}`} className="btn-ghost px-3 py-1.5 text-xs">
                                            <Eye className="h-3.5 w-3.5" /> Details
                                        </Link>
                                        {canReview(a.status) && (
                                            <button
                                                type="button"
                                                onClick={() => handleAction(a.id, 'review')}
                                                className="btn bg-amber-50 px-3 py-1.5 text-xs text-amber-700 hover:bg-amber-100"
                                            >
                                                Review
                                            </button>
                                        )}
                                        {canReview(a.status) && (
                                            <button
                                                type="button"
                                                onClick={() => handleAction(a.id, 'accept')}
                                                className="btn bg-emerald-50 px-3 py-1.5 text-xs text-emerald-700 hover:bg-emerald-100"
                                            >
                                                Accept
                                            </button>
                                        )}
                                        {canReview(a.status) && (
                                            <button
                                                type="button"
                                                onClick={() => handleAction(a.id, 'reject')}
                                                className="btn bg-rose-50 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-100"
                                            >
                                                Reject
                                            </button>
                                        )}
                                    </div>
                                    {a.review_notes && (
                                        <p className="mt-2 text-xs text-slate-500 italic">Notes: {a.review_notes}</p>
                                    )}
                                </li>
                            ))}
                        </ul>

                        <Pagination meta={applications} onPage={(page) => apply({ page })} />
                    </>
                )}
            </div>

            <ReviewModal
                open={reviewing}
                onClose={() => setReviewing(null)}
                onSubmit={(notes) => {
                    setReviewing(null);
                    if (pendingAction) {
                        setPendingAction({ ...pendingAction, notes });
                        setActionOpen(true);
                    }
                }}
            />

            <ConfirmDialog
                open={actionOpen}
                onClose={() => setActionOpen(false)}
                onConfirm={confirmAction}
                title={dialogTitle(pendingAction)}
                message={dialogMessage(pendingAction)}
                confirmLabel={dialogLabel(pendingAction)}
                processing={false}
            />
        </DashboardLayout>
    );
}

function EmptyStatePrompt({ search, status, onClear }) {
    const hasFilters = Boolean(search || status);

    return (
        <div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
            <span className="grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                <RefreshCw className="h-6 w-6" />
            </span>
            <p className="text-sm font-bold text-slate-600">
                {hasFilters ? 'No applications match your filters.' : 'No admission applications yet.'}
            </p>
            {hasFilters && (
                <button
                    type="button"
                    onClick={onClear}
                    className="text-xs font-bold text-primary underline underline-offset-4 hover:text-primary/80"
                >
                    Clear filters
                </button>
            )}
        </div>
    );
}

function Row({ app, isTerminal, canReview, onAction }) {
    const btn = 'grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition';

    return (
        <tr className="transition hover:bg-canvas/60">
            <td className="py-3 font-mono text-xs text-slate-500">{app.reference_no}</td>
            <td className="py-3">
                <Link href={`/admissions/${app.id}`} className="font-bold text-ink hover:text-primary">
                    {app.child_name}
                </Link>
            </td>
            <td className="py-3 text-slate-600">{app.parent_name}</td>
            <td className="py-3 font-mono text-xs text-slate-500">{app.parent_phone}</td>
            <td className="py-3 capitalize text-slate-600">{app.gender_label}</td>
            <td className="py-3 text-slate-600">{app.dob ?? '—'}</td>
            <td className="py-3 text-slate-600">{app.class ?? '—'} ({app.class_code ?? ''})</td>
            <td className="py-3 text-slate-600 text-xs">{app.submitted_at ?? '—'}</td>
            <td className="py-3">
                <Badge tone={STATUS_TONES[app.status] ?? 'muted'}>{app.status_label}</Badge>
            </td>
            <td className="py-3">
                <div className="flex items-center justify-end gap-1">
                    <Link href={`/admissions/${app.id}`} title="Details" className={`${btn} hover:bg-slate-100 hover:text-primary`}>
                        <Eye className="h-4 w-4" />
                    </Link>
                    {canReview && !isTerminal && (
                        <button type="button" onClick={() => onAction(app.id, 'review')} title="Review" className={`${btn} hover:bg-amber-50 hover:text-amber-700`}>
                            <RefreshCw className="h-4 w-4" />
                        </button>
                    )}
                    {canReview && !isTerminal && (
                        <button type="button" onClick={() => onAction(app.id, 'accept')} title="Accept" className={`${btn} hover:bg-emerald-50 hover:text-emerald-700`}>
                            <Plus className="h-4 w-4" />
                        </button>
                    )}
                    {canReview && !isTerminal && (
                        <button type="button" onClick={() => onAction(app.id, 'reject')} title="Reject" className={`${btn} hover:bg-rose-50 hover:text-rose-600`}>
                            <Eye className="h-4 w-4" />{" "}
                        </button>
                    )}
                </div>
            </td>
        </tr>
    );
}

function dialogTitle(action) {
    if (!action) return '';
    switch (action.action) {
        case 'review': return 'Mark as Under Review?';
        case 'accept': return 'Accept Application?';
        case 'reject': return 'Reject Application?';
        default: return '';
    }
}

function dialogMessage(action) {
    if (!action) return '';
    switch (action.action) {
        case 'review': return 'Move this application to under review so staff can begin inspection.';
        case 'accept': return 'Accepting this application notifies the parent by SMS (when enabled).';
        case 'reject': return 'Rejecting this application does NOT send an SMS.';
        default: return '';
    }
}

function dialogLabel(action) {
    if (!action) return '';
    switch (action.action) {
        case 'review': return 'Mark Under Review';
        case 'accept': return 'Accept Application';
        case 'reject': return 'Reject Application';
        default: return '';
    }
}

/** Review modal with optional internal notes. */
function ReviewModal({ open, onClose, onSubmit }) {
    const [notes, setNotes] = useState('');

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                onClick={onClose}
                className="absolute inset-0 bg-ink/60 backdrop-blur-sm"
            />

            <motion.div
                initial={{ opacity: 0, y: 40, scale: 0.98 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                transition={{ type: 'tween', duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
                role="dialog"
                aria-modal="true"
                className="relative max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:rounded-2xl sm:p-6 sm:max-w-lg"
            >
                <header className="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 className="font-display text-lg font-bold text-ink">Add Review Notes</h3>
                        <p className="mt-0.5 text-sm text-slate-500">Optional internal notes recorded with the status change.</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-slate-200"
                        aria-label="Close"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </header>

                <textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    rows={4}
                    className="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary mb-4"
                    placeholder="Internal review notes (staff only)..."
                />

                <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" onClick={onClose} className="btn-ghost">
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={() => onSubmit(notes)}
                        className="btn-primary inline-flex items-center gap-2"
                    >
                        <Plus className="h-4 w-4" /> Save Notes
                    </button>
                </div>
            </motion.div>
        </div>
    );
}

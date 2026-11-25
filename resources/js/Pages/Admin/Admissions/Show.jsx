import { useState } from 'react';
import { motion } from 'framer-motion';
import { Link, router, useForm } from '@inertiajs/react';
import {
    Calendar, Mail, Phone, User, Users, Clock, FileText, ChevronLeft, X,
} from 'lucide-react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import { Badge } from '../../../Components/ui';
import { ConfirmDialog } from '../../../Components/modal';
import { Field, Textarea } from '../../../Components/form';
import { PageHeader } from '../../../Components/page';

const STATUS_TONES = {
    submitted: 'warning',
    under_review: 'warning',
    accepted: 'success',
    rejected: 'danger',
    enrolled: 'primary',
};

export default function AdmissionsShow({ application, classes }) {
    const [reviewOpen, setReviewOpen] = useState(false);
    const [actionOpen, setActionOpen] = useState(null);
    const [pendingAction, setPendingAction] = useState(null);

    const reviewForm = useForm({ review_notes: '' });

    const handleAction = (action) => {
        setPendingAction(action);
        setActionOpen(true);
    };

    const confirmAction = () => {
        if (!pendingAction) return;
        const routeMap = {
            review: `/admissions/${application.id}/review`,
            accept: `/admissions/${application.id}/accept`,
            reject: `/admissions/${application.id}/reject`,
        };
        router.post(routeMap[pendingAction], {
            review_notes: reviewForm.data.review_notes,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setActionOpen(false);
                setReviewOpen(false);
                reviewForm.reset();
            },
            onError: () => setActionOpen(false),
        });
        setPendingAction(null);
    };

    const isTerminal = (status) =>
        status === 'accepted' || status === 'rejected' || status === 'enrolled';

    const canReview = application.status === 'submitted' || application.status === 'under_review';

    return (
        <DashboardLayout title="Application Details" subtitle={`Reference: ${application.reference_no}`}>
            <PageHeader
                title={application.child_name}
                subtitle={`${application.status_label} · ${application.reference_no} · ${application.class ?? 'No class'}`}
                back="/admissions"
                action={
                    <div className="flex flex-wrap gap-2">
                        {canReview && !isTerminal(application.status) && (
                            <button
                                type="button"
                                onClick={() => setReviewOpen(true)}
                                className="btn-ghost px-3 py-1.5 text-xs"
                            >
                                <Clock className="h-3.5 w-3.5" /> Review
                            </button>
                        )}
                        {canReview && !isTerminal(application.status) && (
                            <button
                                type="button"
                                onClick={() => handleAction('accept')}
                                className="btn bg-emerald-600 text-white hover:bg-emerald-700 px-3 py-1.5 text-xs"
                            >
                                <Users className="h-3.5 w-3.5" /> Accept
                            </button>
                        )}
                        {canReview && !isTerminal(application.status) && (
                            <button
                                type="button"
                                onClick={() => handleAction('reject')}
                                className="btn bg-rose-600 text-white hover:bg-rose-700 px-3 py-1.5 text-xs"
                            >
                                <FileText className="h-3.5 w-3.5" /> Reject
                            </button>
                        )}
                    </div>
                }
            />

            <div className="grid gap-6 lg:grid-cols-4">
                {/* ---------- Application summary ---------- */}
                <div className="lg:col-span-1">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                        <p className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Status</p>
                        <Badge tone={STATUS_TONES[application.status] ?? 'muted'} className="mb-3">
                            {application.status_label}
                        </Badge>

                        <dl className="space-y-3 text-sm">
                            {[['Reference No.', application.reference_no],
                                ['Class Applied', `${application.class ?? '—'}${application.class_code ? ` (${application.class_code})` : ''}`],
                                ['Submitted', application.submitted_at ?? '—'],
                                ['Reviewed By', application.reviewed_by ?? '—']].map(([label, value]) => (
                                <div key={label} className="flex items-start justify-between gap-3">
                                    <dt className="text-slate-500">{label}</dt>
                                    <dd className="text-right font-semibold text-ink">{value}</dd>
                                </div>
                            ))}
                        </dl>

                        {application.review_notes && (
                            <div className="mt-4 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-800">
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3 w-3" /> Internal Notes
                                </span>
                                <p className="mt-1">{application.review_notes}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* ---------- Parent / guardian ---------- */}
                <div className="lg:col-span-1">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                        <h3 className="font-display text-base font-bold text-ink mb-3">Parent / Guardian</h3>
                        <dl className="space-y-3 text-sm">
                            {[['Name', application.parent_name],
                                ['Relationship', application.relationship ? application.relationship.charAt(0).toUpperCase() + application.relationship.slice(1) : '—'],
                                ['Phone', application.parent_phone ?? '—'],
                                ['Email', application.parent_email ?? '—'],
                                ['Address', application.address ?? '—']].map(([label, value]) => (
                                <div key={label} className="flex items-start justify-between gap-3">
                                    <dt className="text-slate-500">{label}</dt>
                                    <dd className="text-right font-semibold text-ink">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                </div>

                {/* ---------- Child ---------- */}
                <div className="lg:col-span-1">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                        <h3 className="font-display text-base font-bold text-ink mb-3">Child</h3>
                        <dl className="space-y-3 text-sm">
                            {[['Full Name', application.child_name],
                                ['Gender', application.gender_label],
                                ['Date of Birth', application.dob ?? '—']].map(([label, value]) => (
                                <div key={label} className="flex items-start justify-between gap-3">
                                    <dt className="text-slate-500">{label}</dt>
                                    <dd className="text-right font-semibold text-ink">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                </div>

                {/* ---------- Message + class list ---------- */}
                <div className="lg:col-span-1">
                    {application.message && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 mb-4">
                            <h3 className="font-display text-base font-bold text-ink mb-3">Additional Notes</h3>
                            <p className="text-sm text-slate-600 whitespace-pre-line">{application.message}</p>
                        </div>
                    )}
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                        <h3 className="font-display text-base font-bold text-ink mb-3">Available Classes</h3>
                        <p className="text-xs text-slate-500 mb-3">Classes open for application.</p>
                        <ul className="space-y-2">
                            {classes.map((c) => (
                                <li key={c.id} className="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm">
                                    <span className="text-ink">{c.name}</span>
                                    <span className="font-mono text-xs text-slate-500">{c.code}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </div>

            <ReviewModal
                open={reviewOpen}
                onClose={() => setReviewOpen(false)}
                form={reviewForm}
                onSubmit={(notes) => {
                    setReviewOpen(false);
                    handleAction('review');
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

function dialogTitle(action) {
    if (!action) return '';
    switch (action) {
        case 'review': return 'Mark as Under Review?';
        case 'accept': return 'Accept Application?';
        case 'reject': return 'Reject Application?';
        default: return '';
    }
}

function dialogMessage(action) {
    if (!action) return '';
    switch (action) {
        case 'review': return 'Move this application to under review so staff can begin inspection.';
        case 'accept': return 'Accepting this application notifies the parent by SMS (when enabled).';
        case 'reject': return 'Rejecting this application does NOT send an SMS.';
        default: return '';
    }
}

function dialogLabel(action) {
    if (!action) return '';
    switch (action) {
        case 'review': return 'Mark Under Review';
        case 'accept': return 'Accept Application';
        case 'reject': return 'Reject Application';
        default: return '';
    }
}

function ReviewModal({ open, onClose, form, onSubmit }) {
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
                        <h3 className="font-display text-lg font-bold text-ink">Review Notes</h3>
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

                <form onSubmit={(e) => {
                    e.preventDefault();
                    onSubmit(form.data.review_notes);
                }}>
                    <Field label="Internal Review Notes" error={form.errors.review_notes} required={false}>
                        <Textarea
                            value={form.data.review_notes}
                            onChange={(e) => form.setData('review_notes', e.target.value)}
                            rows={4}
                            placeholder="Internal notes visible only to staff…"
                        />
                    </Field>

                    <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onClick={onClose} className="btn-ghost">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="btn-primary inline-flex items-center gap-2"
                        >
                            {form.processing ? 'Saving…' : 'Save Notes'}
                        </button>
                    </div>
                </form>
            </motion.div>
        </div>
    );
}

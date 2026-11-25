import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { PageHeader, Table, Pagination } from '@/Components/page';
import { Button, Badge, Flash, Input } from '@/Components/ui';
import { ConfirmDialog } from '@/Components/modal';
import { Clock, Phone, AlertTriangle, RefreshCw } from 'lucide-react';

const STATUS_TONES = {
    submission_unknown: 'warning',
    failed: 'danger',
};

const STATUS_LABELS = {
    submission_unknown: 'Unresolved',
    failed: 'Failed',
};

export default function AdmissionRecovery({ messages, filters, statuses }) {
    const { flash } = usePage().props;
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [pending, setPending] = useState(null);
    const [pendingType, setPendingType] = useState(null);

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    const apiFetch = async (url, body = {}) => {
        const r = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
        });

        return r.json();
    };

    const handleReconcile = async (message) => {
        setPending(message.id);
        setPendingType('reconcile');
        try {
            const data = await apiFetch(route('sms.admin.admission-notifications.reconcile', message.id), {});
            if (data.success) {
                window.location.reload();
                return;
            }
            alert(data.error || 'Reconciliation failed.');
        } catch (err) {
            alert('Unexpected error while reconciling.');
        } finally {
            setPending(null);
            setPendingType(null);
        }
    };

    const handleResendConfirm = async () => {
        const id = pending;
        if (!id) return;
        const message = messages.data.find((m) => m.id === id);
        if (!message) return;

        setPendingType('resend');
        try {
            const data = await apiFetch(route('sms.admin.admission-notifications.resend', message.id), {
                confirmed: true,
                reason: 'Admin recovery: manual resend of unresolved admission notification.',
            });
            if (data.success) {
                setConfirmOpen(false);
                window.location.reload();
                return;
            }
            alert(data.error || 'Resend failed.');
        } catch (err) {
            alert('Unexpected error while resending.');
        } finally {
            setPending(null);
            setPendingType(null);
        }
    };

    return (
        <DashboardLayout title="Admission SMS Recovery" subtitle="Reconcile and resend unresolved admission notifications.">
            {flash && <Flash />}

            <PageHeader
                title="Unresolved Admission Notifications"
                back="/sms/history"
                subtitle="Messages stuck in submission_unknown or failed require explicit review before any resend."
            />

            <div className="grid gap-6 sm:grid-cols-12">
                <div className="sm:col-span-8">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                        <div className="mb-4 flex flex-wrap gap-2">
                            <Label>Status</Label>
                            <Select
                                value={filters.status || ''}
                                onChange={(e) => {
                                    const url = new URL(window.location.href);
                                    url.searchParams.set('status', e.target.value);
                                    window.location.href = url.toString();
                                }}
                            >
                                <option value="">All</option>
                                {statuses?.map((s) => (
                                    <option key={s} value={s}>{s}</option>
                                ))}
                            </Select>
                            <Label>Search</Label>
                            <Input
                                placeholder="Reference, recipient, or error…"
                                value={filters.search || ''}
                                onChange={(e) => {
                                    const url = new URL(window.location.href);
                                    url.searchParams.set('search', e.target.value);
                                    window.location.href = url.toString();
                                }}
                            />
                        </div>

                        <Table
                            head={[
                                'Reference',
                                'Type',
                                'Recipient',
                                'Status',
                                'Provider ID',
                                'Last Error',
                                'Created',
                                'Actions',
                            ]}
                        >
                            {messages.data.map((m) => (
                                <tr key={m.id} className="hover:bg-canvas">
                                    <td className="py-3 font-mono text-slate-600 text-xs">{m.reference_no || '—'}</td>
                                    <td className="py-3 text-slate-600 text-xs capitalize">{m.notification_type || '—'}</td>
                                    <td className="py-3 font-mono text-slate-600 text-xs">{m.recipient}</td>
                                    <td className="py-3">
                                        <Badge tone={STATUS_TONES[m.status] ?? 'muted'}>
                                            {STATUS_LABELS[m.status] ?? m.status}
                                        </Badge>
                                    </td>
                                    <td className="py-3 text-slate-600 text-xs truncate max-w-xs">{m.provider_message_id || '—'}</td>
                                    <td className="py-3 text-slate-600 text-xs truncate max-w-xs">{m.error_message || '—'}</td>
                                    <td className="py-3 text-slate-600 text-xs">{m.created_at || '—'}</td>
                                    <td className="py-3">
                                        <div className="flex items-center gap-2">
                                            {m.is_retryable && (
                                                <Button
                                                    size="sm"
                                                    onClick={() => handleReconcile(m)}
                                                    disabled={pending === m.id && pendingType === 'reconcile'}
                                                    className="flex-1"
                                                >
                                                    {pending === m.id && pendingType === 'reconcile' ? (
                                                        <span className="flex items-center gap-1.5"><RefreshCw className="h-3.5 w-3.5" animate-spin /> Verifying</span>
                                                    ) : (
                                                        <span><Clock className="h-3.5 w-3.5" /> Verify</span>
                                                    )}
                                                </Button>
                                            )}
                                            {m.is_retryable && (
                                                <Button
                                                    size="sm"
                                                    variant="rose"
                                                    onClick={() => {
                                        setPending(m.id);
                                        setPendingType('resend');
                                        setConfirmOpen(true);
                                    }}
                                                    disabled={pending === m.id}
                                                    className="flex-1"
                                                >
                                                    Resend
                                                </Button>
                                            )}
                                            {!m.is_retryable && (
                                                <span className="text-xs text-slate-400">Not retryable</span>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {!messages.data.length && (
                                <tr>
                                    <td colSpan="8" className="py-4 text-center text-slate-500">No unresolved admission notifications.</td>
                                </tr>
                            )}
                        </Table>

                        <Pagination meta={messages} onPage={(page) => {
                            const url = new URL(window.location.href);
                            url.searchParams.set('page', page);
                            window.location.href = url.toString();
                        }} />
                    </div>
                </div>

                <div className="sm:col-span-4">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                        <h3 className="font-display text-base font-bold text-ink mb-3">How to resolve</h3>
                        <ul className="space-y-3 text-sm text-slate-600">
                            <li>
                                <span className="font-semibold text-ink">submission_unknown</span>{' '}
                                — the provider may have accepted the message but the outcome was not confirmed. Verify with the provider before resending.
                            </li>
                            <li>
                                <span className="font-semibold text-ink">Verify</span>{' '}
                                checks the provider's message status lookup and updates the record without sending a new SMS.
                            </li>
                            <li>
                                <span className="font-semibold text-ink">Resend</span>{' '}
                                sends a new SMS only after explicit confirmation and is audited on the original record.
                            </li>
                            <li>
                                <span className="font-semibold text-ink">Note</span>{' '}
                                — HTTP 202 / queue completion is provider acceptance, not delivery confirmation.
                            </li>
                        </ul>
                        <div className="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                            <AlertTriangle className="h-3.5 w-3.5 shrink-0" /> Administrator-only action. Head of School users retain their existing SMS history resend privilege and are not granted additional access here.
                        </div>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                onConfirm={handleResendConfirm}
                title="Confirm Resend?"
                message={
                    <>
                        Resending sends a new SMS to{' '}
                        <span className="font-mono">{pending && messages.data.find((m) => m.id === pending)?.recipient}</span>
                        . This cannot be undone and is recorded as an audited resend of the original message.
                    </>
                }
                confirmLabel="Resend SMS"
                processing={pendingType === 'resend'}
            />
        </DashboardLayout>
    );
}

function Label({ children }) {
    return (
        <span className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1 block">{children}</span>
    );
}

function Select({ value, onChange, children }) {
    return (
        <select
            value={value}
            onChange={(e) => onChange(e)}
            className="input w-full"
        >
            {children}
        </select>
    );
}

import { Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import { Flash } from '../../../Components/ui';
import { PageHeader } from '../../../Components/page';

function formatDate(value) {
    if (! value) return '—';

    return new Date(value).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function formatAction(action) {
    if (! action) return '—';

    return action
        .replace(/\./g, ' › ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function getActionTone(action) {
    if (! action) return 'muted';

    const lower = action.toLowerCase();
    if (lower.includes('delete') || lower.includes('void') || lower.includes('suspend')) {
        return 'danger';
    }
    if (lower.includes('create') || lower.includes('login') || lower.includes('register')) {
        return 'success';
    }
    if (lower.includes('update') || lower.includes('edit') || lower.includes('password')) {
        return 'warning';
    }

    return 'primary';
}

export default function AuditLogShow({ log }) {
    return (
        <DashboardLayout title="Audit Log Detail" subtitle={log?.action ? formatAction(log.action) : ''}>
            <Flash className="mb-5" />

            <PageHeader
                title="Audit Log Detail"
                subtitle={log ? `#${log.id} — ${formatAction(log.action)}` : ''}
                back="/audit-logs"
                action={
                    <Link
                        href="/audit-logs"
                        className="btn-ghost inline-flex items-center gap-2"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Audit Logs
                    </Link>
                }
            />

            {! log ? (
                <div className="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
                    Audit log not found.
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    <div className="lg:col-span-2 space-y-4">
                        <section className="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 className="section-title mb-4">Event Details</h3>
                            <div className="grid grid-cols-1 gap-3">
                                <DetailRow label="Action" value={<Badge tone={getActionTone(log.action)}>{formatAction(log.action)}</Badge>} />
                                <DetailRow label="Description" value={log.description ?? '—'} />
                                <DetailRow
                                    label="Date / Time"
                                    value={formatDate(log.created_at)}
                                />
                                <DetailRow
                                    label="Model / Entity"
                                    value={
                                        log.auditable_type && log.auditable_id
                                            ? `${log.auditable_type} #${log.auditable_id}`
                                            : '—'
                                    }
                                />
                            </div>
                        </section>

                        {log.properties && (
                            <section className="rounded-2xl border border-slate-200 bg-white p-5">
                                <h3 className="section-title mb-4">Properties / Metadata</h3>
                                <pre className="overflow-x-auto rounded-xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-700">
                                    {JSON.stringify(log.properties, null, 2)}
                                </pre>
                            </section>
                        )}
                    </div>

                    <div className="space-y-4">
                        <section className="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 className="section-title mb-4">Performed By</h3>
                            <div className="grid grid-cols-1 gap-3">
                                {log.user ? (
                                    <>
                                        <DetailRow label="Name" value={log.user.name} />
                                        <DetailRow label="Email" value={log.user.email} />
                                        <DetailRow label="Role" value={log.user.role_label} />
                                    </>
                                ) : (
                                    <p className="text-sm text-slate-500">System / Cron action (no user)</p>
                                )}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 className="section-title mb-4">Request Context</h3>
                            <div className="grid grid-cols-1 gap-3">
                                <DetailRow label="IP Address" value={log.ip_address ?? '—'} mono />
                                <DetailRow label="User Agent" value={log.user_agent ?? '—'} mono />
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 className="section-title mb-4">Security Notice</h3>
                            <p className="text-xs leading-relaxed text-slate-500">
                                Audit logs are append-only. Sensitive fields such as passwords,
                                tokens, and session identifiers are redacted before display.
                                Do not share audit logs outside the Administrator role.
                            </p>
                        </section>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}

function DetailRow({ label, value, mono }) {
    return (
        <div className="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
            <p className="text-xs font-bold uppercase tracking-wider text-slate-400">{label}</p>
            <div className={`mt-1 text-sm text-ink ${mono ? 'font-mono text-xs' : ''}`}>{value}</div>
        </div>
    );
}

import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Eye, ShieldCheck } from 'lucide-react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import { Badge, EmptyState, Flash } from '../../../Components/ui';
import { PageHeader, Pagination, FilterBar } from '../../../Components/page';

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

export default function AuditLogsIndex({ logs, filters }) {
    const [search, setSearch] = useState(filters?.search ?? '');

    const applyFilters = () => {
        router.get(
            '/audit-logs',
            { search: search || undefined },
            { preserveState: true, replace: true }
        );
    };

    return (
        <DashboardLayout title="Audit Logs" subtitle="System activity and administrative actions">
            <Flash className="mb-5" />

            <PageHeader
                title="Audit Logs"
                subtitle="System activity and administrative actions"
                action={
                    <span className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-500">
                        <ShieldCheck className="h-4 w-4" />
                        {logs?.total ?? 0} records
                    </span>
                }
            />

            <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-4">
                <FilterBar
                    value={search}
                    onChange={setSearch}
                    placeholder="Search by action, description, user, or IP address…"
                    onSubmit={applyFilters}
                />
            </div>

            {!logs || logs.data.length === 0 ? (
                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <EmptyState
                        icon={ShieldCheck}
                        title="No audit logs found"
                        hint="System activity will appear here as it occurs. Try adjusting your search."
                    />
                </div>
            ) : (
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 bg-slate-50/60">
                                    <th className="whitespace-nowrap px-4 py-3 font-semibold text-slate-500">
                                        Date / Time
                                    </th>
                                    <th className="whitespace-nowrap px-4 py-3 font-semibold text-slate-500">
                                        User
                                    </th>
                                    <th className="whitespace-nowrap px-4 py-3 font-semibold text-slate-500">
                                        Action
                                    </th>
                                    <th className="whitespace-nowrap px-4 py-3 font-semibold text-slate-500">
                                        Description
                                    </th>
                                    <th className="whitespace-nowrap px-4 py-3 font-semibold text-slate-500">
                                        IP Address
                                    </th>
                                    <th className="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="transition hover:bg-slate-50/50">
                                        <td className="whitespace-nowrap px-4 py-3 text-xs text-slate-500">
                                            {formatDate(log.created_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {log.user ? (
                                                <div>
                                                    <p className="font-semibold text-ink">{log.user.name}</p>
                                                    <p className="text-xs text-slate-500">{log.user.email}</p>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-slate-400">System / Cron</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={getActionTone(log.action)}>
                                                {formatAction(log.action)}
                                            </Badge>
                                        </td>
                                        <td
                                            className="max-w-xs truncate px-4 py-3 text-slate-600"
                                            title={log.description ?? ''}
                                        >
                                            {log.description ?? '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">
                                            {log.ip_address ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={`/audit-logs/${log.id}`}
                                                className="inline-grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-primary/10 hover:text-primary"
                                                title="View details"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {logs.last_page > 1 && (
                        <div className="border-t border-slate-100 px-4 py-3">
                            <Pagination
                                meta={logs}
                                onPage={(page) =>
                                    router.get('/audit-logs', { search: filters?.search || undefined, page }, {
                                        preserveState: true,
                                        replace: true,
                                    })
                                }
                            />
                        </div>
                    )}
                </div>
            )}
        </DashboardLayout>
    );
}

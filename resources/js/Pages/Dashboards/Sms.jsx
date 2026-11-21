import { usePage } from '@inertiajs/react';
import { Card, StatCard, Badge, Flash, EmptyState } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Phone, Send, Clock, CheckCircle2, XCircle, BarChart3 } from 'lucide-react';

export default function SmsDashboard({ stats, balance, balance_error, capabilities, recent, campaigns }) {
    const { flash } = usePage().props;

    return (
        <DashboardLayout title="SMS Management" subtitle="Send bulk SMS, manage templates, and track delivery.">
            {flash && <Flash />}

            {/* KPI Cards */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
                <StatCard
                    icon={Phone}
                    label="Total Messages"
                    value={stats?.total || 0}
                    accent="primary"
                    variant="filled"
                    fill="bg-primary"
                />
                <StatCard
                    icon={Send}
                    label="Queued"
                    value={stats?.queued || 0}
                    accent="secondary"
                    variant="filled"
                    fill="bg-secondary"
                />
                <StatCard
                    icon={CheckCircle2}
                    label="Delivered"
                    value={stats?.delivered || 0}
                    accent="accent"
                    variant="filled"
                    fill="bg-emerald-500"
                />
                <StatCard
                    icon={XCircle}
                    label="Failed"
                    value={stats?.failed || 0}
                    accent="accent"
                    variant="filled"
                    fill="bg-rose-500"
                />
            </div>

            {/* Balance */}
            {capabilities.balance && (
                <Card className="mb-6">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Available Credits</h3>
                    </header>
                    <div className="p-4 rounded-xl bg-canvas border border-slate-100">
                        {balance_error ? (
                            <p className="text-sm text-rose-600">{balance_error}</p>
                        ) : (
                            <p className="text-xl font-bold text-ink">
                                {balance !== null ? balance : 'Unable to load balance'}
                            </p>
                        )}
                    </div>
                </Card>
            )}

            {/* Quick Actions */}
            <div className="grid gap-6 mb-6">
                <Card className="col-span-1 xl:col-span-4">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Quick Actions</h3>
                    </header>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="p-4 rounded-xl bg-canvas border border-slate-100">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-bold text-ink">Bulk SMS</p>
                                    <p className="text-xs text-slate-500">Send to parents, teachers, staff</p>
                                </div>
                                <a href="/sms/bulk" className="text-sm font-bold text-primary hover:underline">Compose →</a>
                            </div>
                        </div>
                        <div className="p-4 rounded-xl bg-canvas border border-slate-100">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-bold text-ink">Templates</p>
                                    <p className="text-xs text-slate-500">Manage SMS templates</p>
                                </div>
                                <a href="/sms/templates" className="text-sm font-bold text-primary hover:underline">View →</a>
                            </div>
                        </div>
                        <div className="p-4 rounded-xl bg-canvas border border-slate-100">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-bold text-ink">Results SMS</p>
                                    <p className="text-xs text-slate-500">Notify parents of results</p>
                                </div>
                                <a href="/sms/results" className="text-sm font-bold text-primary hover:underline">Manage →</a>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            {/* Recent Activity */}
            <Card className="mb-6">
                <header className="mb-4 flex items-center justify-between gap-2">
                    <h3 className="font-display text-base font-bold text-ink">Recent SMS Activity</h3>
                </header>
                {recent?.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Recipient</th>
                                    <th className="pb-3 font-bold">Message</th>
                                    <th className="pb-3 font-bold">Status</th>
                                    <th className="pb-3 font-bold">Sent By</th>
                                    <th className="pb-3 font-bold">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recent.map((m) => (
                                    <tr key={m.id} className="transition hover:bg-canvas">
                                        <td className="py-3 text-slate-600 truncate max-w-xs">{m.to}</td>
                                        <td className="py-3 text-slate-600 truncate max-w-xs">{m.message}</td>
                                        <td className="py-3">
                                            <Badge tone={m.status === 'delivered' ? 'success' : m.status === 'failed' ? 'danger' : m.status === 'sent' ? 'secondary' : 'warning'}>{m.status}</Badge>
                                        </td>
                                        <td className="py-3 text-slate-600">{m.sent_by}</td>
                                        <td className="py-3 text-slate-600">{m.sent_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <EmptyState title="No recent SMS activity" hint="Start by composing a bulk SMS or results notification." />
                )}
            </Card>

            {/* Campaigns */}
            <Card>
                <header className="mb-4 flex items-center justify-between gap-2">
                    <h3 className="font-display text-base font-bold text-ink">Recent Campaigns</h3>
                </header>
                {campaigns?.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Campaign</th>
                                    <th className="pb-3 font-bold">Category</th>
                                    <th className="pb-3 font-bold">Recipients</th>
                                    <th className="pb-3 font-bold">Sent / Delivered / Failed</th>
                                    <th className="pb-3 font-bold">Status</th>
                                    <th className="pb-3 font-bold">Sent By</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {campaigns.map((c) => (
                                    <tr key={c.id} className="transition hover:bg-canvas">
                                        <td className="py-3 font-bold text-ink">{c.name}</td>
                                        <td className="py-3 text-slate-600 capitalize">{c.category}</td>
                                        <td className="py-3 text-slate-600">{c.total_recipients}</td>
                                        <td className="py-3 text-slate-600">{c.sent_count} / {c.delivered_count} / {c.failed_count}</td>
                                        <td className="py-3"><Badge tone={c.status === 'completed' ? 'success' : c.status === 'failed' ? 'danger' : 'warning'}>{c.status}</Badge></td>
                                        <td className="py-3 text-slate-600">{c.sent_by}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="py-4 text-center text-slate-500">No campaigns yet.</div>
                )}
            </Card>
        </DashboardLayout>
    );
}

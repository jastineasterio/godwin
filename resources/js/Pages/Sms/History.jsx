import { usePage } from '@inertiajs/react';
import { Card, Input, Label, Badge, Button, Select, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Search, Filter, Clock } from 'lucide-react';

export default function SmsHistory({ messages, categories, statuses, filters }) {
    const { flash } = usePage().props;

    return (
        <DashboardLayout title="SMS History" subtitle="View message delivery history and reports.">
            {flash && <Flash />}

            <div className="grid gap-6">
                <Card>
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <div>
                            <h3 className="font-display text-base font-bold text-ink">SMS History</h3>
                            <p className="text-xs text-slate-500">Filter by date, status, category, or search.</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Search className="h-4 w-4 text-slate-400" />
                        </div>
                    </header>

                    {/* Filters */}
                    <div className="grid gap-4 sm:grid-cols-4 mb-6">
                        <div>
                            <Label>Status</Label>
                            <Select value={filters.status || ''} onChange={(e) => {
                                const url = new URL(window.location.href);
                                url.searchParams.set('status', e.target.value);
                                window.location.href = url.toString();
                            }}>
                                <option value="">All Statuses</option>
                                {statuses?.map((s) => (
                                    <option key={s} value={s}>{s}</option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <Label>Category</Label>
                            <Select value={filters.category || ''} onChange={(e) => {
                                const url = new URL(window.location.href);
                                url.searchParams.set('category', e.target.value);
                                window.location.href = url.toString();
                            }}>
                                <option value="">All Categories</option>
                                {categories?.map((c) => (
                                    <option key={c} value={c}>{c.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <Label>From Date</Label>
                            <Input type="date" value={filters.from || ''} onChange={(e) => {
                                const url = new URL(window.location.href);
                                url.searchParams.set('from', e.target.value);
                                window.location.href = url.toString();
                            }} />
                        </div>
                        <div>
                            <Label>To Date</Label>
                            <Input type="date" value={filters.to || ''} onChange={(e) => {
                                const url = new URL(window.location.href);
                                url.searchParams.set('to', e.target.value);
                                window.location.href = url.toString();
                            }} />
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Recipient</th>
                                    <th className="pb-3 font-bold">Phone</th>
                                    <th className="pb-3 font-bold">Message</th>
                                    <th className="pb-3 font-bold">Category</th>
                                    <th className="pb-3 font-bold">Sent By</th>
                                    <th className="pb-3 font-bold">Status</th>
                                    <th className="pb-3 font-bold">Date</th>
                                    <th className="pb-3 font-bold">Provider ID</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {messages?.data?.map((m) => (
                                    <tr key={m.id} className="hover:bg-canvas">
                                        <td className="py-3 text-slate-600">{m.user?.name || 'System'}</td>
                                        <td className="py-3 font-mono text-slate-600">{m.to}</td>
                                        <td className="py-3 text-slate-600 truncate max-w-xs">{m.message}</td>
                                        <td className="py-3 text-slate-600 capitalize">{m.category}</td>
                                        <td className="py-3 text-slate-600">{m.user?.name || '—'}</td>
                                        <td className="py-3">
                                            <Badge tone={m.status === 'delivered' ? 'success' : m.status === 'failed' ? 'danger' : m.status === 'sent' ? 'secondary' : 'warning'}>
                                                {m.status}
                                            </Badge>
                                        </td>
                                        <td className="py-3 text-slate-600">{m.created_at}</td>
                                        <td className="py-3 text-slate-600 text-xs truncate max-w-xs">{m.provider_message_id || '—'}</td>
                                    </tr>
                                ))}
                                {!messages?.data?.length && (
                                    <tr>
                                        <td colSpan="8" className="py-4 text-center text-slate-500">No SMS messages found.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {messages && (
                        <div className="mt-4 flex items-center justify-between text-xs text-slate-500">
                            <span>Page {messages.current_page} of {messages.last_page}</span>
                            <div className="flex gap-2">
                                {messages.current_page > 1 && (
                                    <Button size="sm" onClick={() => {
                                        const url = new URL(window.location.href);
                                        url.searchParams.set('page', messages.current_page - 1);
                                        window.location.href = url.toString();
                                    }}>Previous</Button>
                                )}
                                {messages.current_page < messages.last_page && (
                                    <Button size="sm" onClick={() => {
                                        const url = new URL(window.location.href);
                                        url.searchParams.set('page', messages.current_page + 1);
                                        window.location.href = url.toString();
                                    }}>Next</Button>
                                )}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </DashboardLayout>
    );
}

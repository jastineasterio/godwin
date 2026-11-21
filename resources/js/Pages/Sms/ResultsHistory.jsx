import { usePage } from '@inertiajs/react';
import { Card, Input, Label, Badge, Button, Select, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { GraduationCap, Phone, Filter } from 'lucide-react';

export default function SmsResultsHistory({ messages, examinations, selected_examination }) {
    const { flash } = usePage().props;

    const smsStatusLabel = (status) => {
        switch (status) {
            case 'delivered': return 'Delivered';
            case 'sent': return 'Sent';
            case 'queued': return 'Queued';
            case 'failed': return 'Failed — Retry available';
            case 'submission_unknown': return 'Submission unknown — Verification required';
            default: return 'Pending';
        }
    };

    const smsStatusTone = (status) => {
        switch (status) {
            case 'delivered': return 'success';
            case 'sent': return 'primary';
            case 'queued': return 'warning';
            case 'failed': return 'danger';
            case 'submission_unknown': return 'warning';
            default: return 'secondary';
        }
    };

    return (
        <DashboardLayout title="Results SMS History" subtitle="View results notification history.">
            {flash && <Flash />}

            <div className="grid gap-6">
                <Card>
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <div>
                            <h3 className="font-display text-base font-bold text-ink">Results SMS History</h3>
                            <p className="text-xs text-slate-500">History of results notifications sent to parents.</p>
                        </div>
                    </header>

                    <div className="mb-6">
                        <Label>Filter by Examination</Label>
                        <Select value={selected_examination || ''} onChange={(e) => {
                            const url = new URL(window.location.href);
                            if (e.target.value) {
                                url.searchParams.set('examination_id', e.target.value);
                            } else {
                                url.searchParams.delete('examination_id');
                            }
                            window.location.href = url.toString();
                        }}>
                            <option value="">All Examinations</option>
                            {examinations?.map((ex) => (
                                <option key={ex.id} value={ex.id}>{ex.name}</option>
                            ))}
                        </Select>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Student</th>
                                    <th className="pb-3 font-bold">Parent / Guardian</th>
                                    <th className="pb-3 font-bold">Phone</th>
                                    <th className="pb-3 font-bold">Message</th>
                                    <th className="pb-3 font-bold">Status</th>
                                    <th className="pb-3 font-bold">Sent By</th>
                                    <th className="pb-3 font-bold">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {messages?.data?.map((m) => (
                                    <tr key={m.id} className="hover:bg-canvas">
                                        <td className="py-3 text-slate-600">{m.student_name || `Student #${m.student_id}` || '—'}</td>
                                        <td className="py-3 text-slate-600">{m.parent_name || `Parent #${m.parent_id}` || '—'}</td>
                                        <td className="py-3 font-mono text-slate-600">{m.to}</td>
                                        <td className="py-3 text-slate-600 truncate max-w-xs">{m.message}</td>
                                        <td className="py-3">
                                            <Badge tone={smsStatusTone(m.status)}>
                                                {smsStatusLabel(m.status)}
                                                {m.is_resend && (
                                                    <span className="ml-1 text-xs opacity-80">(retry)</span>
                                                )}
                                            </Badge>
                                        </td>
                                        <td className="py-3 text-slate-600">{m.sent_by}</td>
                                        <td className="py-3 text-slate-600">{m.sent_at_formatted}</td>
                                    </tr>
                                ))}
                                {!messages?.data?.length && (
                                    <tr>
                                        <td colSpan="7" className="py-4 text-center text-slate-500">No results SMS history found.</td>
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

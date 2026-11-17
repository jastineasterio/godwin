import { usePage } from '@inertiajs/react';
import { Card, Button, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Phone, RefreshCw } from 'lucide-react';

export default function SmsSenderIds({ sender_ids, error }) {
    const { flash } = usePage().props;

    const handleRefresh = () => {
        window.location.reload();
    };

    return (
        <DashboardLayout title="Sender IDs" subtitle="Approved sender IDs from the SMS provider.">
            {flash && <Flash />}

            <div className="grid gap-6">
                <Card className="col-span-1 xl:col-span-4">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Approved Sender IDs</h3>
                    </header>
                    {error ? (
                        <div className="p-4 text-center text-rose-600">
                            <p>{error}</p>
                            <Button onClick={handleRefresh}>Retry</Button>
                        </div>
                    ) : sender_ids?.length ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        <th className="pb-3 font-bold">Sender ID</th>
                                        <th className="pb-3 font-bold">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {sender_ids.map((sid) => (
                                        <tr key={sid.senderId || sid.id} className="hover:bg-canvas">
                                            <td className="py-3 font-bold text-ink">{sid.senderId || sid.id}</td>
                                            <td className="py-3">
                                                <Badge tone={sid.status === 'approved' || sid.status === 'ACTIVE' ? 'success' : 'warning'}>
                                                    {sid.status || 'Unknown'}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="py-4 text-center text-slate-500">No sender IDs found.</div>
                    )}
                </Card>
            </div>
        </DashboardLayout>
    );
}

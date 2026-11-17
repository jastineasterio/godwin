import { usePage } from '@inertiajs/react';
import { Button, Card, Input, Label, Badge, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Banknote, Phone, History, RefreshCw } from 'lucide-react';

export default function SmsCredits({ balance, currency, balance_error, purchases, pagination, can_purchase }) {
    const { flash } = usePage().props;

    return (
        <DashboardLayout title="SMS Credits" subtitle="Purchase and manage SMS credits via DARSMS.">
            {flash && <Flash />}

            {/* Balance Card */}
            <div className="grid gap-6 mb-6">
                <Card className="col-span-1 xl:col-span-3">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">SMS Balance</h3>
                    </header>
                    <div className="p-4 rounded-xl bg-canvas border border-slate-100">
                        {balance_error ? (
                            <div className="py-4 text-center text-rose-600">
                                <Badge tone="danger">{balance_error}</Badge>
                            </div>
                        ) : (
                            <div className="text-center">
                                <p className="text-xs font-bold uppercase tracking-wider text-slate-400">Available Credits</p>
                                <p className="mt-1 font-display text-3xl font-bold text-ink">{balance !== null ? balance : '—'} {currency}</p>
                                {balance !== null && (
                                    <p className="mt-2 text-xs text-slate-500">Credits are added by the provider after payment confirmation.</p>
                                )}
                            </div>
                        )}
                    </div>
                </Card>

                {/* Purchase Form */}
                <Card className="col-span-1 xl:col-span-3">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Purchase SMS Credits</h3>
                    </header>
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        const formData = new FormData(e.target);
                        fetch(route('sms.admin.credits.purchase'), {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: formData,
                        }).then(async (r) => {
                            const data = await r.json();
                            if (r.ok && data.success) {
                                alert(data.message || 'Purchase initiated.');
                                window.location.reload();
                            } else {
                                alert(data.error || 'Purchase failed.');
                            }
                        });
                    }}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Quantity (SMS credits)</Label>
                                <Input type="number" name="quantity_sms" required min="1" placeholder="1000" />
                            </div>
                            <div>
                                <Label>Payer Phone Number</Label>
                                <Input name="payer_phone" required placeholder="2557XXXXXXXX" />
                                <p className="mt-1 text-xs text-slate-500">Tanzanian format: 2557XXXXXXXX</p>
                            </div>
                            <div className="col-span-2 sm:col-span-2">
                                <Button type="submit" disabled={!can_purchase}>
                                    <Banknote className="h-4 w-4 mr-2" /> Purchase Credits
                                </Button>
                                <p className="mt-2 text-xs text-slate-500">Credits will be added by DARSMS after payment confirmation. The amount and unit price are determined by the provider.</p>
                            </div>
                        </div>
                    </form>
                </Card>
            </div>

            {/* Purchase History */}
            <Card>
                <header className="mb-4 flex items-center justify-between gap-2">
                    <div>
                        <h3 className="font-display text-base font-bold text-ink">Purchase History</h3>
                        <p className="text-xs text-slate-500">All credit purchase transactions.</p>
                    </div>
                    <Button size="sm">
                        <History className="h-4 w-4" /> Refresh
                    </Button>
                </header>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                <th className="pb-3 font-bold">Date</th>
                                <th className="pb-3 font-bold">Transaction Ref</th>
                                <th className="pb-3 font-bold">Quantity</th>
                                <th className="pb-3 font-bold">Amount</th>
                                <th className="pb-3 font-bold">Unit Price</th>
                                <th className="pb-3 font-bold">Status</th>
                                <th className="pb-3 font-bold">Payer Phone</th>
                                <th className="pb-3 font-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {purchases?.map((p) => (
                                <tr key={p.transaction_ref || p.id} className="transition hover:bg-canvas">
                                    <td className="py-3 text-slate-600">{p.created_at || '—'}</td>
                                    <td className="py-3 text-slate-600 truncate max-w-xs">{p.transaction_ref || '—'}</td>
                                    <td className="py-3 font-bold text-ink">{p.quantity_sms}</td>
                                    <td className="py-3 text-slate-600">{p.amount !== null ? p.amount : '—'}</td>
                                    <td className="py-3 text-slate-600">{p.unit_price !== null ? p.unit_price : '—'}</td>
                                    <td className="py-3">
                                        <Badge tone={
                                            p.provider_status === 'SUCCESS' ? 'success' :
                                            p.provider_status === 'PENDING' ? 'warning' :
                                            'danger'
                                        }>{p.provider_status}</Badge>
                                    </td>
                                    <td className="py-3 text-slate-600">{p.payer_phone || '—'}</td>
                                    <td className="py-3">
                                        {(p.provider_status === 'PENDING' || p.provider_status === 'FAILED') && (
                                            <form onSubmit={(e) => {
                                                e.preventDefault();
                                                const formData = new FormData(e.target);
                                                fetch(route('sms.admin.credits.retry', p.provider_purchase_id || p.id), {
                                                    method: 'POST',
                                                    headers: {
                                                        'X-Requested-With': 'XMLHttpRequest',
                                                        'Accept': 'application/json',
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                                    },
                                                    body: formData,
                                                }).then(async (r) => {
                                                    const data = await r.json();
                                                    if (r.ok && data.success) {
                                                        alert('Retry initiated.');
                                                        window.location.reload();
                                                    } else {
                                                        alert(data.error || 'Retry failed.');
                                                    }
                                                });
                                            }}>
                                                <Button size="sm">
                                                    <RefreshCw className="h-3 w-3" /> Retry
                                                </Button>
                                            </form>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {!purchases?.length && (
                                <tr>
                                    <td colSpan="8" className="py-4 text-center text-slate-500">No purchase history found.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {pagination && (
                    <div className="mt-4 flex items-center justify-between text-xs text-slate-500">
                        <span>Page {pagination.page} of {Math.ceil(pagination.total / pagination.limit)}</span>
                        <span>{pagination.total} total records</span>
                    </div>
                )}
            </Card>
        </DashboardLayout>
    );
}

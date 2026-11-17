import { usePage } from '@inertiajs/react';
import { Card, StatCard, Badge, Button, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Phone, Banknote, RefreshCw } from 'lucide-react';

export default function SmsBalance({ balance, currency, error }) {
    const { flash } = usePage().props;

    const handleRefresh = () => {
        window.location.reload();
    };

    return (
        <DashboardLayout title="SMS Balance" subtitle="View available SMS credits from the provider.">
            {flash && <Flash />}

            <div className="grid gap-6">
                <Card className="col-span-1 xl:col-span-4">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Available Credits</h3>
                    </header>
                    <div className="p-6 rounded-xl bg-canvas border border-slate-100 text-center">
                        {error ? (
                            <div className="space-y-2">
                                <Badge tone="danger">{error}</Badge>
                                <Button onClick={handleRefresh} size="sm">
                                    <RefreshCw className="h-4 w-4" /> Retry
                                </Button>
                            </div>
                        ) : (
                            <div>
                                <p className="text-xs font-bold uppercase tracking-wider text-slate-400">SMS Balance</p>
                                <p className="mt-2 font-display text-4xl font-bold text-ink">{balance !== null ? balance : '—'} {currency || ''}</p>
                                <p className="mt-4 text-xs text-slate-500">Balance is fetched live from the provider.</p>
                            </div>
                        )}
                    </div>
                </Card>
            </div>
        </DashboardLayout>
    );
}

import { ChevronLeft, Package } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge } from '../../Components/ui';
import { PageHeader } from '../../Components/page';

const money = (value) => `TZS ${Number(value ?? 0).toLocaleString()}`;

const CONDITION_TONE = { new: 'success', good: 'secondary', fair: 'warning', damaged: 'danger', missing: 'danger' };
const STATUS_TONE = { active: 'success', in_use: 'secondary', maintenance: 'warning', disposed: 'muted', missing: 'danger' };

export default function FacilitiesShow({ asset }) {
    const totalValue = asset.purchase_price ? asset.purchase_price * asset.quantity : 0;

    return (
        <DashboardLayout
            title="Asset Details"
            subtitle={`${asset.name} — inventory record`}
        >
            <PageHeader
                title="Asset Details"
                subtitle={`Category: ${asset.category} · Status: ${asset.status}`}
                back="/facilities"
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="glass-card p-5 sm:p-6">
                    <h3 className="font-display text-base font-bold text-ink mb-1">{asset.name}</h3>
                    <p className="text-xs text-slate-500 mb-3">
                        {asset.asset_code ?? 'No inventory code'}
                    </p>
                    {asset.description && (
                        <p className="text-sm text-slate-600 mb-4">{asset.description}</p>
                    )}
                    <div className="flex items-center gap-2 mb-2">
                        <Package className="h-4 w-4 text-slate-400" />
                        <span className="text-sm text-slate-600 font-bold">{asset.quantity} unit(s)</span>
                    </div>
                    <div className="flex items-center gap-2 mb-2">
                        <Info className="h-4 w-4 text-slate-400" />
                        <span className="text-sm text-slate-600">{asset.location ?? 'No location assigned'}</span>
                    </div>
                    <div className="mt-3">
                        <Badge tone={CONDITION_TONE[asset.condition] ?? 'muted'} className="mr-2">
                            {asset.condition}
                        </Badge>
                        <Badge tone={STATUS_TONE[asset.status] ?? 'muted'}>
                            {asset.status.replace('_', ' ')}
                        </Badge>
                    </div>
                </div>

                <div className="glass-card p-5 sm:p-6 sm:col-span-2">
                    <h3 className="font-display text-base font-bold text-ink mb-3">Purchase Information</h3>
                    <div className="space-y-3">
                        <div className="flex justify-between text-sm">
                            <span className="text-slate-500">Purchase Date</span>
                            <span className="text-ink">{asset.purchase_date ?? '—'}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-slate-500">Purchase Price (unit)</span>
                            <span className="text-ink font-mono">{asset.purchase_price ? money(asset.purchase_price) : '—'}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-slate-500">Total Value</span>
                            <span className="text-ink font-mono font-bold">{money(totalValue)}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-slate-500">Supplier</span>
                            <span className="text-slate-600">{asset.supplier ?? '—'}</span>
                        </div>
                        {asset.image_path && (
                            <div className="flex justify-between text-sm">
                                <span className="text-slate-500">Image / Document</span>
                                <span className="text-slate-600 truncate max-w-xs">{asset.image_path}</span>
                            </div>
                        )}
                    </div>
                </div>

                <div className="glass-card p-5 sm:p-6 sm:col-span-2">
                    <h3 className="font-display text-base font-bold text-ink mb-3">Record Information</h3>
                    <div className="space-y-3">
                        <div className="flex justify-between text-sm">
                            <span className="text-slate-500">Recorded By</span>
                            <span className="text-ink">{asset.recorded_by ?? '—'}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-slate-500">Registered</span>
                            <span className="text-slate-600">{asset.created_at}</span>
                        </div>
                    </div>
                    <div className="mt-4">
                        <p className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Condition History</p>
                        <p className="text-sm text-slate-600">
                            Current condition: <span className="text-ink">{asset.condition}</span>.
                            Changes are logged in the audit trail.
                        </p>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

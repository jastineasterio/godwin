import { useState } from 'react';
import { usePage, useForm } from '@inertiajs/react';
import { Button, Card, Input, Label, Badge, Switch, Textarea, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Plus, Phone, Shield, Info, Key, Link, RefreshCw } from 'lucide-react';

export default function SmsProviderSettings({ configs, active_provider, capabilities, available_providers }) {
    const { flash } = usePage().props;
    const [editingId, setEditingId] = useState(null);

    const form = useForm({
        name: '',
        provider_type: 'darsms',
        base_url: 'https://dev.darsms.co.tz/api/v1',
        auth_header_name: 'X-API-Key',
        api_key: '',
        api_secret: '',
        username: '',
        password: '',
        sender_id: '',
        is_active: false,
        timeout_seconds: 30,
    });

    const handleSave = (e) => {
        e.preventDefault();

        if (editingId) {
            form.put(route('sms.admin.providers.update', editingId)).then(() => setEditingId(null));
        } else {
            form.post(route('sms.admin.providers.store')).then(() => setEditingId(null));
        }
    };

    const handleUpdate = (id) => {
        const config = configs.find(c => c.id === id);
        if (!config) return;
        setEditingId(id);
        form.setData({
            name: config.name,
            provider_type: config.provider_type,
            base_url: config.base_url || '',
            auth_header_name: config.auth_header_name || 'X-API-Key',
            sender_id: config.sender_id || '',
            is_active: config.is_active,
            timeout_seconds: config.timeout_seconds || 30,
        });
    };

    const handleToggle = (id) => {
        const config = configs.find(c => c.id === id);
        fetch(route('sms.admin.providers.status', config), {
            method: 'PATCH',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ is_active: !config.is_active }),
        }).then(() => window.location.reload());
    };

    const handleTestConnection = () => {
        fetch(route('sms.admin.test-connection'), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        }).then(async (r) => {
            const data = await r.json();
            alert(data.success ? (data.message || 'Connected successfully.') : (data.error || 'Connection failed.'));
            if (data.success) window.location.reload();
        });
    };

    const handleDelete = (id) => {
        if (!confirm('Are you sure you want to delete this provider configuration?')) return;
        fetch(route('sms.admin.providers.destroy', id), {
            method: 'DELETE',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        }).then(() => window.location.reload());
    };

    return (
        <DashboardLayout title="SMS Provider Settings" subtitle="Configure SMS providers, credentials, and connection settings.">
            {flash && <Flash />}

            {/* Active Provider Overview */}
            <div className="grid gap-6 mb-6">
                <Card className="col-span-1 xl:col-span-3">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Active Provider</h3>
                    </header>
                    <div className="p-4 rounded-xl bg-canvas border border-slate-100">
                        {active_provider ? (
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-slate-600">Provider</span>
                                    <span className="text-sm font-bold text-ink">{active_provider.name}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-slate-600">Type</span>
                                    <span className="text-sm text-slate-600">{active_provider.type}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-slate-600">Status</span>
                                    <Badge tone="success">Active</Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-slate-600">Base URL</span>
                                    <span className="text-sm text-slate-600 truncate max-w-xs">{active_provider.base_url}</span>
                                </div>
                                <div className="flex items-center justify-between pt-3">
                                    <span className="text-sm text-slate-600">Capabilities</span>
                                    <div className="flex flex-wrap gap-2">
                                        {Object.entries(capabilities).map(([key, supported]) => (
                                            <Badge key={key} tone={supported ? 'success' : 'muted'} className="text-xs">{key.replace(/_/g, ' ')}: {supported ? 'Yes' : 'No'}</Badge>
                                        ))}
                                    </div>
                                </div>
                                <div className="pt-4 border-t border-slate-100 flex items-center gap-2">
                                    <Button size="sm" onClick={handleTestConnection}>
                                        <RefreshCw className="h-4 w-4" /> Test Connection
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className="py-4 text-center text-slate-500">No active SMS provider configured.</div>
                        )}
                    </div>
                </Card>

                <Card className="col-span-1 xl:col-span-3">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <div>
                            <h3 className="font-display text-base font-bold text-ink">Provider Configurations</h3>
                            <p className="text-xs text-slate-500">Encrypted credentials are never exposed to the frontend.</p>
                        </div>
                        <Button onClick={() => { form.reset(); setEditingId(null); }}>
                            <Plus className="h-4 w-4" /> Add Provider
                        </Button>
                    </header>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Name</th>
                                    <th className="pb-3 font-bold">Type</th>
                                    <th className="pb-3 font-bold">Status</th>
                                    <th className="pb-3 font-bold">Credentials</th>
                                    <th className="pb-3 font-bold">Sender ID</th>
                                    <th className="pb-3 font-bold">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {configs?.map((config) => (
                                    <tr key={config.id} className="transition hover:bg-canvas">
                                        <td className="py-3 font-bold text-ink">{config.name}</td>
                                        <td className="py-3 text-slate-600">{config.provider_type}</td>
                                        <td className="py-3">
                                            <Badge tone={config.is_active ? 'success' : 'muted'}>{config.is_active ? 'Active' : 'Inactive'}</Badge>
                                        </td>
                                        <td className="py-3 text-slate-600">
                                            {config.has_credentials ? (
                                                <span className="flex items-center gap-2">
                                                    <Badge tone="secondary">{config.masked_api_key}</Badge>
                                                    <Button size="sm" variant="outline" onClick={() => handleUpdate(config.id)}>
                                                        <Key className="h-3 w-3" /> Edit
                                                    </Button>
                                                </span>
                                            ) : (
                                                <span className="text-xs text-slate-400">Not configured</span>
                                            )}
                                        </td>
                                        <td className="py-3 text-slate-600">{config.sender_id || '—'}</td>
                                        <td className="py-3">
                                            {config.is_active ? (
                                                <div className="flex items-center gap-2">
                                                    <Label className="text-xs flex items-center gap-1 cursor-pointer">
                                                        <Switch checked={config.is_active} onCheckedChange={(checked) => handleToggle(config.id)} />
                                                        Active
                                                    </Label>
                                                </div>
                                            ) : (
                                                <Button size="sm" onClick={() => handleToggle(config.id)}>Activate</Button>
                                            )}
                                            <Button size="sm" variant="outline" onClick={() => handleDelete(config.id)}>Delete</Button>
                                        </td>
                                    </tr>
                                ))}
                                {!configs?.length && (
                                    <tr>
                                        <td colSpan="6" className="py-4 text-center text-slate-500">No provider configurations found.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            {/* Add / Edit Provider Form */}
            <Card className="mt-6">
                <header className="mb-4 flex items-center justify-between gap-2">
                    <h3 className="font-display text-base font-bold text-ink">{form.name ? 'Edit Provider' : 'Add New Provider'}</h3>
                </header>
                <form onSubmit={handleSave} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="col-span-2 sm:col-span-1">
                        <Label>Provider Name</Label>
                        <Input value={form.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. DARSMS Production" />
                    </div>
                    <div className="col-span-2 sm:col-span-1">
                        <Label>Provider Type</Label>
                        <Input value={form.provider_type} onChange={(e) => form.setData('provider_type', e.target.value)} placeholder="darsms" />
                    </div>
                    <div className="col-span-2 sm:col-span-2">
                        <Label>Base URL</Label>
                        <Input value={form.base_url} onChange={(e) => form.setData('base_url', e.target.value)} placeholder="https://dev.darsms.co.tz/api/v1" />
                        <p className="mt-1 text-xs text-slate-500">Configurable per provider. Ask your SMS provider for the production URL.</p>
                    </div>
                    <div className="col-span-2 sm:col-span-1">
                        <Label>Auth Header Name</Label>
                        <Input value={form.auth_header_name} onChange={(e) => form.setData('auth_header_name', e.target.value)} placeholder="X-API-Key" />
                    </div>
                    <div className="col-span-2 sm:col-span-2">
                        <Label>API Key</Label>
                        <Input type="password" value={form.api_key} onChange={(e) => form.setData('api_key', e.target.value)} placeholder="••••••••" />
                        <p className="mt-1 text-xs text-slate-500">Stored encrypted. Never exposed to frontend.</p>
                    </div>
                    <div className="col-span-2 sm:col-span-1">
                        <Label>API Secret (optional)</Label>
                        <Input type="password" value={form.api_secret} onChange={(e) => form.setData('api_secret', e.target.value)} placeholder="••••••••" />
                        <p className="mt-1 text-xs text-slate-500">Optional for DARSMS.</p>
                    </div>
                    <div className="col-span-2 sm:col-span-1">
                        <Label>Username (optional)</Label>
                        <Input value={form.username} onChange={(e) => form.setData('username', e.target.value)} placeholder="Username" />
                    </div>
                    <div className="col-span-2 sm:col-span-1">
                        <Label>Password (optional)</Label>
                        <Input type="password" value={form.password} onChange={(e) => form.setData('password', e.target.value)} placeholder="••••••••" />
                    </div>
                    <div className="col-span-2 sm:col-span-1">
                        <Label>Default Sender ID</Label>
                        <Input value={form.sender_id} onChange={(e) => form.setData('sender_id', e.target.value)} placeholder="DARSMS" />
                    </div>
                    <div className="col-span-2 sm:col-span-1">
                        <Label>Timeout (seconds)</Label>
                        <Input type="number" value={form.timeout_seconds} onChange={(e) => form.setData('timeout_seconds', parseInt(e.target.value) || 30)} placeholder="30" />
                    </div>
                    <div className="col-span-2 sm:col-span-1">
                        <Label className="flex items-center gap-2">
                            <Switch checked={form.is_active} onCheckedChange={(v) => form.setData('is_active', v)} />
                            Set as Active Provider
                        </Label>
                        <p className="mt-1 text-xs text-slate-500">Only one provider can be active.</p>
                    </div>
                    <div className="col-span-2 sm:col-span-2 lg:col-span-4">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving...' : 'Save Provider'}
                        </Button>
                        <Button type="button" onClick={() => { form.reset(); setEditingId(null); }} variant="outline">Cancel</Button>
                    </div>
                </form>
            </Card>
        </DashboardLayout>
    );
}

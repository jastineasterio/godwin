import { usePage, useForm } from '@inertiajs/react';
import { Card, Input, Label, Badge, Button, Select, Textarea, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { FileText, ToggleRight, Trash2, Edit3, Play } from 'lucide-react';

export default function SmsTemplates({ templates, categories, can_manage }) {
    const { flash } = usePage().props;

    const form = useForm({
        name: '',
        category: 'general',
        template: '',
        is_active: true,
    }, {
        preserveState: false,
    });

    const handleSave = (e) => {
        e.preventDefault();
        const formData = new FormData();
        formData.append('name', form.name);
        formData.append('category', form.category);
        formData.append('template', form.template);
        formData.append('is_active', form.is_active ? '1' : '0');

        fetch(route('sms.templates.store'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: formData,
        }).then(async (r) => {
            if (r.ok) {
                alert('Template created.');
                window.location.reload();
            } else {
                const data = await r.json();
                alert(data.message || 'Failed to create template.');
            }
        });
    };

    const handleToggle = (id) => {
        fetch(route('sms.templates.toggle', id), {
            method: 'PATCH',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        }).then(() => window.location.reload());
    };

    const handleDelete = (id) => {
        if (!confirm('Delete this template?')) return;
        fetch(route('sms.templates.destroy', id), {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        }).then(() => window.location.reload());
    };

    return (
        <DashboardLayout title="SMS Templates" subtitle="Create and manage reusable SMS templates.">
            {flash && <Flash />}

            <div className="grid gap-6">
                <Card className="col-span-1 xl:col-span-4">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Create Template</h3>
                    </header>
                    <form onSubmit={handleSave}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="col-span-2">
                                <Label>Template Name</Label>
                                <Input value={form.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. Fee Reminder" />
                            </div>
                            <div className="col-span-2">
                                <Label>Category</Label>
                                <Select value={form.category} onChange={(e) => form.setData('category', e.target.value)}>
                                    {categories.map((c) => (
                                        <option key={c} value={c}>{c.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</option>
                                    ))}
                                </Select>
                            </div>
                            <div className="col-span-2">
                                <Label>Template Message</Label>
                                <Textarea value={form.template} onChange={(e) => form.setData('template', e.target.value)} rows={5} placeholder="Dear {parent_name}, ..." />
                                <p className="mt-1 text-xs text-slate-500">{'Allowed variables: {school_name}, {parent_name}, {student_name}, {class_name}, {term_name}, {exam_name}, {amount}, {date}'}</p>
                            </div>
                            <div className="col-span-2">
                                <Label className="flex items-center gap-2">
                                    <input type="checkbox" checked={form.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                                    Active
                                </Label>
                            </div>
                        </div>
                        <div className="col-span-2 pt-4">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Creating...' : 'Create Template'}
                            </Button>
                        </div>
                    </form>
                </Card>

                <Card className="col-span-1 xl:col-span-8">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Existing Templates</h3>
                    </header>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Name</th>
                                    <th className="pb-3 font-bold">Category</th>
                                    <th className="pb-3 font-bold">Template</th>
                                    <th className="pb-3 font-bold">Status</th>
                                    <th className="pb-3 font-bold">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {templates?.map((t) => (
                                    <tr key={t.id} className="hover:bg-canvas">
                                        <td className="py-3 font-bold text-ink">{t.name}</td>
                                        <td className="py-3 text-slate-600 capitalize">{t.category}</td>
                                        <td className="py-3 text-slate-600 truncate max-w-xs">{t.template}</td>
                                        <td className="py-3">
                                            <Badge tone={t.is_active ? 'success' : 'muted'}>
                                                {t.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </td>
                                        <td className="py-3">
                                            <Button size="sm" onClick={() => handleToggle(t.id)}>
                                                {t.is_active ? 'Deactivate' : 'Activate'}
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={() => handleDelete(t.id)}>
                                                <Trash2 className="h-3 w-3" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {!templates?.length && (
                                    <tr>
                                        <td colSpan="5" className="py-4 text-center text-slate-500">No templates found.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </DashboardLayout>
    );
}

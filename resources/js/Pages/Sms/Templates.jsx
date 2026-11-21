import { usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Card, Input, Label, Badge, Button, Select, Textarea, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { FileText, ToggleRight, Trash2, Edit3, Play, Loader2, Save, X } from 'lucide-react';

export default function SmsTemplates({ templates, categories, can_manage }) {
    const { flash } = usePage().props;

    const form = useForm(
        {
            id: null,
            name: '',
            category: 'general',
            template: '',
            is_active: true,
        },
        {
            preserveState: false,
        }
    );

    const [editingId, setEditingId] = useState(null);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [deletingId, setDeletingId] = useState(null);

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    const resetForm = () => {
        form.reset({
            id: null,
            name: '',
            category: 'general',
            template: '',
            is_active: true,
        });
        setEditingId(null);
        setError(null);
        setSuccess(null);
    };

    const startEdit = (template) => {
        form.reset({
            id: template.id,
            name: template.name,
            category: template.category,
            template: template.template,
            is_active: template.is_active,
        });
        setEditingId(template.id);
        setError(null);
        setSuccess(null);
    };

    const handleSave = async (e) => {
        e.preventDefault();
        setError(null);
        setSuccess(null);

        const isUpdate = editingId !== null;
        const url = isUpdate
            ? route('sms.templates.update', editingId)
            : route('sms.templates.store');
        const method = isUpdate ? 'PUT' : 'POST';

        const formData = new FormData();
        formData.append('name', form.name);
        formData.append('category', form.category);
        formData.append('template', form.template);
        formData.append('is_active', form.is_active ? '1' : '0');
        if (isUpdate) {
            formData.append('_method', 'PUT');
        }

        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            const data = await r.json();

            if (!r.ok || !data.success) {
                setError(data.message || (isUpdate ? 'Failed to update template.' : 'Failed to create template.'));
                if (data.errors) {
                    const first = Object.values(data.errors).flat()[0];
                    if (first) setError(Array.isArray(first) ? first[0] : first);
                }
                return;
            }

            setSuccess(data.message || (isUpdate ? 'Template updated.' : 'Template created.'));
            // Reload so the template list reflects the change and the form resets cleanly.
            setTimeout(() => window.location.reload(), 600);
        } catch (err) {
            setError('Unexpected error while saving the template.');
        }
    };

    const handleToggle = async (id) => {
        setError(null);
        setSuccess(null);

        try {
            const r = await fetch(route('sms.templates.toggle', id), {
                method: 'PATCH',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            const data = await r.json();

            if (!r.ok || !data.success) {
                setError(data.message || 'Failed to update template status.');
                return;
            }

            setSuccess('Template status updated.');
            // Reflect locally without a full reload.
            setTemplateList((prev) =>
                prev.map((t) => (t.id === id ? { ...t, is_active: data.template.is_active } : t))
            );
        } catch (err) {
            setError('Unexpected error while toggling the template.');
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this template? This cannot be undone.')) return;

        setDeletingId(id);
        setError(null);
        setSuccess(null);

        try {
            const r = await fetch(route('sms.templates.destroy', id), {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            const data = await r.json();

            if (!r.ok || !data.success) {
                setDeletingId(null);
                setError(data.message || 'Failed to delete template.');
                return;
            }

            setSuccess('Template deleted.');
            setTemplateList((prev) => prev.filter((t) => t.id !== id));
            if (editingId === id) resetForm();
            setDeletingId(null);
        } catch (err) {
            setDeletingId(null);
            setError('Unexpected error while deleting the template.');
        }
    };

    const safeTemplates = Array.isArray(templates) ? templates : [];

    const [templateList, setTemplateList] = useState(safeTemplates);

    return (
        <DashboardLayout title="SMS Templates" subtitle="Create and manage reusable SMS templates.">
            {flash && <Flash />}

            {success && (
                <Card className="mb-6 bg-emerald-50 border border-emerald-200 p-4">
                    <div className="flex items-start gap-3">
                        <Play className="mt-0.5 h-5 w-5 text-emerald-600 shrink-0" />
                        <div>
                            <p className="font-semibold text-emerald-800">{success}</p>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setSuccess(null)}
                                className="mt-2 text-emerald-600 border-emerald-200 hover:bg-emerald-100"
                            >
                                Dismiss
                            </Button>
                        </div>
                    </div>
                </Card>
            )}

            {!can_manage && (
                <Card className="mb-6">
                    <div className="p-4 text-center text-rose-600">
                        <p>You are not authorized to manage SMS templates.</p>
                    </div>
                </Card>
            )}

            <div className="grid gap-6">
                <Card className="col-span-1 xl:col-span-4">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">
                            {editingId ? 'Edit Template' : 'Create Template'}
                        </h3>
                    </header>
                    <form onSubmit={handleSave}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="col-span-2">
                                <Label>Template Name</Label>
                                <Input
                                    value={form.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="e.g. Fee Reminder"
                                    disabled={form.processing}
                                />
                            </div>
                            <div className="col-span-2">
                                <Label>Category</Label>
                                <Select
                                    value={form.category}
                                    onChange={(e) => form.setData('category', e.target.value)}
                                    disabled={form.processing}
                                >
                                    {categories.map((c) => (
                                        <option key={c} value={c}>
                                            {c.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <div className="col-span-2">
                                <Label>Template Message</Label>
                                <Textarea
                                    value={form.template}
                                    onChange={(e) => form.setData('template', e.target.value)}
                                    rows={5}
                                    placeholder="Dear {parent_name}, ..."
                                    disabled={form.processing}
                                />
                                <p className="mt-1 text-xs text-slate-500">
                                    {(form.template ?? '').length} / 1600 characters. Allowed variables:{' '}
                                    {`{school_name}, {parent_name}, {student_name}, {class_name}, {term_name}, {exam_name}, {amount}, {date}`}
                                </p>
                            </div>
                            <div className="col-span-2">
                                <Label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={form.is_active}
                                        onChange={(e) => form.setData('is_active', e.target.checked)}
                                        disabled={form.processing}
                                    />
                                    Active
                                </Label>
                            </div>
                        </div>
                        <div className="col-span-2 pt-4 flex gap-2">
                            {editingId ? (
                                <>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing ? (
                                            <>
                                                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                                Updating...
                                            </>
                                        ) : (
                                            <>
                                                <Save className="w-4 h-4 mr-2" />
                                                Update Template
                                            </>
                                        )}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={resetForm}
                                        disabled={form.processing}
                                    >
                                        <X className="w-4 h-4 mr-2" />
                                        Cancel
                                    </Button>
                                </>
                            ) : (
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? (
                                        <>
                                            <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                            Creating...
                                        </>
                                    ) : (
                                        <>
                                            <Save className="w-4 h-4 mr-2" />
                                            Create Template
                                        </>
                                    )}
                                </Button>
                            )}
                        </div>
                    </form>

                    {error && (
                        <div className="mt-4 p-3 rounded bg-rose-50 border border-rose-100">
                            <p className="text-xs text-rose-700">{error}</p>
                        </div>
                    )}
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
                                {templateList?.map((t) => (
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
                                            {can_manage && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        onClick={() => startEdit(t)}
                                                        disabled={form.processing || deletingId === t.id}
                                                    >
                                                        {deletingId === t.id ? (
                                            <Loader2 className="w-3 h-3 mr-1 animate-spin" />
                                                        ) : (
                                            <Edit3 className="w-3 h-3" />
                                                        )}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleToggle(t.id)}
                                                        disabled={form.processing || deletingId === t.id}
                                                    >
                                                        {t.is_active ? 'Deactivate' : 'Activate'}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleDelete(t.id)}
                                                        disabled={form.processing || deletingId === t.id}
                                                    >
                                                        <Trash2 className="w-3 h-3" />
                                                    </Button>
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                 {!templateList?.length && (
                                    <tr>
                                        <td colSpan="5" className="py-4 text-center text-slate-500">
                                            No templates found.
                                        </td>
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

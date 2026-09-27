import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState } from '../../Components/ui';
import { ConfirmDialog, Modal } from '../../Components/modal';
import { Checkbox, Field, Select, TextInput, Textarea } from '../../Components/form';
import { PageHeader, Pagination, PrimaryAction } from '../../Components/page';

const TONES = {
    tuition: 'primary', transport: 'secondary', feeding: 'accent',
    daycare: 'secondary', uniform: 'muted', exam: 'muted', other: 'muted',
};

/** FEE STRUCTURE CONFIGURATION — what the school bills, and how often. */
export default function FeeStructures({ fees, types, frequencies, classes, years, filters }) {
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const blank = {
        id: null, name: '', type: 'tuition', class_id: '',
        academic_year_id: years?.[0]?.id ?? '', amount: '', frequency: 'termly',
        description: '', is_active: true,
    };

    const form = useForm(blank);

    const openCreate = () => {
        form.setData(blank);
        form.clearErrors();
        setEditing('new');
    };

    const openEdit = (fee) => {
        form.setData({
            id: fee.id, name: fee.name, type: fee.type, class_id: fee.class_id ?? '',
            academic_year_id: fee.academic_year_id ?? '', amount: fee.amount,
            frequency: fee.frequency, description: fee.description ?? '', is_active: fee.is_active,
        });
        form.clearErrors();
        setEditing(fee);
    };

    const submit = (event) => {
        event.preventDefault();

        if (editing === 'new') {
            form.post('/fee-structures', { preserveScroll: true, onSuccess: () => setEditing(null) });
        } else {
            form.put(`/fee-structures/${editing.id}`, { preserveScroll: true, onSuccess: () => setEditing(null) });
        }
    };

    return (
        <DashboardLayout title="Finance" subtitle="Fee configuration for the school.">
            <PageHeader
                title="Fee Structures"
                subtitle="Tuition, transport, feeding, daycare and other charges."
                back="/invoices"
                action={<PrimaryAction onClick={openCreate} icon={Plus}>Add Fee</PrimaryAction>}
            />

            <div className="mb-4">
                <select
                    className="input sm:w-56"
                    value={filters.type ?? ''}
                    onChange={(e) =>
                        router.get('/fee-structures', { type: e.target.value }, { preserveState: true, replace: true })
                    }
                >
                    <option value="">All fee types</option>
                    {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                </select>
            </div>

            <div className="glass-card p-4 sm:p-5">
                {fees.data.length === 0 ? (
                    <EmptyState title="No fee structures yet" hint="Add tuition, feeding or transport fees to start invoicing." />
                ) : (
                    <>
                        {/* ---------------- Desktop table ---------------- */}
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        {['Fee', 'Type', 'Applies To', 'Frequency', 'Amount', 'Status', ''].map((h) => (
                                            <th key={h} className="whitespace-nowrap pb-3 font-bold">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {fees.data.map((fee) => (
                                        <tr key={fee.id} className="transition hover:bg-canvas/60">
                                            <td className="py-3 font-bold text-ink">{fee.name}</td>
                                            <td className="py-3"><Badge tone={TONES[fee.type] ?? 'muted'}>{fee.type_label}</Badge></td>
                                            <td className="py-3 text-slate-600">{fee.class}</td>
                                            <td className="py-3 text-slate-600">{fee.frequency_label}</td>
                                            <td className="py-3 font-bold text-ink">TZS {fee.amount.toLocaleString()}</td>
                                            <td className="py-3">
                                                <Badge tone={fee.is_active ? 'success' : 'muted'}>
                                                    {fee.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    <button type="button" onClick={() => openEdit(fee)} title="Edit"
                                                        className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-primary">
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                    <button type="button" onClick={() => setDeleting(fee)} title="Delete"
                                                        className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* ---------------- Mobile cards ---------------- */}
                        <ul className="space-y-3 sm:hidden">
                            {fees.data.map((fee) => (
                                <li key={fee.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="font-bold text-ink">{fee.name}</p>
                                        <Badge tone={fee.is_active ? 'success' : 'muted'}>
                                            {fee.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">{fee.class} · {fee.frequency_label}</p>
                                    <p className="mt-2 font-display text-lg font-extrabold text-primary">
                                        TZS {fee.amount.toLocaleString()}
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <button type="button" onClick={() => openEdit(fee)} className="btn-ghost px-3 py-1.5 text-xs">
                                            Edit
                                        </button>
                                        <button type="button" onClick={() => setDeleting(fee)}
                                            className="btn bg-rose-50 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-100">
                                            Delete
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <Pagination meta={fees.meta} onPage={() => {}} />
            </div>

            <FeeModal
                open={!!editing}
                isNew={editing === 'new'}
                form={form}
                types={types}
                frequencies={frequencies}
                classes={classes}
                years={years}
                onClose={() => setEditing(null)}
                onSubmit={submit}
            />

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    router.delete(`/fee-structures/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Delete fee structure?"
                message={`"${deleting?.name ?? ''}" will be removed. Fees already used on invoices are deactivated instead of deleted.`}
                confirmLabel="Delete"
            />
        </DashboardLayout>
    );
}

/** Create / edit modal for a single fee structure. */
function FeeModal({ open, isNew, form, types, frequencies, classes, years, onClose, onSubmit }) {
    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isNew ? 'Add Fee Structure' : 'Edit Fee Structure'}
            subtitle="Fees marked 'All classes' apply to every student."
            size="lg"
            footer={
                <>
                    <button type="button" onClick={onClose} className="btn-ghost">Cancel</button>
                    <button type="button" onClick={onSubmit} disabled={form.processing} className="btn-primary">
                        {form.processing ? 'Saving…' : 'Save Fee'}
                    </button>
                </>
            }
        >
            <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
                <Field label="Fee Name" required error={form.errors.name} className="sm:col-span-2">
                    <TextInput
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="e.g. Termly Tuition"
                    />
                </Field>

                <Field label="Fee Type" required error={form.errors.type}>
                    <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                    </Select>
                </Field>

                <Field label="Amount (TZS)" required error={form.errors.amount}>
                    <TextInput
                        type="number" min="0" step="any"
                        value={form.data.amount}
                        onChange={(e) => form.setData('amount', e.target.value)}
                    />
                </Field>

                <Field label="Applies To" error={form.errors.class_id} hint="Leave blank for all classes.">
                    <Select value={form.data.class_id} onChange={(e) => form.setData('class_id', e.target.value)}>
                        <option value="">All classes</option>
                        {classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </Select>
                </Field>

                <Field label="Frequency" required error={form.errors.frequency}>
                    <Select value={form.data.frequency} onChange={(e) => form.setData('frequency', e.target.value)}>
                        {frequencies.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
                    </Select>
                </Field>

                <Field label="Academic Year" error={form.errors.academic_year_id} required={false}>
                    <Select
                        value={form.data.academic_year_id}
                        onChange={(e) => form.setData('academic_year_id', e.target.value)}
                    >
                        <option value="">— All years —</option>
                        {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                    </Select>
                </Field>

                <div className="flex items-end pb-2">
                    <Checkbox
                        label="Active"
                        description="Inactive fees are hidden from new invoices."
                        checked={!!form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                    />
                </div>

                <div className="sm:col-span-2">
                    <Field label="Description" error={form.errors.description} required={false}>
                        <Textarea
                            rows={2}
                            value={form.data.description ?? ''}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                    </Field>
                </div>
            </form>
        </Modal>
    );
}
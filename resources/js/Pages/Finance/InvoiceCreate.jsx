import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Card } from '../../Components/ui';
import { Field, FormActions, Select, TextInput, Textarea } from '../../Components/form';
import { PageHeader } from '../../Components/page';

/**
 * INVOICE GENERATOR — pick a student and the applicable fee structures are
 * suggested automatically (their class fees + universal fees). Line items can
 * be added, edited or removed before the invoice is issued.
 */
export default function InvoiceCreate({ students, activeStudentId, suggestedFees, terms, years }) {
    const [items, setItems] = useState([]);

    const form = useForm({
        student_id: activeStudentId || '',
        academic_year_id: years?.find((y) => y.is_current)?.id ?? years?.[0]?.id ?? '',
        term_id: terms?.find((t) => t.is_current)?.id ?? terms?.[0]?.id ?? '',
        issue_date: new Date().toISOString().slice(0, 10),
        due_date: new Date(Date.now() + 30 * 864e5).toISOString().slice(0, 10),
        discount: 0,
        notes: '',
        items: [],
    });

    // Load the suggested fees whenever the server sends a new selection
    useEffect(() => {
        const seeded = suggestedFees.map((fee) => ({
            fee_structure_id: fee.id,
            description: fee.name,
            quantity: 1,
            amount: fee.amount,
        }));

        setItems(seeded);
        form.setData('items', seeded);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [suggestedFees]);

    const changeStudent = (studentId) => {
        form.setData('student_id', studentId);
        router.get('/invoices/create', { student_id: studentId }, { preserveScroll: true, preserveState: true });
    };

    const updateItem = (index, key, value) => {
        const next = items.map((item, i) => (i === index ? { ...item, [key]: value } : item));
        setItems(next);
        form.setData('items', next);
    };

    const removeItem = (index) => {
        const next = items.filter((_, i) => i !== index);
        setItems(next);
        form.setData('items', next);
    };

    const addItem = () => {
        const next = [...items, { fee_structure_id: '', description: '', quantity: 1, amount: 0 }];
        setItems(next);
        form.setData('items', next);
    };

    const submit = (event) => {
        event.preventDefault();
        form.post('/invoices');
    };

    const subtotal = items.reduce((sum, i) => sum + Number(i.amount || 0) * Number(i.quantity || 0), 0);
    const total = Math.max(0, subtotal - Number(form.data.discount || 0));

    return (
        <DashboardLayout title="Finance" subtitle="Generate an invoice for a student.">
            <PageHeader
                title="Generate Invoice"
                subtitle="Fees are suggested from the active fee structures."
                back="/invoices"
            />

            <form onSubmit={submit}>
                <div className="grid gap-5 lg:grid-cols-3">
                    {/* ---------------- Invoice meta ---------------- */}
                    <Card title="Invoice Details" className="lg:col-span-1">
                        <div className="space-y-4">
                            <Field label="Student" required error={form.errors.student_id}>
                                <Select value={form.data.student_id} onChange={(e) => changeStudent(e.target.value)}>
                                    <option value="">Select student…</option>
                                    {students.map((s) => (
                                        <option key={s.id} value={s.id}>{s.name} ({s.reg_no})</option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label="Academic Year" error={form.errors.academic_year_id} required={false}>
                                <Select value={form.data.academic_year_id}
                                    onChange={(e) => form.setData('academic_year_id', e.target.value)}>
                                    {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                </Select>
                            </Field>

                            <Field label="Term" error={form.errors.term_id} required={false}>
                                <Select value={form.data.term_id} onChange={(e) => form.setData('term_id', e.target.value)}>
                                    <option value="">— None —</option>
                                    {terms.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.name}{t.is_current ? ' (current)' : ''}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label="Issue Date" required error={form.errors.issue_date}>
                                <TextInput type="date" value={form.data.issue_date}
                                    onChange={(e) => form.setData('issue_date', e.target.value)} />
                            </Field>

                            <Field label="Due Date" required error={form.errors.due_date}>
                                <TextInput type="date" value={form.data.due_date}
                                    onChange={(e) => form.setData('due_date', e.target.value)} />
                            </Field>

                            <Field label="Discount (TZS)" error={form.errors.discount} required={false}>
                                <TextInput type="number" min="0" step="any" value={form.data.discount}
                                    onChange={(e) => form.setData('discount', e.target.value)} />
                            </Field>

                            <Field label="Notes" error={form.errors.notes} required={false}>
                                <Textarea rows={2} value={form.data.notes ?? ''}
                                    onChange={(e) => form.setData('notes', e.target.value)} />
                            </Field>
                        </div>
                    </Card>

                    {/* ---------------- Line items ---------------- */}
                    <Card
                        title="Invoice Items"
                        subtitle="Suggested from the student's class fees"
                        className="lg:col-span-2"
                        action={
                            <button type="button" onClick={addItem} className="btn-ghost px-3 py-1.5 text-xs">
                                <Plus className="h-3.5 w-3.5" /> Add item
                            </button>
                        }
                    >
                        {form.errors.items && (
                            <p className="mb-3 rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                                {form.errors.items}
                            </p>
                        )}

                        {items.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-500">
                                Select a student to load applicable fees, or add an item manually.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {items.map((item, index) => (
                                    <li key={index} className="rounded-xl bg-canvas p-3">
                                        <div className="grid gap-3 sm:grid-cols-12 sm:items-end">
                                            <div className="sm:col-span-6">
                                                <span className="label">Description</span>
                                                <TextInput
                                                    value={item.description}
                                                    onChange={(e) => updateItem(index, 'description', e.target.value)}
                                                    placeholder="e.g. Termly Tuition"
                                                />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <span className="label">Qty</span>
                                                <TextInput
                                                    type="number" min="1" step="1"
                                                    value={item.quantity}
                                                    onChange={(e) => updateItem(index, 'quantity', e.target.value)}
                                                />
                                            </div>
                                            <div className="sm:col-span-3">
                                                <span className="label">Unit Amount</span>
                                                <TextInput
                                                    type="number" min="0" step="any"
                                                    value={item.amount}
                                                    onChange={(e) => updateItem(index, 'amount', e.target.value)}
                                                />
                                            </div>
                                            <div className="flex items-center justify-between gap-2 sm:col-span-1">
                                                <span className="text-sm font-bold text-ink sm:hidden">
                                                    {Number(item.amount || 0) * Number(item.quantity || 0) > 0 &&
                                                        `TZS ${Number(item.amount || 0) * Number(item.quantity || 0).toLocaleString()}`}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => removeItem(index)}
                                                    className="grid h-9 w-9 place-items-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                                    aria-label="Remove item"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {/* ---------------- Totals ---------------- */}
                        <div className="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Subtotal</span>
                                <span className="font-bold text-ink">TZS {subtotal.toLocaleString()}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Discount</span>
                                <span className="font-bold text-rose-600">− TZS {Number(form.data.discount || 0).toLocaleString()}</span>
                            </div>
                            <div className="flex justify-between border-t border-slate-100 pt-2">
                                <span className="font-display text-base font-bold text-ink">Total Due</span>
                                <span className="font-display text-lg font-extrabold text-primary">
                                    TZS {total.toLocaleString()}
                                </span>
                            </div>
                        </div>
                    </Card>
                </div>

                <FormActions>
                    <a href="/invoices" className="btn-ghost">Cancel</a>
                    <button type="submit" disabled={form.processing || items.length === 0} className="btn-primary">
                        <Save className="h-4 w-4" />
                        {form.processing ? 'Issuing…' : 'Issue Invoice'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}
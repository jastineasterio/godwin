import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Banknote as BanknoteIcon, FileText, Receipt, RotateCcw } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Card, EmptyState, ProgressBar, StatCard } from '../../Components/ui';
import { Modal } from '../../Components/modal';
import { Field, Select, TextInput, Textarea } from '../../Components/form';
import { PageHeader } from '../../Components/page';

const money = (value) => `TZS ${Number(value ?? 0).toLocaleString()}`;

/**
 * INVOICE DETAIL — line items, payment history, printable receipt links and
 * the payment-entry modal (cash / mobile money / bank transfer …).
 */
export default function InvoiceShow({ invoice, items, payments, methods }) {
    const [payOpen, setPayOpen] = useState(false);

    const payForm = useForm({
        amount: invoice.balance > 0 ? invoice.balance : '',
        method: 'cash',
        reference: '',
        paid_at: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    const submitPayment = (event) => {
        event.preventDefault();
        payForm.post(`/invoices/${invoice.id}/payments`, {
            preserveScroll: true,
            onSuccess: () => {
                setPayOpen(false);
                payForm.reset();
            },
        });
    };

    const refund = (payment) => {
        const reason = prompt('Reason for refund:');
        if (reason) {
            router.post(`/payments/${payment.id}/refund`, { reason }, { preserveScroll: true });
        }
    };

    return (
        <DashboardLayout title="Finance" subtitle={`Invoice ${invoice.invoice_number}`}>
            <PageHeader
                title={invoice.invoice_number}
                subtitle={`${invoice.student_name} · ${invoice.student_reg} · ${invoice.term_name ?? 'No term'}`}
                back="/invoices"
                action={
                    <div className="flex flex-wrap gap-2">
                        <a href={`/students/${invoice.student_id}/fee-statement`} target="_blank" rel="noreferrer" className="btn-ghost">
                            <FileText className="h-4 w-4" /> Statement
                        </a>
                        {invoice.balance > 0 && invoice.status !== 'void' && (
                            <button type="button" onClick={() => setPayOpen(true)} className="btn-primary">
                                <BanknoteIcon className="h-4 w-4" /> Record Payment
                            </button>
                        )}
                    </div>
                }
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <StatCard index={0} label="Invoice Total" value={money(invoice.total_amount)} accent="primary" />
                <StatCard index={1} label="Amount Paid" value={money(invoice.amount_paid)} accent="secondary" />
                <StatCard index={2} label="Balance Due" value={money(invoice.balance)} accent="accent" />
                <StatCard index={3} label="Status" value={invoice.status_label} accent="primary" />
            </div>

            <div className="mt-5">
                <ProgressBar value={invoice.progress} color="bg-primary" label="Collection progress" />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                {/* ---------------- Items ---------------- */}
                <Card title="Invoice Items" subtitle={`Issued ${invoice.issue_date} · due ${invoice.due_date}`}>
                    <ul className="space-y-2">
                        {items.map((item) => (
                            <li key={item.id} className="flex items-center justify-between gap-3 rounded-xl bg-canvas p-3 text-sm">
                                <div className="min-w-0">
                                    <p className="truncate font-semibold text-ink">{item.description}</p>
                                    <p className="text-xs text-slate-500">{item.quantity} × {money(item.unit_amount)}</p>
                                </div>
                                <span className="shrink-0 font-bold text-ink">{money(item.amount)}</span>
                            </li>
                        ))}
                    </ul>

                    <div className="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                        <div className="flex justify-between">
                            <span className="text-slate-500">Subtotal</span>
                            <span className="font-bold text-ink">{money(invoice.subtotal)}</span>
                        </div>
                        {invoice.discount > 0 && (
                            <div className="flex justify-between">
                                <span className="text-slate-500">Discount</span>
                                <span className="font-bold text-rose-600">− {money(invoice.discount)}</span>
                            </div>
                        )}
                        <div className="flex justify-between">
                            <span className="font-bold text-ink">Total</span>
                            <span className="font-display text-lg font-extrabold text-primary">{money(invoice.total_amount)}</span>
                        </div>
                    </div>

                    {invoice.notes && (
                        <p className="mt-3 rounded-xl bg-canvas p-3 text-xs text-slate-600">{invoice.notes}</p>
                    )}
                </Card>

                {/* ---------------- Payment history ---------------- */}
                <Card
                    title="Payment History"
                    subtitle={`${payments.length} receipt(s) issued`}
                    action={
                        <Badge tone={invoice.status === 'paid' ? 'success' : 'warning'}>{invoice.status_label}</Badge>
                    }
                >
                    {payments.length === 0 ? (
                        <EmptyState title="No payments yet" hint="Record the first payment to issue a receipt." />
                    ) : (
                        <ul className="space-y-2.5">
                            {payments.map((payment) => (
                                <li key={payment.id} className="rounded-xl bg-canvas p-3">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-mono text-xs font-bold text-primary">{payment.receipt_number}</p>
                                            <p className="text-sm font-bold text-ink">{money(payment.amount)}</p>
                                            <p className="text-xs text-slate-500">
                                                {payment.method_label} · {payment.paid_at}
                                                {payment.reference ? ` · ${payment.reference}` : ''}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <a
                                                href={`/payments/${payment.id}/receipt`}
                                                target="_blank"
                                                rel="noreferrer"
                                                title="Print receipt"
                                                className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-secondary"
                                            >
                                                <Receipt className="h-4 w-4" />
                                            </a>
                                            <button
                                                type="button"
                                                onClick={() => refund(payment)}
                                                title="Refund"
                                                className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-amber-50 hover:text-amber-600"
                                            >
                                                <RotateCcw className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            {/* ---------------- Payment entry modal ---------------- */}
            <Modal
                open={payOpen}
                onClose={() => setPayOpen(false)}
                title="Record Payment"
                subtitle={`Outstanding balance: ${money(invoice.balance)}`}
                footer={
                    <>
                        <button type="button" onClick={() => setPayOpen(false)} className="btn-ghost">Cancel</button>
                        <button type="button" onClick={submitPayment} disabled={payForm.processing} className="btn-primary">
                            {payForm.processing ? 'Saving…' : 'Save Payment'}
                        </button>
                    </>
                }
            >
                <form onSubmit={submitPayment} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Amount (TZS)" required error={payForm.errors.amount}
                        hint={`Maximum ${money(invoice.balance)}`}>
                        <TextInput
                            type="number" min="1" step="any" max={invoice.balance}
                            value={payForm.data.amount}
                            onChange={(e) => payForm.setData('amount', e.target.value)}
                        />
                    </Field>

                    <Field label="Payment Method" required error={payForm.errors.method}>
                        <Select value={payForm.data.method} onChange={(e) => payForm.setData('method', e.target.value)}>
                            {methods.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                        </Select>
                    </Field>

                    <Field label="Reference / Teller No." error={payForm.errors.reference} required={false}>
                        <TextInput
                            value={payForm.data.reference}
                            onChange={(e) => payForm.setData('reference', e.target.value)}
                            placeholder="e.g. TXN-88213"
                        />
                    </Field>

                    <Field label="Payment Date" error={payForm.errors.paid_at} required={false}>
                        <TextInput
                            type="date"
                            value={payForm.data.paid_at}
                            onChange={(e) => payForm.setData('paid_at', e.target.value)}
                        />
                    </Field>

                    <div className="sm:col-span-2">
                        <Field label="Notes" error={payForm.errors.notes} required={false}>
                            <Textarea
                                rows={2}
                                value={payForm.data.notes}
                                onChange={(e) => payForm.setData('notes', e.target.value)}
                            />
                        </Field>
                    </div>
                </form>
            </Modal>
        </DashboardLayout>
    );
}
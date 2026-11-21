import { ChevronLeft } from 'lucide-react';
import { router, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import {
    Field,
    FormActions,
    FormSection,
    Select,
    TextInput,
    Textarea,
} from '../../Components/form';
import { PageHeader } from '../../Components/page';

export default function ExpenseCreate({ categories }) {
    const form = useForm({
        title: '',
        category: '',
        amount: '',
        incurred_on: '',
        reference: '',
        description: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/expenses');
    };

    return (
        <DashboardLayout
            title="Record Expense"
            subtitle="Enter a new school operating expense"
        >
            <PageHeader
                title="Record Expense"
                subtitle="Expenses appear in finance reports and reduce net balance"
                back="/expenses"
            />

            <form onSubmit={submit}>
                <FormSection title="Expense Details" description="What was purchased and how much it cost.">
                    <Field
                        label="Title"
                        required
                        error={form.errors.title}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            placeholder="e.g. Stationery supplies, Utility bill"
                        />
                    </Field>

                    <Field label="Category" required error={form.errors.category}>
                        <Select
                            value={form.data.category}
                            onChange={(e) => form.setData('category', e.target.value)}
                        >
                            <option value="">Select category…</option>
                            {categories.map((c) => (
                                <option key={c.value} value={c.value}>{c.label}</option>
                            ))}
                        </Select>
                    </Field>

                    <Field
                        label="Amount (TZS)"
                        required
                        error={form.errors.amount}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                        />
                    </Field>

                    <Field
                        label="Date Incurred"
                        required
                        error={form.errors.incurred_on}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            type="date"
                            value={form.data.incurred_on}
                            onChange={(e) => form.setData('incurred_on', e.target.value)}
                        />
                    </Field>

                    <Field
                        label="Reference"
                        required={false}
                        error={form.errors.reference}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={form.data.reference}
                            onChange={(e) => form.setData('reference', e.target.value)}
                            placeholder="Receipt or invoice number (optional)"
                        />
                    </Field>

                    <Field
                        label="Description"
                        required={false}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            rows={3}
                            value={form.data.description ?? ''}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="Additional details about this expense…"
                        />
                    </Field>
                </FormSection>

                <FormActions>
                    <a href="/expenses" className="btn-ghost">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="btn-primary"
                    >
                        {form.processing ? 'Recording…' : 'Record Expense'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}

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

const CATEGORIES = [
    { value: 'furniture', label: 'Furniture' },
    { value: 'electronics', label: 'Electronics' },
    { value: 'teaching', label: 'Teaching Materials' },
    { value: 'sports', label: 'Sports Equipment' },
    { value: 'kitchen', label: 'Kitchen Equipment' },
    { value: 'bedding', label: 'Beds & Mattresses' },
    { value: 'toys', label: 'Toys' },
    { value: 'other', label: 'Other' },
];

const CONDITIONS = [
    { value: 'new', label: 'New' },
    { value: 'good', label: 'Good' },
    { value: 'fair', label: 'Fair' },
    { value: 'damaged', label: 'Damaged' },
    { value: 'missing', label: 'Missing' },
];

const STATUSES = [
    { value: 'active', label: 'Active' },
    { value: 'in_use', label: 'In Use' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'disposed', label: 'Disposed' },
    { value: 'missing', label: 'Missing' },
];

export default function FacilitiesCreate({ users }) {
    const form = useForm({
        name: '',
        category: '',
        asset_code: '',
        description: '',
        quantity: '1',
        purchase_date: '',
        purchase_price: '',
        supplier: '',
        location: '',
        condition: 'good',
        status: 'active',
        image_path: '',
        recorded_by: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/facilities');
    };

    return (
        <DashboardLayout
            title="Register Asset"
            subtitle="Add a new item to the school inventory"
        >
            <PageHeader
                title="Register Asset"
                subtitle="Track quantity, condition, location and purchase details"
                back="/facilities"
            />

            <form onSubmit={submit}>
                <FormSection title="Asset Details" description="Identify the asset and its category.">
                    <Field
                        label="Asset Name"
                        required
                        error={form.errors.name}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="e.g. Office Chair, Desktop Computer"
                        />
                    </Field>

                    <Field label="Category" required error={form.errors.category}>
                        <Select
                            value={form.data.category}
                            onChange={(e) => form.setData('category', e.target.value)}
                        >
                            <option value="">Select category…</option>
                            {CATEGORIES.map((c) => (
                                <option key={c.value} value={c.value}>{c.label}</option>
                            ))}
                        </Select>
                    </Field>

                    <Field
                        label="Asset / Inventory Code"
                        required={false}
                        error={form.errors.asset_code}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={form.data.asset_code}
                            onChange={(e) => form.setData('asset_code', e.target.value)}
                            placeholder="e.g. GW-0001"
                            hint="Unique inventory number (optional)"
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
                            placeholder="Brief description of the asset…"
                        />
                    </Field>
                </FormSection>

                <FormSection title="Quantity & Purchase" description="How many units and what they cost.">
                    <Field
                        label="Quantity"
                        required
                        error={form.errors.quantity}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            type="number"
                            min="1"
                            value={form.data.quantity}
                            onChange={(e) => form.setData('quantity', e.target.value)}
                        />
                    </Field>

                    <Field
                        label="Purchase Date"
                        required={false}
                        error={form.errors.purchase_date}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            type="date"
                            value={form.data.purchase_date}
                            onChange={(e) => form.setData('purchase_date', e.target.value)}
                        />
                    </Field>

                    <Field
                        label="Purchase Price (TZS)"
                        required={false}
                        error={form.errors.purchase_price}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.purchase_price}
                            onChange={(e) => form.setData('purchase_price', e.target.value)}
                        />
                    </Field>

                    <Field
                        label="Supplier"
                        required={false}
                        error={form.errors.supplier}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={form.data.supplier}
                            onChange={(e) => form.setData('supplier', e.target.value)}
                            placeholder="Supplier or vendor name (optional)"
                        />
                    </Field>
                </FormSection>

                <FormSection title="Location & Condition" description="Where the asset is kept and its state.">
                    <Field
                        label="Location / Room"
                        required={false}
                        error={form.errors.location}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={form.data.location}
                            onChange={(e) => form.setData('location', e.target.value)}
                            placeholder="e.g. KG1 Classroom, Office"
                        />
                    </Field>

                    <Field label="Condition" required error={form.errors.condition}>
                        <Select
                            value={form.data.condition}
                            onChange={(e) => form.setData('condition', e.target.value)}
                        >
                            <option value="">Select condition…</option>
                            {CONDITIONS.map((c) => (
                                <option key={c.value} value={c.value}>{c.label}</option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Status" required error={form.errors.status}>
                        <Select
                            value={form.data.status}
                            onChange={(e) => form.setData('status', e.target.value)}
                        >
                            <option value="">Select status…</option>
                            {STATUSES.map((s) => (
                                <option key={s.value} value={s.value}>{s.label}</option>
                            ))}
                        </Select>
                    </Field>

                    <Field
                        label="Image / Document Path"
                        required={false}
                        error={form.errors.image_path}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={form.data.image_path}
                            onChange={(e) => form.setData('image_path', e.target.value)}
                            placeholder="e.g. assets/chair-001.jpg"
                            hint="Path to supporting photo or document (optional)"
                        />
                    </Field>

                    <Field label="Recorded By" className="sm:col-span-2">
                        <Select
                            value={form.data.recorded_by}
                            onChange={(e) => form.setData('recorded_by', e.target.value)}
                        >
                            <option value="">Current user</option>
                            {users.map((u) => (
                                <option key={u.id} value={u.id}>{u.name}</option>
                            ))}
                        </Select>
                    </Field>
                </FormSection>

                <FormActions>
                    <a href="/facilities" className="btn-ghost">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="btn-primary"
                    >
                        {form.processing ? 'Registering…' : 'Register Asset'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}

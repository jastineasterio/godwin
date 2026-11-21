import { ChevronLeft } from 'lucide-react';
import { router, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Field, FormActions, FormSection, Select, TextInput } from '../../Components/form';
import { PageHeader } from '../../Components/page';

export default function TeachersEdit({ teacher }) {
    const form = useForm({
        name: teacher.name,
        email: teacher.email,
        phone: teacher.phone ?? '',
        status: teacher.status,
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(`/teachers/${teacher.id}`);
    };

    return (
        <DashboardLayout
            title="Edit Teacher"
            subtitle="Update teacher profile and account status"
        >
            <PageHeader
                title="Edit Teacher"
                subtitle={`Changes to "${teacher.name}" take effect immediately`}
                back="/teachers"
            />

            <form onSubmit={submit}>
                <FormSection title="Account Details" description="Personal and contact information.">
                    <Field
                        label="Full Name"
                        required
                        error={form.errors.name}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Field>

                    <Field
                        label="Email Address"
                        required
                        error={form.errors.email}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                    </Field>

                    <Field
                        label="Phone Number"
                        required={false}
                        error={form.errors.phone}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            type="tel"
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                        />
                    </Field>

                    <Field label="Account Status" className="sm:col-span-2">
                        <Select
                            value={form.data.status}
                            onChange={(e) => form.setData('status', e.target.value)}
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </Select>
                    </Field>
                </FormSection>

                <FormActions>
                    <a href={`/teachers/${teacher.id}`} className="btn-ghost">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="btn-primary"
                    >
                        {form.processing ? 'Updating…' : 'Update Teacher'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}

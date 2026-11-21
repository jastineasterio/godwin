import { ChevronLeft } from 'lucide-react';
import { router, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Field, FormActions, FormSection, Select, TextInput } from '../../Components/form';
import { PageHeader } from '../../Components/page';

export default function TeachersCreate() {
    const form = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        status: 'active',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/teachers');
    };

    return (
        <DashboardLayout
            title="Register Teacher"
            subtitle="Create a new teacher account with secure login credentials"
        >
            <PageHeader
                title="Register Teacher"
                subtitle="The account is created with the Teacher role and can log in immediately"
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
                            placeholder="e.g. Amina Juma"
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
                            placeholder="amina.juma@godwin.ac.tz"
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
                            placeholder="e.g. 0757123456"
                        />
                    </Field>

                    <Field
                        label="Initial Password"
                        required
                        error={form.errors.password}
                        className="sm:col-span-2"
                        hint="Minimum 8 characters. The teacher can change it at first login."
                    >
                        <TextInput
                            type="password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                    </Field>

                    <Field
                        label="Confirm Password"
                        required
                        error={form.errors.password_confirmation}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            type="password"
                            value={form.data.password_confirmation}
                            onChange={(e) => form.setData('password_confirmation', e.target.value)}
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
                    <a href="/teachers" className="btn-ghost">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="btn-primary"
                    >
                        {form.processing ? 'Creating…' : 'Register Teacher'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}

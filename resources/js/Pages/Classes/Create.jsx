import { useForm } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { PageHeader } from '../../Components/page';

export default function ClassCreate({ teachers, levels }) {
    const form = useForm({
        name: '', code: '', level: '', teacher_id: '', capacity: '', description: '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post('/classes', { preserveScroll: true });
    };

    return (
        <DashboardLayout title="Class Management" subtitle="Register a new school class.">
            <PageHeader title="Create New Class" subtitle="All fields marked with * are required." back="/classes" />
            <form onSubmit={submit} className="glass-card p-4 sm:p-5 space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Class Name *" required error={!!form.errors.name}>
                        <TextInput value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. KG1 Sunshine" error={!!form.errors.name} />
                    </Field>
                    <Field label="Class Code *" required error={!!form.errors.code}>
                        <TextInput value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder="e.g. KG1-A" error={!!form.errors.code} />
                    </Field>
                    <Field label="Level *" required error={!!form.errors.level}>
                        <Select value={form.data.level} onChange={(e) => form.setData('level', e.target.value)} error={!!form.errors.level}>
                            <option value="">Select level</option>
                            {levels.map((l) => <option key={l.value} value={l.value}>{l.label}</option>)}
                        </Select>
                    </Field>
                    <Field label="Class Teacher" error={!!form.errors.teacher_id}>
                        <Select value={form.data.teacher_id} onChange={(e) => form.setData('teacher_id', e.target.value)} error={!!form.errors.teacher_id}>
                            <option value="">Unassigned</option>
                            {teachers.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </Select>
                    </Field>
                    <Field label="Maximum Capacity *" required error={!!form.errors.capacity}>
                        <TextInput type="number" value={form.data.capacity} onChange={(e) => form.setData('capacity', e.target.value)} placeholder="e.g. 25" error={!!form.errors.capacity} />
                    </Field>
                </div>
                <Field label="Description" error={!!form.errors.description}>
                    <Textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Optional class description" error={!!form.errors.description} />
                </Field>
                <div className="flex justify-end gap-2 pt-4">
                    <Link href="/classes" className="btn-ghost px-4 py-2 text-sm">Cancel</Link>
                    <button type="submit" disabled={form.processing} className="btn-primary px-4 py-2 text-sm">
                        {form.processing ? 'Creating…' : 'Create Class'}
                    </button>
                </div>
            </form>
        </DashboardLayout>
    );
}

function Field({ label, required = false, error, children }) {
    return (
        <label className="block">
            <span className="label">{label} {required && <span className="text-primary">*</span>}</span>
            {children}
            {error && <span className="mt-1 block text-xs font-semibold text-rose-600">Invalid value.</span>}
        </label>
    );
}
function TextInput({ value, onChange, placeholder, error = false, className = '', ...props }) {
    return <input {...props} value={value} onChange={onChange} placeholder={placeholder} className={`input ${error ? 'border-rose-300 focus:border-rose-500' : ''} ${className}`} />;
}
function Select({ value, onChange, children, error = false, className = '', ...props }) {
    return <select {...props} value={value} onChange={onChange} className={`input ${error ? 'border-rose-300 focus:border-rose-500' : ''} ${className}`}>{children}</select>;
}
function Textarea({ value, onChange, placeholder, error = false, className = '', ...props }) {
    return <textarea {...props} value={value} onChange={onChange} placeholder={placeholder} className={`input ${error ? 'border-rose-300 focus:border-rose-500' : ''} ${className}`} />;
}

import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Field, FormActions } from '../../Components/form';

export default function EventsCreate({ audiences, errors }) {
    const [processing, setProcessing] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post('/events', {
            title: e.target.title.value,
            description: e.target.description.value,
            start_date: e.target.start_date.value,
            end_date: e.target.end_date.value || null,
            start_time: e.target.start_time.value || null,
            end_time: e.target.end_time.value || null,
            location: e.target.location.value,
            color: e.target.color.value || '#D81B60',
            audience: e.target.audience.value,
            is_published: e.target.is_published?.checked ?? true,
        }, {
            onFinish: () => setProcessing(false),
            onError: () => setProcessing(false),
        });
    };

    return (
        <DashboardLayout title="Communications" subtitle="Create a new school event.">
            <Link href="/events" className="mb-4 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                <ArrowLeft className="h-3.5 w-3.5" />
                Back to Events
            </Link>

            <form onSubmit={submit} className="glass-card p-5 sm:p-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Title" required error={errors.title}>
                        <input type="text" name="title" className="input" placeholder="Event title" maxLength={180} />
                    </Field>

                    <Field label="Location" error={errors.location}>
                        <input type="text" name="location" className="input" placeholder="e.g. Main Hall" maxLength={180} />
                    </Field>

                    <Field label="Start Date" required error={errors.start_date}>
                        <input type="date" name="start_date" className="input" />
                    </Field>

                    <Field label="End Date" error={errors.end_date}>
                        <input type="date" name="end_date" className="input" />
                    </Field>

                    <Field label="Start Time" error={errors.start_time}>
                        <input type="time" name="start_time" className="input" />
                    </Field>

                    <Field label="End Time" error={errors.end_time}>
                        <input type="time" name="end_time" className="input" />
                    </Field>

                    <Field label="Audience" required error={errors.audience}>
                        <select name="audience" className="input" defaultValue="everyone">
                            {audiences.map((a) => (
                                <option key={a.value} value={a.value}>{a.label}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Color" error={errors.color}>
                        <input type="color" name="color" className="input h-10 w-full p-1" defaultValue="#D81B60" />
                    </Field>

                    <div className="sm:col-span-2">
                        <Field label="Description" error={errors.description}>
                            <textarea name="description" className="input" rows={4} placeholder="Event details..." maxLength={2000}></textarea>
                        </Field>
                    </div>

                    <div className="sm:col-span-2">
                        <label className="flex cursor-pointer items-start gap-2.5">
                            <input type="checkbox" name="is_published" defaultChecked className="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/30" />
                            <span>
                                <span className="block text-sm font-semibold text-ink">Publish immediately</span>
                                <span className="block text-xs text-slate-500">Visible to the selected audience right away.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <FormActions>
                    <Link href="/events" className="btn-ghost">Cancel</Link>
                    <button type="submit" disabled={processing} className="btn-primary">
                        <Plus className="h-4 w-4" />
                        {processing ? 'Creating…' : 'Create Event'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}

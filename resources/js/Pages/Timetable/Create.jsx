import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Field, FormActions } from '../../Components/form';

export default function TimetableCreate({ classes, subjects, teachers, days, errors }) {
    const [processing, setProcessing] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post('/timetable', formData, {
            onFinish: () => setProcessing(false),
            onError: () => setProcessing(false),
        });
    };

    const formData = {
        class_id: '',
        subject_id: '',
        teacher_id: '',
        day: 'monday',
        start_time: '08:00',
        end_time: '09:00',
        room: '',
    };

    return (
        <DashboardLayout title="Academics" subtitle="Create a new timetable entry.">
            <Link href="/timetable" className="mb-4 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                <ArrowLeft className="h-3.5 w-3.5" />
                Back to Timetable
            </Link>

            <form onSubmit={submit} className="glass-card p-5 sm:p-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Class" required error={errors.class_id}>
                        <select
                            name="class_id"
                            className="input"
                            defaultValue=""
                        >
                            <option value="">Select class</option>
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Subject" required error={errors.subject_id}>
                        <select
                            name="subject_id"
                            className="input"
                            defaultValue=""
                        >
                            <option value="">Select subject</option>
                            {subjects.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Teacher" error={errors.teacher_id}>
                        <select name="teacher_id" className="input" defaultValue="">
                            <option value="">Unassigned</option>
                            {teachers.map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Day" required error={errors.day}>
                        <select name="day" className="input" defaultValue="monday">
                            {days.map((d) => (
                                <option key={d.value} value={d.value}>{d.label}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Start Time" required error={errors.start_time}>
                        <input type="time" name="start_time" className="input" defaultValue="08:00" />
                    </Field>

                    <Field label="End Time" required error={errors.end_time}>
                        <input type="time" name="end_time" className="input" defaultValue="09:00" />
                    </Field>

                    <Field label="Room / Location" error={errors.room}>
                        <input type="text" name="room" className="input" placeholder="e.g. Room 1" maxLength={50} />
                    </Field>
                </div>

                <FormActions>
                    <Link href="/timetable" className="btn-ghost">Cancel</Link>
                    <button type="submit" disabled={processing} className="btn-primary">
                        <Plus className="h-4 w-4" />
                        {processing ? 'Saving…' : 'Add Entry'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}

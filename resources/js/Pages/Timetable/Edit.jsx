import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Field, FormActions } from '../../Components/form';

export default function TimetableEdit({ timetable, classes, subjects, teachers, days, errors }) {
    const [processing, setProcessing] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.put(`/timetable/${timetable.id}`, {
            class_id: e.target.class_id.value,
            subject_id: e.target.subject_id.value,
            teacher_id: e.target.teacher_id.value || null,
            day: e.target.day.value,
            start_time: e.target.start_time.value,
            end_time: e.target.end_time.value,
            room: e.target.room.value,
        }, {
            onFinish: () => setProcessing(false),
            onError: () => setProcessing(false),
        });
    };

    return (
        <DashboardLayout title="Academics" subtitle="Edit timetable entry.">
            <Link href="/timetable" className="mb-4 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                <ArrowLeft className="h-3.5 w-3.5" />
                Back to Timetable
            </Link>

            <form onSubmit={submit} className="glass-card p-5 sm:p-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Class" required error={errors.class_id}>
                        <select name="class_id" className="input" defaultValue={timetable.class_id}>
                            <option value="">Select class</option>
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Subject" required error={errors.subject_id}>
                        <select name="subject_id" className="input" defaultValue={timetable.subject_id}>
                            <option value="">Select subject</option>
                            {subjects.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Teacher" error={errors.teacher_id}>
                        <select name="teacher_id" className="input" defaultValue={timetable.teacher_id ?? ''}>
                            <option value="">Unassigned</option>
                            {teachers.map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Day" required error={errors.day}>
                        <select name="day" className="input" defaultValue={timetable.day}>
                            {days.map((d) => (
                                <option key={d.value} value={d.value}>{d.label}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Start Time" required error={errors.start_time}>
                        <input type="time" name="start_time" className="input" defaultValue={timetable.start_time} />
                    </Field>

                    <Field label="End Time" required error={errors.end_time}>
                        <input type="time" name="end_time" className="input" defaultValue={timetable.end_time} />
                    </Field>

                    <Field label="Room / Location" error={errors.room}>
                        <input type="text" name="room" className="input" defaultValue={timetable.room ?? ''} placeholder="e.g. Room 1" maxLength={50} />
                    </Field>
                </div>

                <FormActions>
                    <Link href="/timetable" className="btn-ghost">Cancel</Link>
                    <button type="submit" disabled={processing} className="btn-primary">
                        {processing ? 'Saving…' : 'Save Changes'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}

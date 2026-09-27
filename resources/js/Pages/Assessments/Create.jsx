import { router, useForm } from '@inertiajs/react';
import { Calculator, Save } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Card } from '../../Components/ui';
import { Field, FormActions, FormSection, Select, TextInput, Textarea } from '../../Components/form';
import { PageHeader } from '../../Components/page';

/**
 * ASSESSMENT ENTRY — record a test, exam, homework mark or developmental
 * milestone. The same screen edits an existing record when `assessment`
 * is supplied.
 */
export default function AssessmentCreate({
    assessment = null, classes, activeClassId, students, subjects, terms, types,
}) {
    const isEdit = !!assessment;

    const form = useForm({
        student_id: assessment?.student_id ?? students?.[0]?.id ?? '',
        class_id: assessment?.class_id ?? activeClassId ?? '',
        subject_id: assessment?.subject_id ?? '',
        term_id: assessment?.term_id ?? terms?.find((t) => t.is_current)?.id ?? '',
        title: assessment?.title ?? '',
        type: assessment?.type ?? 'test',
        score: assessment?.score ?? '',
        max_score: assessment?.max_score ?? 20,
        remarks: assessment?.remarks ?? '',
        obtained_at: assessment?.obtained_at ?? new Date().toISOString().slice(0, 10),
    });

    const set = (key) => (e) => form.setData(key, e.target.value);

    // Switching class reloads the roster for that class (server-side scoping)
    const changeClass = (classId) => {
        form.setData('class_id', classId);
        form.setData('student_id', '');
        router.get('/assessments/create', { class_id: classId }, { preserveScroll: true, preserveState: true });
    };

    const submit = (event) => {
        event.preventDefault();
        isEdit ? form.put(`/assessments/${assessment.id}`) : form.post('/assessments');
    };

    const percentage =
        Number(form.data.max_score) > 0
            ? Math.round((Number(form.data.score) / Number(form.data.max_score)) * 100)
            : 0;

    const overMax = Number(form.data.score) > Number(form.data.max_score);

    return (
        <DashboardLayout title="Academics" subtitle="Record marks and developmental milestones.">
            <PageHeader
                title={isEdit ? 'Edit Assessment' : 'Record Assessment'}
                subtitle={isEdit ? assessment.title : 'Marks appear instantly on the child’s progress report.'}
                back="/assessments"
            />

            <form onSubmit={submit}>
                <FormSection title="Assessment Details" description="Who was assessed, on what, and when.">
                    <Field label="Class" required error={form.errors.class_id} hint="Only your assigned classes appear.">
                        <Select value={form.data.class_id} onChange={(e) => changeClass(e.target.value)} disabled={isEdit}>
                            {classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </Select>
                    </Field>

                    <Field label="Student" required error={form.errors.student_id}>
                        <Select value={form.data.student_id} onChange={set('student_id')}>
                            <option value="">Select student…</option>
                            {students.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.reg_no})</option>)}
                        </Select>
                    </Field>

                    <Field label="Assessment Title" required error={form.errors.title} className="sm:col-span-2">
                        <TextInput value={form.data.title} onChange={set('title')} placeholder="e.g. Week 4 Phonics Test" />
                    </Field>

                    <Field label="Subject" error={form.errors.subject_id} required={false}>
                        <Select value={form.data.subject_id} onChange={set('subject_id')}>
                            <option value="">General (no subject)</option>
                            {subjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </Select>
                    </Field>

                    <Field label="Type" required error={form.errors.type}>
                        <Select value={form.data.type} onChange={set('type')}>
                            {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                        </Select>
                    </Field>

                    <Field label="Term" error={form.errors.term_id} required={false}>
                        <Select value={form.data.term_id} onChange={set('term_id')}>
                            <option value="">— No term —</option>
                            {terms.map((t) => (
                                <option key={t.id} value={t.id}>{t.name}{t.is_current ? ' (current)' : ''}</option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Date Obtained" error={form.errors.obtained_at} required={false}>
                        <TextInput type="date" value={form.data.obtained_at ?? ''} onChange={set('obtained_at')} />
                    </Field>
                </FormSection>

                {/* ---------------- Marks ---------------- */}
                <Card title="Marks" subtitle="Marks cannot exceed the maximum score." className="mt-5">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Maximum Score" required error={form.errors.max_score}>
                            <TextInput
                                type="number" min="1" step="any"
                                value={form.data.max_score} onChange={set('max_score')}
                            />
                        </Field>

                        <Field label="Marks Obtained" required error={form.errors.score}>
                            <TextInput
                                type="number" min="0" step="any"
                                value={form.data.score} onChange={set('score')}
                            />
                        </Field>

                        <div>
                            <span className="label">Percentage</span>
                            <div
                                className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold ${
                                    overMax
                                        ? 'bg-rose-50 text-rose-700'
                                        : percentage >= 75
                                          ? 'bg-emerald-50 text-emerald-700'
                                          : 'bg-amber-50 text-amber-700'
                                }`}
                            >
                                <Calculator className="h-4 w-4" />
                                {form.data.score === '' ? '—' : `${percentage}%`}
                            </div>
                        </div>
                    </div>

                    <div className="mt-4">
                        <Field
                            label="Teacher Remarks"
                            error={form.errors.remarks}
                            required={false}
                            hint="Shown to parents on the progress report."
                        >
                            <Textarea
                                rows={3}
                                value={form.data.remarks ?? ''}
                                onChange={set('remarks')}
                                placeholder="e.g. Excellent recall of letter sounds; needs practice with blending."
                            />
                        </Field>
                    </div>
                </Card>

                <FormActions>
                    <a href="/assessments" className="btn-ghost">Cancel</a>
                    <button type="submit" disabled={form.processing || overMax} className="btn-primary">
                        <Save className="h-4 w-4" />
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Record Assessment'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}
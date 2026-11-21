import { useState } from 'react';
import { Calculator, ChevronLeft, Save, Send } from 'lucide-react';
import { router, usePage, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { Field, FormActions, FormSection, Select, TextInput } from '../../Components/form';
import { PageHeader } from '../../Components/page';

export default function TeacherResultEntry({
    examination,
    class: klass,
    subject,
    max_score,
    students,
    existingResults,
    classes,
    subjects,
}) {
    const [selectedClassId, setSelectedClassId] = useState(klass?.id ?? '');
    const [selectedSubjectId, setSelectedSubjectId] = useState(subject?.id ?? '');

    const [submitting, setSubmitting] = useState(false);

    const studentsForClass = selectedClassId
        ? students.filter((s) => s.id)
        : [];

    const form = useForm({
        class_id: selectedClassId,
        subject_id: selectedSubjectId,
        results: students.map((s) => ({
            student_id: s.id,
            score: existingResults.find((r) => r.student_id === s.id)?.score ?? '',
            remarks: existingResults.find((r) => r.student_id === s.id)?.remarks ?? '',
        })),
    });

    const changeClass = (classId) => {
        setSelectedClassId(classId);
        router.get(
            `/teacher/examinations/${examination.id}/results`,
            { class_id: classId, subject_id: '' },
            { preserveScroll: true, preserveState: true }
        );
    };

    const changeSubject = (subjectId) => {
        setSelectedSubjectId(subjectId);
        router.get(
            `/teacher/examinations/${examination.id}/results`,
            { class_id: selectedClassId, subject_id: subjectId },
            { preserveScroll: true, preserveState: true }
        );
    };

    const updateScore = (index, value) => {
        const results = [...form.data.results];
        results[index].score = value;
        form.setData('results', results);
    };

    const updateRemarks = (index, value) => {
        const results = [...form.data.results];
        results[index].remarks = value;
        form.setData('results', results);
    };

    const saveDraft = (e) => {
        e.preventDefault();
        form.put(`/teacher/examinations/${examination.id}/results`, {
            preserveScroll: true,
            onSuccess: () => {},
        });
    };

    const submitResults = (e) => {
        e.preventDefault();
        setSubmitting(true);
    };

    const completion =
        students.length > 0
            ? Math.round(
                  (form.data.results.filter((r) => r.score !== '' && r.score !== null).length /
                      students.length) *
                      100
              )
            : 0;

    return (
        <DashboardLayout
            title="Record Results"
            subtitle={`${examination.name} · ${klass?.name} · ${subject?.name ?? 'General'}`}
        >
            <PageHeader
                title="Record Results"
                subtitle={`Enter marks for ${examination.name} — ${klass?.name} — ${subject?.name ?? 'General'}`}
                back="/teacher/examinations"
            />

            <div className="mb-6 grid gap-4 sm:grid-cols-4">
                <div className="rounded-xl border border-slate-200 p-4">
                    <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Examination
                    </p>
                    <p className="font-bold text-ink">{examination.name}</p>
                    <p className="text-xs text-slate-500">
                        {examination.academic_year} · {examination.term}
                    </p>
                </div>
                <div className="rounded-xl border border-slate-200 p-4">
                    <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Class
                    </p>
                    <p className="font-bold text-ink">{klass?.name ?? '—'}</p>
                    <p className="text-xs text-slate-500">{klass?.code ?? '—'}</p>
                </div>
                <div className="rounded-xl border border-slate-200 p-4">
                    <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Subject
                    </p>
                    <p className="font-bold text-ink">{subject?.name ?? 'General'}</p>
                    <p className="text-xs text-slate-500">Max: {max_score}</p>
                </div>
                <div className="rounded-xl border border-slate-200 p-4">
                    <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Completion
                    </p>
                    <p className="font-bold text-ink">{completion}%</p>
                    <p className="text-xs text-slate-500">
                        {form.data.results.filter((r) => r.score !== '' && r.score !== null).length} of{" "}
                        {students.length} students
                    </p>
                </div>
            </div>

            <div className="grid gap-6 sm:grid-cols-12">
                <div className="sm:col-span-3 xl:col-span-4">
                    <Card title="Filters" className="mb-4">
                        <Field label="Class" required error={null}>
                            <Select
                                value={selectedClassId}
                                onChange={(e) => changeClass(e.target.value)}
                            >
                                <option value="">Select class…</option>
                                {classes.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Subject" required error={null}>
                            <Select
                                value={selectedSubjectId}
                                onChange={(e) => changeSubject(e.target.value)}
                            >
                                <option value="">Select subject…</option>
                                {subjects.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name} (max {s.max_score})
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    </Card>

                    {students.length > 0 && selectedSubjectId && (
                        <div className="mt-4 rounded-xl border border-slate-200 p-4">
                            <p className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                                Students ({students.length})
                            </p>
                            <ul className="space-y-2 max-h-96 overflow-y-auto pr-2">
                                {students.map((s, idx) => {
                                    const existing = existingResults.find((r) => r.student_id === s.id);
                                    return (
                                        <li key={s.id} className="flex items-center justify-between gap-3 p-2 rounded-lg border border-slate-100">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-bold text-ink truncate">
                                                    {s.name}
                                                </p>
                                                <p className="text-[11px] text-slate-500 truncate">
                                                    {s.reg_no}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="any"
                                                    max={max_score}
                                                    value={form.data.results[idx]?.score ?? ''}
                                                    onChange={(e) =>
                                                        updateScore(idx, e.target.value)
                                                    }
                                                    className="input w-20 text-center"
                                                    placeholder="0"
                                                />
                                                <span className="text-[11px] font-bold text-slate-400">
                                                    / {max_score}
                                                </span>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}
                </div>

                <div className="sm:col-span-9 xl:col-span-8">
                    <Card title="Bulk Marks Entry" subtitle="Enter marks for each student. Marks cannot exceed the maximum score.">
                        <form onSubmit={saveDraft}>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                            <th className="pb-3 font-bold">Admission No.</th>
                                            <th className="pb-3 font-bold">Student Name</th>
                                            <th className="pb-3 font-bold text-right">Marks Obtained</th>
                                            <th className="pb-3 font-bold text-right">Max Marks</th>
                                            <th className="pb-3 font-bold">Remarks</th>
                                            <th className="pb-3 font-bold text-right">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {students.map((s, idx) => {
                                            const existing = existingResults.find((r) => r.student_id === s.id);
                                            const score = form.data.results[idx]?.score ?? existing?.score ?? '';
                                            const remarks = form.data.results[idx]?.remarks ?? existing?.remarks ?? '';
                                            const overMax =
                                                score !== '' && Number(score) > Number(max_score);
                                            return (
                                                <tr
                                                    key={s.id}
                                                    className={`transition hover:bg-canvas/60 ${
                                                        overMax ? 'bg-rose-50' : ''
                                                    }`}
                                                >
                                                    <td className="py-3 font-bold text-ink">
                                                        {s.reg_no}
                                                    </td>
                                                    <td className="py-3 text-slate-600">{s.name}</td>
                                                    <td className="py-3 text-right">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            step="any"
                                                            max={max_score}
                                                            value={score}
                                                            onChange={(e) =>
                                                                updateScore(idx, e.target.value)
                                                            }
                                                            className={`input w-24 text-center ${
                                                                overMax
                                                                    ? 'border-rose-300 focus:border-rose-500'
                                                                    : ''
                                                            }`}
                                                            placeholder="0"
                                                        />
                                                    </td>
                                                    <td className="py-3 text-right text-slate-600">
                                                        {max_score}
                                                    </td>
                                                    <td className="py-3">
                                                        <input
                                                            type="text"
                                                            value={remarks}
                                                            onChange={(e) =>
                                                                updateRemarks(idx, e.target.value)
                                                            }
                                                            className="input"
                                                            placeholder="Remarks…"
                                                        />
                                                    </td>
                                                    <td className="py-3 text-right">
                                                        <Badge
                                                            tone={
                                                                existing?.status === 'approved'
                                                                    ? 'success'
                                                                    : existing?.status === 'submitted'
                                                                    ? 'secondary'
                                                                    : existing?.status === 'returned'
                                                                    ? 'accent'
                                                                    : 'muted'
                                                            }
                                                        >
                                                            {existing?.status ?? 'Draft'}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {students.length > 0 && selectedSubjectId && (
                                <FormActions>
                                    <button
                                        type="button"
                                        onClick={submitResults}
                                        disabled={form.processing || completion === 0 || submitting}
                                        className="btn-secondary mr-2"
                                    >
                                        <Send className="h-4 w-4 mr-2" />
                                        Submit for Review
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="btn-primary"
                                    >
                                        <Save className="h-4 w-4 mr-2" />
                                        Save Draft
                                    </button>
                                </FormActions>
                            )}
                        </form>
                    </Card>
                </div>
            </div>

            <ConfirmDialog
                open={submitting}
                onClose={() => setSubmitting(false)}
                onConfirm={() => {
                    router.post(
                        `/teacher/examinations/${examination.id}/results/submit`,
                        { class_id: selectedClassId, subject_id: selectedSubjectId },
                        { preserveScroll: true, onFinish: () => setSubmitting(false) }
                    );
                }}
                title="Submit marks for review?"
                message="Submitted marks can no longer be edited by you. Ask the Head of School to return them if corrections are needed."
                confirmLabel="Submit"
            />
        </DashboardLayout>
    );
}

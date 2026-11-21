import { useState } from 'react';
import { Plus, Search, UserRound } from 'lucide-react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState, StatCard } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { PageHeader, PrimaryAction } from '../../Components/page';

/* ===========================================================================
 * TEACHING ASSIGNMENTS — Head of School / Admin
 *
 * Assigns a teacher to a subject within a class via the existing `class_subject`
 * pivot (class_id + subject_id unique, teacher_id on the pivot). No duplicate
 * table is created.
 * ======================================================================== */

export default function TeachingAssignmentsIndex({
    assignments,
    teachers,
    classes,
    subjects,
    classSubjects,
    filters,
}) {
    const [teacherFilter, setTeacherFilter] = useState(filters.teacher ?? '');
    const [step, setStep] = useState(1); // 1=class, 2=subject, 3=teacher, 4=save
    const [selectedClass, setSelectedClass] = useState('');
    const [selectedSubject, setSelectedSubject] = useState('');
    const [selectedTeacher, setSelectedTeacher] = useState('');
    const [editing, setEditing] = useState(null); // {id, class_id, subject_id, teacher_id}

    // Subjects valid for the selected class (pivot link exists). `selectedClass`
    // is a string from the <select> element but a number when set via
    // `startEdit`, while `cs.class_id`/`s.id` are JSON numbers, so coerce to
    // numbers for a reliable comparison. Do NOT filter on teacher_id: subjects
    // already assigned to a teacher must still appear (use Change Teacher for them).
    const selectedClassId = selectedClass ? Number(selectedClass) : null;
    const validSubjects = subjects.filter((s) =>
        classSubjects.some((cs) => cs.class_id === selectedClassId && cs.subject_id === s.id)
    );

    const form = useForm({
        class_id: editing?.class_id ?? selectedClass,
        subject_id: editing?.subject_id ?? selectedSubject,
        teacher_id: editing?.teacher_id ?? selectedTeacher,
    });

    const filterAssignments = () => {
        let list = assignments.data ?? [];
        if (teacherFilter) {
            const t = teacherFilter.toLowerCase();
            list = list.filter((a) => a.teacher.name.toLowerCase().includes(t));
        }
        if (filters.class) {
            const c = filters.class.toLowerCase();
            list = list.filter((a) => a.class.name.toLowerCase().includes(c));
        }
        if (filters.subject) {
            const s = filters.subject.toLowerCase();
            list = list.filter((a) => a.subject.name.toLowerCase().includes(s));
        }
        return list;
    };

    const startAssignment = () => {
        setStep(1);
        setSelectedClass('');
        setSelectedSubject('');
        setSelectedTeacher('');
        setEditing(null);
        form.reset({ class_id: '', subject_id: '', teacher_id: '' });
    };

    const startEdit = (a) => {
        setStep(1);
        setEditing(a);
        setSelectedClass(a.class.id);
        setSelectedSubject(a.subject.id);
        setSelectedTeacher(a.teacher?.id ?? '');
        form.reset({
            class_id: a.class.id,
            subject_id: a.subject.id,
            teacher_id: a.teacher?.id ?? '',
        });
    };

    const saveAssignment = (e) => {
        e.preventDefault();
        const url = editing
            ? route('teaching-assignments.update', editing.id)
            : route('teaching-assignments.store');
        const method = editing ? 'put' : 'post';
        router[method](url, {
            class_id: form.data.class_id,
            subject_id: form.data.subject_id,
            teacher_id: form.data.teacher_id,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setEditing(null);
                setStep(1);
                router.get(route('teaching-assignments.index'), { preserveState: true, replace: true });
            },
        });
    };

    const removeAssignment = (id) => {
        router.delete(route('teaching-assignments.destroy', id), {
            preserveScroll: true,
            onSuccess: () => router.get(route('teaching-assignments.index'), { preserveState: true, replace: true }),
        });
    };

    const filtered = filterAssignments();
    const activeCount = filtered.filter((a) => a.status === 'active').length;

    return (
        <DashboardLayout
            title="Teaching Assignments"
            subtitle="Assign teachers to subjects within classes"
        >
            <PageHeader
                title="Teaching Assignments"
                subtitle={`${filtered.length} assignment(s) · ${activeCount} active`}
                action={<PrimaryAction onClick={startAssignment}>Assign Teacher</PrimaryAction>}
            />

            {/* ---------- Filters ---------- */}
            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(route('teaching-assignments.index'), {
                            teacher: teacherFilter,
                            class: filters.class ?? '',
                            subject: filters.subject ?? '',
                            preserveState: true,
                            replace: true,
                        });
                    }}
                    className="relative flex-1"
                >
                    <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        className="input pl-10"
                        value={teacherFilter}
                        onChange={(e) => setTeacherFilter(e.target.value)}
                        placeholder="Filter by teacher…"
                    />
                </form>
                <select
                    className="input sm:w-48"
                    defaultValue={filters.class ?? ''}
                    onChange={(e) => {
                        const val = e.target.value;
                        router.get(route('teaching-assignments.index'), {
                            teacher: filters.teacher ?? '',
                            class: val,
                            subject: filters.subject ?? '',
                            preserveState: true,
                            replace: true,
                        });
                    }}
                >
                    <option value="">All classes</option>
                    {classes.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name} ({c.code})
                        </option>
                    ))}
                </select>
                <select
                    className="input sm:w-48"
                    defaultValue={filters.subject ?? ''}
                    onChange={(e) => {
                        const val = e.target.value;
                        router.get(route('teaching-assignments.index'), {
                            teacher: filters.teacher ?? '',
                            class: filters.class ?? '',
                            subject: val,
                            preserveState: true,
                            replace: true,
                        });
                    }}
                >
                    <option value="">All subjects</option>
                    {subjects.map((s) => (
                        <option key={s.id} value={s.id}>{s.name} ({s.code})</option>
                    ))}
                </select>
            </div>

            {/* ---------- Wizard ---------- */}
            <div className="mt-6 glass-card p-5 sm:p-6">
                <h3 className="font-display text-base font-bold text-ink mb-4">
                    {editing ? 'Change Teacher Assignment' : 'Assign Teacher to Subject'}
                </h3>

                {step === 1 && (
                    <>
                        <p className="text-sm text-slate-600 mb-4">Step 1 of 4 — Select Class</p>
                        <select
                            className="input w-full sm:w-80"
                            value={selectedClass}
                            onChange={(e) => {
                                setSelectedClass(e.target.value);
                                setSelectedSubject('');
                                setSelectedTeacher('');
                                form.setData('class_id', e.target.value);
                                form.setData('subject_id', '');
                                form.setData('teacher_id', '');
                            }}
                        >
                            <option value="">Select a class…</option>
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name} ({c.code})
                                </option>
                            ))}
                        </select>
                        <div className="mt-4 flex gap-2">
                            <button
                                type="button"
                                disabled={!selectedClass}
                                onClick={() => setStep(2)}
                                className={`btn-primary px-4 py-2 text-sm ${!selectedClass ? 'opacity-50 cursor-not-allowed' : ''}`}
                            >
                                Next: Subject
                            </button>
                        </div>
                    </>
                )}

                {step === 2 && (
                    <>
                        <p className="text-sm text-slate-600 mb-4">
                            Step 2 of 4 — Select Subject ({validSubjects.length} available for this class)
                        </p>
                        {validSubjects.length === 0 ? (
                            <p className="text-xs text-slate-500">
                                No subjects are allocated to this class. Add subject-class links in Subjects Management first.
                            </p>
                        ) : (
                            <select
                                className="input w-full sm:w-80"
                                value={selectedSubject}
                                onChange={(e) => {
                                    setSelectedSubject(e.target.value);
                                    setSelectedTeacher('');
                                    form.setData('subject_id', e.target.value);
                                    form.setData('teacher_id', '');
                                }}
                            >
                                <option value="">Select a subject…</option>
                                {validSubjects.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name} ({s.code})
                                    </option>
                                ))}
                            </select>
                        )}
                        <div className="mt-4 flex gap-2">
                            <button
                                type="button"
                                onClick={() => setStep(1)}
                                className="btn-ghost px-4 py-2 text-sm"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                disabled={!selectedSubject}
                                onClick={() => setStep(3)}
                                className={`btn-primary px-4 py-2 text-sm ${!selectedSubject ? 'opacity-50 cursor-not-allowed' : ''}`}
                            >
                                Next: Teacher
                            </button>
                        </div>
                    </>
                )}

                {step === 3 && (
                    <>
                        <p className="text-sm text-slate-600 mb-4">Step 3 of 4 — Select Teacher</p>
                        <select
                            className="input w-full sm:w-80"
                            value={selectedTeacher}
                            onChange={(e) => {
                                setSelectedTeacher(e.target.value);
                                form.setData('teacher_id', e.target.value);
                            }}
                        >
                            <option value="">Select an active teacher…</option>
                            {teachers.map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                        <div className="mt-4 flex gap-2">
                            <button
                                type="button"
                                onClick={() => setStep(2)}
                                className="btn-ghost px-4 py-2 text-sm"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                disabled={!selectedTeacher}
                                onClick={() => setStep(4)}
                                className={`btn-primary px-4 py-2 text-sm ${!selectedTeacher ? 'opacity-50 cursor-not-allowed' : ''}`}
                            >
                                Review & Save
                            </button>
                        </div>
                    </>
                )}

                {step === 4 && (
                    <>
                        <p className="text-sm text-slate-600 mb-4">Step 4 of 4 — Confirm</p>
                        <div className="space-y-2">
                            <p className="text-sm">
                                <span className="font-semibold">Class:</span> {classes.find((c) => c.id == form.data.class_id)?.name}
                            </p>
                            <p className="text-sm">
                                <span className="font-semibold">Subject:</span> {subjects.find((s) => s.id == form.data.subject_id)?.name}
                            </p>
                            <p className="text-sm">
                                <span className="font-semibold">Teacher:</span> {teachers.find((t) => t.id == form.data.teacher_id)?.name}
                            </p>
                        </div>
                        <div className="mt-6 flex gap-3">
                            <button
                                type="button"
                                onClick={() => setStep(3)}
                                className="btn-ghost px-4 py-2 text-sm"
                            >
                                Back
                            </button>
                            <form onSubmit={saveAssignment}>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="btn-primary px-4 py-2 text-sm"
                                >
                                    {form.processing ? 'Saving…' : editing ? 'Update Assignment' : 'Save Assignment'}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setStep(1)}
                                    className="text-xs text-slate-500 hover:underline ml-2"
                                >
                                    Cancel
                                </button>
                            </form>
                        </div>
                    </>
                )}
            </div>

            {/* ---------- Table ---------- */}
            <div className="mt-6 glass-card p-4 sm:p-5">
                {filtered.length === 0 ? (
                    <EmptyState
                        title="No teaching assignments"
                        hint="Assign a teacher to a subject in a class to get started."
                    />
                ) : (
                    <>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        <th className="pb-3 font-bold">Teacher</th>
                                        <th className="pb-3 font-bold">Class</th>
                                        <th className="pb-3 font-bold">Subject</th>
                                        <th className="pb-3 font-bold text-center">Status</th>
                                        <th className="pb-3 font-bold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filtered.map((a) => (
                                        <tr key={a.id} className="transition hover:bg-canvas/60">
                                            <td className="py-3 font-bold text-ink">
                                                {a.teacher.name}
                                                <span className="block text-[11px] text-slate-400 mt-0.5">
                                                    {a.teacher.status}
                                                </span>
                                            </td>
                                            <td className="py-3 text-slate-600">
                                                {a.class.name} <span className="font-mono text-xs text-slate-400">({a.class.code})</span>
                                            </td>
                                            <td className="py-3 text-slate-600">
                                                {a.subject.name}
                                                <span className="block text-[11px] text-slate-400 mt-0.5">({a.subject.code})</span>
                                            </td>
                                            <td className="py-3 text-center">
                                                <Badge tone={a.status === 'active' ? 'success' : 'muted'}>
                                                    {a.status === 'active' ? 'Active' : 'Unassigned'}
                                                </Badge>
                                            </td>
                                            <td className="py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => startEdit(a)}
                                                    className="text-xs text-primary hover:underline mr-2"
                                                >
                                                    Change Teacher
                                                </button>
                                                {a.status === 'active' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => removeAssignment(a.id)}
                                                        className="text-xs text-rose-600 hover:underline"
                                                    >
                                                        Remove
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="space-y-3 sm:hidden">
                            {filtered.map((a) => (
                                <li key={a.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-bold text-ink">{a.teacher.name}</p>
                                            <p className="text-xs text-slate-400">{a.teacher.status}</p>
                                        </div>
                                        <Badge tone={a.status === 'active' ? 'success' : 'muted'}>
                                            {a.status === 'active' ? 'Active' : 'Unassigned'}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-xs text-slate-500">
                                        {a.class.name} · {a.subject.name}
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <button
                                            type="button"
                                            onClick={() => startEdit(a)}
                                            className="flex-1 btn-ghost py-1.5 text-xs text-center text-primary"
                                        >
                                            Change Teacher
                                        </button>
                                        {a.status === 'active' && (
                                            <button
                                                type="button"
                                                onClick={() => removeAssignment(a.id)}
                                                className="flex-1 btn-ghost py-1.5 text-xs text-center text-rose-600"
                                            >
                                                Remove
                                            </button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="mt-4">
                            {assignments.current_page != null &&
                                assignments.last_page > 1 && (
                                <div className="flex items-center justify-between gap-1">
                                    <button
                                        onClick={() => {
                                            if (assignments.current_page > 1)
                                                router.get(route('teaching-assignments.index'), {
                                                    page: assignments.current_page - 1,
                                                    preserveState: true,
                                                    replace: true,
                                                });
                                        }}
                                        disabled={assignments.current_page <= 1}
                                        className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                                    </button>
                                    <span className="px-3 text-xs font-bold text-ink">
                                        {assignments.current_page} / {assignments.last_page}
                                    </span>
                                    <button
                                        onClick={() => {
                                            if (assignments.current_page < assignments.last_page)
                                                router.get(route('teaching-assignments.index'), {
                                                    page: assignments.current_page + 1,
                                                    preserveState: true,
                                                    replace: true,
                                                });
                                        }}
                                        disabled={assignments.current_page >= assignments.last_page}
                                        className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                                    >
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                                    </button>
                                </div>
                            )}
                        </div>
                    </>
                )}
            </div>
        </DashboardLayout>
    );
}

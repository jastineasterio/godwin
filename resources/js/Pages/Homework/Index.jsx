import { useState } from 'react';
import { CalendarDays, ChevronRight, FileText, Plus, Save, Trash2 } from 'lucide-react';
import { Link, router, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { PageHeader, PrimaryAction } from '../../Components/page';
import { useForm } from '@inertiajs/react';

const STATUS_TONE = { draft: 'muted', published: 'secondary', archived: 'danger' };

export default function HomeworkIndex({ homeworks, classes, subjects, filters }) {
    const [deleting, setDeleting] = useState(null);

    const apply = (extra = {}) =>
        router.get(
            '/homework',
            { class_id: filters.class_id ?? '', subject_id: filters.subject_id ?? '', status: filters.status ?? '', ...extra },
            { preserveState: true, replace: true }
        );

    const form = useForm({
        class_id: '',
        subject_id: '',
        title: '',
        description: '',
        assigned_on: new Date().toISOString().slice(0, 10),
        due_on: '',
        status: 'published',
    });

    const isOpen = !!form.data._showForm;
    const toggleForm = () => form.setData('_showForm', !isOpen);

    const submit = (e) => {
        e.preventDefault();
        form.post('/homework', {
            onSuccess: () => {
                form.reset();
                toggleForm();
            },
        });
    };

    return (
        <DashboardLayout
            title="Homework"
            subtitle="Class assignments and activities for your students"
        >
            <PageHeader
                title="Homework"
                subtitle={`${homeworks.total ?? 0} assignment(s)`}
                action={
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={toggleForm}
                            className="btn-primary"
                        >
                            <Plus className="h-4 w-4" /> {isOpen ? 'Hide' : 'Post'} Homework
                        </button>
                    </div>
                }
            />

            <div className="mb-4 flex flex-wrap gap-2">
                <select
                    className="input w-auto min-w-[150px]"
                    value={filters.class_id ?? ''}
                    onChange={(e) => apply({ class_id: e.target.value })}
                >
                    <option value="">All classes</option>
                    {classes.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                </select>
                <select
                    className="input w-auto min-w-[160px]"
                    value={filters.subject_id ?? ''}
                    onChange={(e) => apply({ subject_id: e.target.value })}
                >
                    <option value="">All subjects</option>
                    {subjects.map((s) => (
                        <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                </select>
                <select
                    className="input w-auto min-w-[140px]"
                    value={filters.status ?? ''}
                    onChange={(e) => apply({ status: e.target.value })}
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            {isOpen && (
                <div className="glass-card p-4 sm:p-5 mb-6">
                    <form onSubmit={submit}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <label className="label block">Title <span className="text-primary">*</span></label>
                                <input
                                    type="text"
                                    value={form.data.title}
                                    onChange={(e) => form.setData('title', e.target.value)}
                                    className={`input ${form.errors.title ? 'border-rose-300' : ''}`}
                                />
                                {form.errors.title && (
                                    <span className="mt-1 block text-xs font-semibold text-rose-600">
                                        {form.errors.title}
                                    </span>
                                )}
                            </div>
                            <div>
                                <label className="label block">Class <span className="text-primary">*</span></label>
                                <select
                                    value={form.data.class_id}
                                    onChange={(e) => form.setData('class_id', e.target.value)}
                                    className="input"
                                >
                                    <option value="">Select class…</option>
                                    {classes.map((c) => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="label block">Subject</label>
                                <select
                                    value={form.data.subject_id}
                                    onChange={(e) => form.setData('subject_id', e.target.value)}
                                    className="input"
                                >
                                    <option value="">No subject</option>
                                    {subjects.map((s) => (
                                        <option key={s.id} value={s.id}>{s.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="label block">Assigned On <span className="text-primary">*</span></label>
                                <input
                                    type="date"
                                    value={form.data.assigned_on}
                                    onChange={(e) => form.setData('assigned_on', e.target.value)}
                                    className="input"
                                />
                            </div>
                            <div>
                                <label className="label block">Due On</label>
                                <input
                                    type="date"
                                    value={form.data.due_on}
                                    onChange={(e) => form.setData('due_on', e.target.value)}
                                    className="input"
                                />
                            </div>
                            <div>
                                <label className="label block">Status</label>
                                <select
                                    value={form.data.status}
                                    onChange={(e) => form.setData('status', e.target.value)}
                                    className="input"
                                >
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                </select>
                            </div>
                            <div className="sm:col-span-2">
                                <label className="label block">Description <span className="text-primary">*</span></label>
                                <textarea
                                    rows={3}
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    className={`input ${form.errors.description ? 'border-rose-300' : ''}`}
                                />
                                {form.errors.description && (
                                    <span className="mt-1 block text-xs font-semibold text-rose-600">
                                        {form.errors.description}
                                    </span>
                                )}
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={toggleForm}
                                className="btn-ghost px-4 py-2"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="btn-primary px-4 py-2"
                            >
                                <Save className="h-4 w-4 mr-2" />
                                {form.processing ? 'Posting…' : 'Post Homework'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            <div className="glass-card p-4 sm:p-5">
                {homeworks.data.length === 0 ? (
                    <EmptyState
                        title="No homework posted yet"
                        hint="Post your first homework assignment to share with students and parents."
                    />
                ) : (
                    <>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        <th className="pb-3 font-bold">Title</th>
                                        <th className="pb-3 font-bold">Class</th>
                                        <th className="pb-3 font-bold">Subject</th>
                                        <th className="pb-3 font-bold">Due Date</th>
                                        <th className="pb-3 font-bold">Status</th>
                                        <th className="pb-3 font-bold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {homeworks.data.map((h) => (
                                        <tr key={h.id} className="transition hover:bg-canvas/60">
                                            <td className="py-3 font-bold text-ink">{h.title}</td>
                                            <td className="py-3 text-slate-600">
                                                {h.class} <span className="text-xs text-slate-400">({h.class_code})</span>
                                            </td>
                                            <td className="py-3 text-slate-600">{h.subject ?? '—'}</td>
                                            <td className="py-3 text-slate-600">{h.due_on ?? '—'}</td>
                                            <td className="py-3">
                                                <Badge tone={STATUS_TONE[h.status] ?? 'muted'}>
                                                    {h.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3 text-right">
                                                {h.is_own && (
                                                    <>
                                                        <Link
                                                            href={`/homework/${h.id}`}
                                                            className="text-xs text-primary hover:underline mr-2"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            onClick={() => setDeleting(h)}
                                                            className="text-xs text-rose-600 hover:underline"
                                                        >
                                                            Delete
                                                        </button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="space-y-3 sm:hidden">
                            {homeworks.data.map((h) => (
                                <li key={h.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-bold text-ink">{h.title}</p>
                                            <p className="text-xs text-slate-500">
                                                {h.class} · {h.subject ?? 'No subject'} · Due {h.due_on ?? '—'}
                                            </p>
                                        </div>
                                        <Badge tone={STATUS_TONE[h.status] ?? 'muted'}>
                                            {h.status}
                                        </Badge>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {h.is_own && (
                                            <>
                                                <Link
                                                    href={`/homework/${h.id}`}
                                                    className="btn-ghost px-3 py-1.5 text-xs"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() => setDeleting(h)}
                                                    className="btn bg-rose-50 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-100"
                                                >
                                                    Delete
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    router.delete(`/homework/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Delete homework?"
                message={`"${deleting?.title ?? ''}" will be removed.`}
                confirmLabel="Delete"
            />
        </DashboardLayout>
    );
}

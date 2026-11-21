import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Eye, Pencil, Trash2, X } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, StatCard, EmptyState } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { FilterBar, PageHeader, Pagination, PrimaryAction } from '../../Components/page';

const STATUS_TONES = { Active: 'success', Inactive: 'muted' };

export default function ClassesIndex({
    classes,
    teachers,
    levels,
    filters,
    stats,
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [levelFilter, setLevelFilter] = useState(filters.level ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [deleting, setDeleting] = useState(null);
    const [createOpen, setCreateOpen] = useState(false);

    const apply = (extra = {}) => {
        router.get(
            '/classes',
            { search, level: levelFilter, status: statusFilter, ...extra },
            { preserveState: true, replace: true },
        );
    };

    const form = useForm({
        name: '', code: '', level: '', teacher_id: '', capacity: '', description: '',
    });

    const createSubmit = (event) => {
        event.preventDefault();
        form.post('/classes', {
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpen(false);
                form.reset();
                apply();
            },
        });
    };

    const doDelete = () => {
        if (!deleting) return;
        router.delete(`/classes/${deleting.id}`, { preserveScroll: true });
        setDeleting(null);
    };

    const totalClasses = stats?.find((s) => s.label === 'Total Classes')?.value ?? classes.total ?? 0;
    const activeClasses = stats?.find((s) => s.label === 'Active Classes')?.value ?? 0;
    const totalStudents = stats?.find((s) => s.label === 'Total Students Assigned')?.value ?? 0;
    const availableCapacity = stats?.find((s) => s.label === 'Available Class Capacity')?.value ?? 0;

    return (
        <DashboardLayout title="Class Management" subtitle="Create, view, edit and archive school classes.">
            <PageHeader
                title="Class Management"
                subtitle={`Managing ${totalClasses} school classes.`}
                action={<button type="button" onClick={() => setCreateOpen(true)} className="btn-primary">
                    New Class
                </button>}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <StatCard index={0} label="Total Classes" value={totalClasses} accent="primary" />
                <StatCard index={1} label="Active Classes" value={activeClasses} accent="secondary" />
                <StatCard index={2} label="Total Students Assigned" value={totalStudents} accent="accent" />
                <StatCard index={3} label="Available Class Capacity" value={availableCapacity} accent="primary" />
            </div>

            <FilterBar
                value={search}
                onChange={setSearch}
                onSubmit={() => apply()}
                placeholder="Search class name or code…"
            >
                <select className="input w-auto min-w-[150px]" value={levelFilter} onChange={(e) => apply({ level: e.target.value })}>
                    <option value="">All levels</option>
                    {levels.map((l) => <option key={l.value} value={l.value}>{l.label}</option>)}
                </select>
                <select className="input w-auto min-w-[140px]" value={statusFilter} onChange={(e) => apply({ status: e.target.value })}>
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </FilterBar>

            <div className="glass-card p-4 sm:p-5">
                {classes.data.length === 0 ? (
                    <EmptyState title="No classes found" hint="Create a new class or try another search term." />
                ) : (
                    <>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        {['Class Name', 'Class Code', 'Level', 'Class Teacher', 'Students', 'Capacity', 'Status', 'Actions'].map((h) => (
                                            <th key={h} className="whitespace-nowrap pb-3 font-bold">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {classes.data.map((klass) => (
                                        <Row key={klass.id} klass={klass} onDelete={() => setDeleting(klass)} />
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="space-y-3 sm:hidden">
                            {classes.data.map((klass) => (
                                <li key={klass.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate font-bold text-ink">{klass.name}</p>
                                            <p className="font-mono text-xs text-slate-500">{klass.code}</p>
                                        </div>
                                        <Badge tone={STATUS_TONES[klass.status] ?? 'muted'}>{klass.status}</Badge>
                                    </div>
                                    <p className="mt-2 text-sm text-slate-600 capitalize">{klass.level} · {klass.students}/{klass.capacity} students</p>
                                    <p className="mt-1 text-xs text-slate-500">Teacher: {klass.teacher ?? 'Unassigned'}</p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Link href={`/classes/${klass.id}`} className="btn-ghost px-3 py-1.5 text-xs">
                                            <Eye className="h-3.5 w-3.5" /> View
                                        </Link>
                                        <Link href={`/classes/${klass.id}/edit`} className="btn-ghost px-3 py-1.5 text-xs">
                                            <Pencil className="h-3.5 w-3.5" /> Edit
                                        </Link>
                                        <button type="button" onClick={() => confirmDelete(klass)} className="btn bg-rose-50 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-100">
                                            <Trash2 className="h-3.5 w-3.5" /> Delete
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <Pagination meta={classes.meta} onPage={(page) => apply({ page })} />
                    </>
                )}
            </div>

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={doDelete}
                title={`Archive class "${deleting?.name ?? ''}"?`}
                message={deleting ? `Archiving "${deleting.name}" (${deleting.code}) deactivates it. Students and their academic/financial records are preserved.` : ''}
                confirmLabel="Archive Class"
            />

            <CreateClassModal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                form={form}
                teachers={teachers}
                levels={levels}
                onSubmit={createSubmit}
            />
        </DashboardLayout>
    );
}

function CreateClassModal({ open, onClose, form, teachers, levels, onSubmit }) {
    return (
        <Modal open={open} onClose={onClose} title="Create New Class" subtitle="Enter class details." size="md"
            footer={
                <>
                    <button type="button" onClick={onClose} className="btn-ghost">Cancel</button>
                    <button type="button" onClick={onSubmit} disabled={form.processing} className="btn-primary">
                        {form.processing ? 'Creating…' : 'Create Class'}
                    </button>
                </>
            }
        >
            <form onSubmit={onSubmit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Class Name *" required>
                        <TextInput value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. KG1 Sunshine" error={!!form.errors.name} />
                    </Field>
                    <Field label="Class Code *" required>
                        <TextInput value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder="e.g. KG1-A" error={!!form.errors.code} />
                    </Field>
                    <Field label="Level *" required>
                        <Select value={form.data.level} onChange={(e) => form.setData('level', e.target.value)} error={!!form.errors.level}>
                            <option value="">Select level</option>
                            {levels.map((l) => <option key={l.value} value={l.value}>{l.label}</option>)}
                        </Select>
                    </Field>
                    <Field label="Class Teacher">
                        <Select value={form.data.teacher_id} onChange={(e) => form.setData('teacher_id', e.target.value)} error={!!form.errors.teacher_id}>
                            <option value="">Unassigned</option>
                            {teachers.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </Select>
                    </Field>
                    <Field label="Maximum Capacity *" required>
                        <TextInput type="number" value={form.data.capacity} onChange={(e) => form.setData('capacity', e.target.value)} placeholder="e.g. 25" error={!!form.errors.capacity} />
                    </Field>
                </div>
                <Field label="Description">
                    <Textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Optional class description" error={!!form.errors.description} />
                </Field>
            </form>
        </Modal>
    );
}

function Field({ label, required = false, children }) {
    return (
        <label className="block">
            <span className="label">{label} {required && <span className="text-primary">*</span>}</span>
            {children}
        </label>
    );
}
function TextInput({ value, onChange, placeholder, error = false, className = '', ...props }) {
    return (
        <input {...props} value={value} onChange={onChange} placeholder={placeholder}
            className={`input ${error ? 'border-rose-300 focus:border-rose-500' : ''} ${className}`} />
    );
}
function Select({ value, onChange, children, error = false, className = '', ...props }) {
    return (
        <select {...props} value={value} onChange={onChange}
            className={`input ${error ? 'border-rose-300 focus:border-rose-500' : ''} ${className}`}>
            {children}
        </select>
    );
}
function Textarea({ value, onChange, placeholder, error = false, className = '', ...props }) {
    return (
        <textarea {...props} value={value} onChange={onChange} placeholder={placeholder}
            className={`input ${error ? 'border-rose-300 focus:border-rose-500' : ''} ${className}`} />
    );
}
function Modal({ open, onClose, title, subtitle, children, footer, size = 'md' }) {
    if (!open) return null;
    const widths = { sm: 'sm:max-w-sm', md: 'sm:max-w-lg', lg: 'sm:max-w-2xl' };
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            <div className="absolute inset-0 bg-ink/60 backdrop-blur-sm" onClick={onClose} />
            <div className={`relative max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:rounded-2xl sm:p-6 ${widths[size]}`}>
                <header className="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 className="font-display text-lg font-bold text-ink">{title}</h3>
                        {subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}
                    </div>
                    <button type="button" onClick={onClose} className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-slate-200" aria-label="Close">
                        <X className="h-4 w-4" />
                    </button>
                </header>
                {children}
                {footer && <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">{footer}</div>}
            </div>
        </div>
    );
}

function Row({ klass, onDelete }) {
    const btn = 'grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition';

    return (
        <tr className="transition hover:bg-canvas/60">
            <td className="py-3">
                <Link href={`/classes/${klass.id}`} className="font-bold text-ink hover:text-primary">{klass.name}</Link>
            </td>
            <td className="py-3 font-mono text-xs text-slate-500">{klass.code}</td>
            <td className="py-3 capitalize text-slate-600">{klass.level}</td>
            <td className="py-3 text-slate-600">{klass.teacher ?? 'Unassigned'}</td>
            <td className="py-3 font-bold text-ink">{klass.students}</td>
            <td className="py-3 text-slate-600">{klass.capacity}</td>
            <td className="py-3"><Badge tone={STATUS_TONES[klass.status] ?? 'muted'}>{klass.status}</Badge></td>
            <td className="py-3">
                <div className="flex items-center justify-end gap-1">
                    <Link href={`/classes/${klass.id}`} title="View" className={`${btn} hover:bg-slate-100 hover:text-secondary`}>
                        <Eye className="h-4 w-4" />
                    </Link>
                    <Link href={`/classes/${klass.id}/edit`} title="Edit" className={`${btn} hover:bg-slate-100 hover:text-primary`}>
                        <Pencil className="h-4 w-4" />
                    </Link>
                    <button type="button" onClick={() => onDelete(klass)} title="Delete" className={`${btn} hover:bg-rose-50 hover:text-rose-600`}>
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            </td>
        </tr>
    );
}

import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import {
    Banknote,
    CalendarCheck2,
    ClipboardCheck,
    Mail,
    Pencil,
    Phone,
    Plus,
    Printer,
    Star,
    UserPlus,
    Users,
} from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, StatCard } from '../../Components/ui';
import { Modal } from '../../Components/modal';
import { Field, Select, TextInput } from '../../Components/form';
import { PageHeader } from '../../Components/page';

export default function StudentShow({
    student, parents, availableParents, relationships, stats, recentAssessments,
}) {
    const [linkOpen, setLinkOpen] = useState(false);

    const linkForm = useForm({
        parent_id: '',
        parent_name: '',
        parent_phone: '',
        parent_email: '',
        relationship_type: 'mother',
        is_primary_contact: true,
    });

    const unlink = (parentId) => {
        if (confirm('Remove this parent from the student?')) {
            router.delete(`/students/${student.id}/parents/${parentId}`, { preserveScroll: true });
        }
    };

    const submitLink = (event) => {
        event.preventDefault();
        linkForm.post(`/students/${student.id}/parents`, {
            preserveScroll: true,
            onSuccess: () => {
                setLinkOpen(false);
                linkForm.reset();
            },
        });
    };

    return (
        <DashboardLayout title="Student Profile" subtitle="Full record, attendance and academic history.">
            <PageHeader
                title={student.full_name}
                subtitle={`${student.reg_no} · ${student.class ?? 'No class'} · ${student.status_label}`}
                back="/students"
                action={
                    <div className="flex flex-wrap gap-2">
                        <a href={`/students/${student.id}/report-card`} target="_blank" rel="noreferrer" className="btn-ghost">
                            <Printer className="h-4 w-4" /> Report Card
                        </a>
                        <a href={`/students/${student.id}/fee-statement`} target="_blank" rel="noreferrer" className="btn-ghost">
                            <Banknote className="h-4 w-4" /> Fee Statement
                        </a>
                        <Link href={`/students/${student.id}/edit`} className="btn-primary">
                            <Pencil className="h-4 w-4" /> Edit
                        </Link>
                    </div>
                }
            />

            {/* ---------- KPI strip ---------- */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard index={0} icon={CalendarCheck2} label="Attendance" value={`${stats.attendance_rate ?? 0}%`} accent="primary" />
                <StatCard index={1} icon={ClipboardCheck} label="Assessments" value={stats.assessments} accent="secondary" />
                <StatCard index={2} icon={Banknote} label="Invoices" value={stats.invoices} accent="accent" />
                <StatCard index={3} icon={Star} label="Fee Balance" value={`TZS ${Math.round(stats.balance).toLocaleString()}`} accent="primary" />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                {/* ---------- Details ---------- */}
                <Card title="Student Details" className="lg:col-span-1">
                    <dl className="space-y-3 text-sm">
                        {[
                            ['Full Name', student.full_name],
                            ['Registration No.', student.reg_no],
                            ['Gender', student.gender],
                            ['Date of Birth', student.dob ?? '—'],
                            ['Age', student.age ? `${student.age} years` : '—'],
                            ['Class', student.class ?? '—'],
                            ['Blood Group', student.blood_group ?? '—'],
                            ['Admission Date', student.admission_date ?? '—'],
                            ['Previous School', student.previous_school ?? '—'],
                        ].map(([label, value]) => (
                            <div key={label} className="flex items-start justify-between gap-3">
                                <dt className="text-slate-500">{label}</dt>
                                <dd className="text-right font-bold capitalize text-ink">{value}</dd>
                            </div>
                        ))}
                    </dl>

                    {student.medical_notes && (
                        <div className="mt-4 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-800">
                            🩺 {student.medical_notes}
                        </div>
                    )}
                </Card>

                {/* ---------- Parents & guardians ---------- */}
                <Card
                    title="Parents & Guardians"
                    subtitle="Linked portal accounts"
                    className="lg:col-span-2"
                    action={
                        <button type="button" onClick={() => setLinkOpen(true)} className="btn-primary px-3 py-1.5 text-xs">
                            <UserPlus className="h-3.5 w-3.5" /> Link Parent
                        </button>
                    }
                >
                    {parents.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-500">
                            No parent linked yet — parents can see this child's records once linked.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {parents.map((parent) => (
                                <li key={parent.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-canvas p-4">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-bold text-ink">{parent.name}</p>
                                            <Badge tone="secondary" className="capitalize">{parent.relationship}</Badge>
                                            {parent.is_primary && <Badge tone="primary">Primary</Badge>}
                                        </div>
                                        <p className="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                            <span className="flex items-center gap-1"><Phone className="h-3 w-3" />{parent.phone}</span>
                                            <span className="flex items-center gap-1"><Mail className="h-3 w-3" />{parent.email}</span>
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => unlink(parent.id)}
                                        className="btn bg-rose-50 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-100"
                                    >
                                        Unlink
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    {/* Recent academic results */}
                    <div className="mt-6">
                        <h4 className="flex items-center gap-2 text-sm font-bold text-ink">
                            <Users className="h-4 w-4 text-secondary" />
                            Recent Results
                        </h4>
                        {recentAssessments.length === 0 ? (
                            <p className="mt-2 text-sm text-slate-500">No assessments recorded yet.</p>
                        ) : (
                            <ul className="mt-3 space-y-2">
                                {recentAssessments.map((item) => (
                                    <li key={item.id} className="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3 py-2 text-sm">
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-ink">{item.title}</p>
                                            <p className="text-xs text-slate-500">{item.subject} · {item.date}</p>
                                        </div>
                                        <Badge tone={item.percentage >= 50 ? 'success' : 'danger'}>{item.percentage}%</Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </Card>
            </div>

            <ParentLinkModal
                open={linkOpen}
                onClose={() => setLinkOpen(false)}
                form={linkForm}
                availableParents={availableParents}
                relationships={relationships}
                onSubmit={submitLink}
            />
        </DashboardLayout>
    );
}

/**
 * Parent assignment modal — link an EXISTING parent account or create a
 * brand-new one in the same step.
 */
function ParentLinkModal({ open, onClose, form, availableParents, relationships, onSubmit }) {
    const mode = form.data.parent_id ? 'existing' : 'new';

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Link a Parent / Guardian"
            subtitle="Grant portal access to this child's records."
            footer={
                <>
                    <button type="button" onClick={onClose} className="btn-ghost">Cancel</button>
                    <button type="button" onClick={onSubmit} disabled={form.processing} className="btn-primary">
                        {form.processing ? 'Linking…' : 'Link Parent'}
                    </button>
                </>
            }
        >
            <form onSubmit={onSubmit} className="space-y-4">
                {availableParents.length > 0 && (
                    <Field label="Existing parent account" hint="Leave empty to create a new account.">
                        <Select
                            value={form.data.parent_id}
                            onChange={(e) => form.setData('parent_id', e.target.value)}
                        >
                            <option value="">— Create a new parent instead —</option>
                            {availableParents.map((p) => (
                                <option key={p.id} value={p.id}>{p.name} ({p.phone})</option>
                            ))}
                        </Select>
                    </Field>
                )}

                {mode === 'new' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Parent Full Name" required error={form.errors.parent_name}>
                            <TextInput
                                value={form.data.parent_name}
                                onChange={(e) => form.setData('parent_name', e.target.value)}
                                placeholder="Bwana Juma"
                            />
                        </Field>
                        <Field label="Phone Number" required error={form.errors.parent_phone}>
                            <TextInput
                                value={form.data.parent_phone}
                                onChange={(e) => form.setData('parent_phone', e.target.value)}
                                placeholder="0751000000"
                            />
                        </Field>
                        <div className="sm:col-span-2">
                            <Field label="Email" error={form.errors.parent_email} required={false}>
                                <TextInput
                                    type="email"
                                    value={form.data.parent_email}
                                    onChange={(e) => form.setData('parent_email', e.target.value)}
                                    placeholder="optional"
                                />
                            </Field>
                        </div>
                    </div>
                )}

                <Field label="Relationship to Child" required error={form.errors.relationship_type}>
                    <Select
                        value={form.data.relationship_type}
                        onChange={(e) => form.setData('relationship_type', e.target.value)}
                    >
                        {relationships.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                    </Select>
                </Field>

                <label className="flex cursor-pointer items-center gap-2.5">
                    <input
                        type="checkbox"
                        checked={!!form.data.is_primary_contact}
                        onChange={(e) => form.setData('is_primary_contact', e.target.checked)}
                        className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/30"
                    />
                    <span className="text-sm font-semibold text-ink">Set as primary contact</span>
                </label>
            </form>
        </Modal>
    );
}
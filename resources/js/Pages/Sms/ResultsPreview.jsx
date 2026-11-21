import { usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Card, Input, Label, Badge, Button, Select, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { GraduationCap, Phone, Send, Mail, Loader2, AlertCircle, CheckCircle2 } from 'lucide-react';

const maskPhone = (phone) => {
    if (!phone) return '';
    const last = phone.slice(-4);
    return '****' + last;
};

export default function SmsResultsPreview({
    examination,
    template,
    sample_message,
    total_students,
    total_parents,
    valid_phone_numbers,
    missing_phone_count,
    duplicate_recipients,
    students,
    missing_phone_students,
    class_id,
    student_ids: initialStudentIds = [],
}) {
    const { flash } = usePage().props;
    const form = useForm({
        examination_id: examination.id,
        class_id: class_id || '',
        template: template || '',
        student_ids: initialStudentIds || [],
        sender_id: '',
    });

    const [sending, setSending] = useState(false);
    const [success, setSuccess] = useState(null);
    const [error, setError] = useState(null);

    const studentsWithPhone = students?.filter((s) => s.has_phone) || [];
    const selectedCount = form.data.student_ids.length;

    const handleSelectAll = () => {
        form.setData('student_ids', studentsWithPhone.map((s) => s.student_id));
    };

    const handleSelectNone = () => {
        form.setData('student_ids', []);
    };

    const toggleStudent = (id) => {
        const ids = form.data.student_ids.includes(id)
            ? form.data.student_ids.filter((x) => x !== id)
            : [...form.data.student_ids, id];
        form.setData('student_ids', ids);
    };

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    const handleSend = async (e) => {
        e.preventDefault();
        setSending(true);
        setError(null);
        setSuccess(null);

        if (form.data.student_ids.length === 0) {
            setSending(false);
            setError('Please select at least one student to notify.');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('examination_id', form.data.examination_id);
            formData.append('class_id', form.data.class_id || '');
            form.data.student_ids.forEach((id) => formData.append('student_ids[]', id));
            formData.append('template', form.data.template);
            formData.append('sender_id', form.data.sender_id);

            const r = await fetch(route('sms.results.send'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            const data = await r.json();

            const sent = data.sent ?? 0;
            const failed = data.failed ?? 0;
            const skipped = data.skipped ?? 0;
            const duplicatesPrevented = data.duplicates_prevented ?? 0;

            if (!r.ok) {
                setSending(false);
                setError(data.error || 'SMS submission failed. No messages were accepted by the provider.');
                return;
            }

            if (sent > 0 && failed === 0) {
                setSuccess(`SMS request accepted. ${sent} recipient(s) queued. Delivery is pending.`);
            } else if (sent > 0 && failed > 0) {
                setError(`SMS partially accepted. ${sent} recipient(s) accepted, ${failed} failed.`);
            } else if (sent === 0 && failed > 0) {
                setError(data.error || 'SMS submission failed. No messages were accepted by the provider.');
            } else if (skipped > 0 || duplicatesPrevented > 0) {
                setSuccess('SMS submission processed. Check the details above for accepted, skipped, and duplicate-prevented recipients.');
            } else {
                setSuccess('SMS submission processed.');
            }
            setSending(false);
        } catch (err) {
            setSending(false);
            setError('Unexpected error while sending results SMS.');
        }
    };

    return (
        <DashboardLayout
            title="Preview Results SMS"
            subtitle={`Review personalized messages for "${examination.name}" before sending.`}
        >
            {flash && <Flash />}

            {success && (
                <Card className="mb-6 bg-emerald-50 border border-emerald-200 p-4">
                    <div className="flex items-start gap-3">
                        <CheckCircle2 className="mt-0.5 h-5 w-5 text-emerald-600 shrink-0" />
                        <div>
                            <p className="font-semibold text-emerald-800">{success}</p>
                        </div>
                    </div>
                </Card>
            )}

            {error && (
                <Card className="mb-6 p-4 bg-rose-50 border border-rose-100 text-rose-700">
                    {error}
                </Card>
            )}

            <div className="grid gap-6">
                {/* Summary cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="p-4">
                        <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Eligible Students</div>
                        <div className="text-2xl font-bold text-ink">{total_students}</div>
                    </Card>
                    <Card className="p-4">
                        <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Parents with Valid Phone</div>
                        <div className="text-2xl font-bold text-ink">{valid_phone_numbers}</div>
                    </Card>
                    <Card className="p-4">
                        <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Missing Phone</div>
                        <div className="text-2xl font-bold text-rose-600">{missing_phone_count}</div>
                    </Card>
                    <Card className="p-4">
                        <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Duplicate Recipients</div>
                        <div className="text-2xl font-bold text-amber-600">{Object.keys(duplicate_recipients || {}).length}</div>
                    </Card>
                </div>

                <div className="grid gap-6">
                    <Card className="col-span-1 lg:col-span-4">
                        <header className="mb-4 flex items-center justify-between gap-2">
                            <div>
                                <h3 className="font-display text-base font-bold text-ink">Template &amp; Selection</h3>
                                <p className="text-xs text-slate-500">
                                    Select students to notify. Only parents with valid phone numbers are eligible.
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button size="sm" onClick={handleSelectAll} disabled={studentsWithPhone.length === 0}>
                                    Select All
                                </Button>
                                <Button size="sm" variant="outline" onClick={handleSelectNone}>
                                    Select None
                                </Button>
                            </div>
                        </header>

                        <div className="mb-4">
                            <Label>SMS Template</Label>
                            <Textarea
                                value={form.data.template}
                                onChange={(e) => form.setData('template', e.target.value)}
                                rows={6}
                                disabled={sending}
                                placeholder="Dear {{parent_name}}, results for {{student_name}}..."
                            />
                            <p className="mt-1 text-xs text-slate-500">
                                {`Supported: {{parent_name}}, {{student_name}}, {{admission_number}}, {{class_name}},
                                {{academic_year}}, {{term}}, {{examination_name}}, {{subject_summary}},
                                {{total_marks}}, {{total_max_marks}}, {{average_percentage}}, {{overall_grade}},
                                {{school_name}}.`}
                            </p>
                        </div>

                        <div className="mb-4">
                            <Label>Sender ID</Label>
                            <Input
                                value={form.data.sender_id}
                                onChange={(e) => form.setData('sender_id', e.target.value)}
                                disabled={sending}
                                placeholder="(provider default)"
                            />
                            <p className="mt-1 text-xs text-slate-500">
                                Approved sender ID configured in SMS Provider Settings.
                            </p>
                        </div>

                        <div className="flex items-center justify-between gap-3 pt-4 border-t border-slate-100">
                            <span className="text-sm font-bold text-ink">
                                {selectedCount} of {studentsWithPhone.length} eligible students selected
                            </span>
                            <Button
                                onClick={handleSend}
                                disabled={
                                    sending ||
                                    selectedCount === 0 ||
                                    !form.data.template.trim()
                                }
                            >
                                {sending ? (
                                    <>
                                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                        Sending...
                                    </>
                                ) : (
                                    <>
                                        <Send className="w-4 h-4 mr-2" />
                                        Send SMS ({selectedCount})
                                    </>
                                )}
                            </Button>
                        </div>
                    </Card>

                    {/* Student messages preview */}
                    <Card className="col-span-1 lg:col-span-8 overflow-hidden">
                        <header className="mb-4 flex items-center justify-between gap-2">
                            <h3 className="font-display text-base font-bold text-ink">Personalized Message Preview</h3>
                        </header>

                        {missing_phone_students?.length && (
                            <div className="p-3 rounded bg-rose-50 border border-rose-100 mb-4">
                                <p className="text-xs text-rose-700 font-bold mb-1">
                                    Students without a valid guardian phone (skipped):
                                </p>
                                <ul className="text-xs text-rose-600 mt-1 list-disc list-inside">
                                    {missing_phone_students.map((s) => (
                                        <li key={s.student_id}>{s.student_name} ({s.reg_no})</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {studentsWithPhone.length === 0 && (
                            <div className="p-8 text-center text-slate-500">
                                No eligible students with valid phone numbers for this examination.
                            </div>
                        )}

                        <div className="overflow-x-auto max-h-[60vh] overflow-y-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        <th className="pb-3 font-bold w-10">
                                            <input
                                                type="checkbox"
                                                checked={selectedCount === studentsWithPhone.length && studentsWithPhone.length > 0}
                                                onChange={handleSelectAll}
                                                disabled={sending}
                                            />
                                        </th>
                                        <th className="pb-3 font-bold">Student</th>
                                        <th className="pb-3 font-bold">Class</th>
                                        <th className="pb-3 font-bold text-right">Average</th>
                                        <th className="pb-3 font-bold">Grade</th>
                                        <th className="pb-3 font-bold">Parent / Guardian</th>
                                        <th className="pb-3 font-bold">Phone</th>
                                        <th className="pb-3 font-bold">Message</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {studentsWithPhone.map((s) => (
                                        <tr key={s.student_id} className="hover:bg-canvas group">
                                            <td className="py-3">
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.student_ids.includes(s.student_id)}
                                                    onChange={() => toggleStudent(s.student_id)}
                                                    disabled={sending}
                                                />
                                            </td>
                                            <td className="py-3">
                                                <div className="font-semibold text-ink">{s.student_name}</div>
                                                <div className="text-xs text-slate-500 font-mono">{s.admission_number}</div>
                                            </td>
                                            <td className="py-3 text-slate-600">{s.class_name || '—'}</td>
                                            <td className="py-3 text-right text-slate-600 font-mono">
                                                {s.average_percentage}%
                                            </td>
                                            <td className="py-3">
                                                <Badge tone={s.is_complete ? 'success' : 'warning'}>
                                                    {s.overall_grade}
                                                    {!s.is_complete && ' (partial)'}
                                                </Badge>
                                            </td>
                                            <td className="py-3 text-slate-600">{s.parent_name || '—'}</td>
                                            <td className="py-3 font-mono text-slate-600">{maskPhone(s.parent_phone)}</td>
                                            <td className="py-3 text-slate-600 truncate max-w-xs">
                                                {s.message || '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}

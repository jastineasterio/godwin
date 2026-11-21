import { usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Card, Input, Label, Badge, Button, Select, Textarea, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Users, Phone, FileText, Send, AlertCircle, Loader2, Mail, CheckCircle2 } from 'lucide-react';

/** Template variables the system is allowed to substitute. */
const ALLOWED_VARIABLES = [
    'school_name',
    'parent_name',
    'student_name',
    'class_name',
    'term_name',
    'exam_name',
    'amount',
    'date',
];

/** Mask a phone number, showing only the last 4 digits. */
const maskPhone = (phone) => {
    if (!phone) return '';
    const last = phone.slice(-4);
    return '****' + last;
};

/** Detect template variables in a message that are not in the allowed list. */
const unresolvedVariables = (message) => {
    const used = [...new Set((message.match(/\{([a-z_]+)\}/g) || []).map((m) => m.slice(1, -1)))];
    return used.filter((v) => !ALLOWED_VARIABLES.includes(v));
};

export default function SmsBulk({ classes, templates, can_send }) {
    const { flash } = usePage().props;

    const form = useForm({
        recipient_type: 'parents_all',
        class_ids: [],
        student_ids: [],
        phone_numbers: [],
        message: '',
        sender_id: '',
        campaign_name: '',
    });

    const [previewing, setPreviewing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [sending, setSending] = useState(false);
    const [recipients, setRecipients] = useState([]);
    const [invalidPhones, setInvalidPhones] = useState([]);
    const [duplicates, setDuplicates] = useState({});
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

    const validCount = recipients.length;
    const invalidCount = invalidPhones.length;
    const duplicateCount = Object.keys(duplicates).length;
    const unresolved = unresolvedVariables(form.data.message);

    const recipientOptions = [
        { value: 'parents_all', label: 'All Parents / Guardians' },
        { value: 'parents_class', label: 'Parents by Class' },
        { value: 'parents_students', label: 'Parents of Selected Students' },
        { value: 'teachers', label: 'Teachers' },
        { value: 'staff', label: 'Staff' },
        { value: 'custom', label: 'Custom Phone Numbers' },
    ];

    const buildFormData = () => {
        const formData = new FormData();
        formData.append('recipient_type', form.data.recipient_type);
        form.data.class_ids.forEach((id) => formData.append('class_ids[]', id));
        form.data.student_ids.forEach((id) => formData.append('student_ids[]', id));
        form.data.phone_numbers.forEach((p) => formData.append('phone_numbers[]', p));
        return formData;
    };

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    const handlePreview = async (e) => {
        e.preventDefault();
        setPreviewing(false);
        setConfirming(false);
        setRecipients([]);
        setInvalidPhones([]);
        setDuplicates({});
        setError(null);
        setSuccess(null);

        try {
            const r = await fetch(route('sms.bulk.preview'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: buildFormData(),
            });

            const data = await r.json();

            if (!r.ok || !data.success) {
                setError(data.error || 'Failed to preview recipients.');
                return;
            }

            setRecipients(data.recipients || []);
            setInvalidPhones(data.invalid_phones || []);
            setDuplicates(data.duplicates || {});
            setPreviewing(true);
            setConfirming(true);
        } catch (err) {
            setError('Unexpected error while previewing recipients.');
        }
    };

    const handleSend = async (e) => {
        e.preventDefault();
        setSending(true);
        setError(null);
        setSuccess(null);

        if (!form.data.message.trim()) {
            setSending(false);
            setError('Message cannot be empty.');
            return;
        }

        if (recipients.length === 0) {
            setSending(false);
            setError('No recipients to send to. Please preview recipients first.');
            return;
        }

        try {
            const recipientList = recipients.map((r) => ({
                phone: r.phone,
                name: r.name || '',
            }));

            const formData = new FormData();
            recipientList.forEach((r, i) => {
                formData.append(`recipients[${i}][phone]`, r.phone);
                formData.append(`recipients[${i}][name]`, r.name);
            });
            formData.append('message', form.data.message);
            formData.append('sender_id', form.data.sender_id);
            formData.append('campaign_name', form.data.campaign_name);

            const r = await fetch(route('sms.bulk.send'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            const data = await r.json();

            if (!r.ok || !data.success) {
                setSending(false);
                setError(data.error || 'Failed to submit bulk SMS campaign.');
                return;
            }

            setSuccess(data.message || 'Bulk SMS campaign submitted.');
            setSending(false);
            setConfirming(false);
            setRecipients([]);
        } catch (err) {
            setSending(false);
            setError('Unexpected error while submitting the campaign.');
        }
    };

    const useTemplate = (template) => {
        form.setData('message', template.template);
    };

    const resetForm = () => {
        form.setData('message', '');
        form.setData('sender_id', '');
        form.setData('campaign_name', '');
        setSuccess(null);
        setError(null);
        setConfirming(false);
        setPreviewing(false);
        setRecipients([]);
    };

    return (
        <DashboardLayout title="Bulk SMS Composer" subtitle="Send SMS to parents, teachers, staff, or custom numbers.">
            {flash && <Flash />}

            {success && (
                <Card className="mb-6 bg-emerald-50 border border-emerald-200 p-4">
                    <div className="flex items-start gap-3">
                        <CheckCircle2 className="mt-0.5 h-5 w-5 text-emerald-600 shrink-0" />
                        <div>
                            <p className="font-semibold text-emerald-800">{success}</p>
                            {success && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={resetForm}
                                    className="mt-2 text-emerald-600 border-emerald-200 hover:bg-emerald-100"
                                >
                                    Send another
                                </Button>
                            )}
                        </div>
                    </div>
                </Card>
            )}

            {!can_send && (
                <Card className="mb-6">
                    <div className="p-4 text-center text-rose-600">
                        <p>You are not authorized to send SMS messages.</p>
                    </div>
                </Card>
            )}

            <div className="grid gap-6">
                <Card className="col-span-1 xl:col-span-4">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Compose SMS</h3>
                    </header>
                    <form onSubmit={handlePreview}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="col-span-2">
                                <Label>Recipient Type</Label>
                                <Select
                                    value={form.data.recipient_type}
                                    onChange={(e) => form.setData('recipient_type', e.target.value)}
                                    disabled={previewing || sending}
                                >
                                    {recipientOptions.map((o) => (
                                        <option key={o.value} value={o.value}>{o.label}</option>
                                    ))}
                                </Select>
                            </div>
                            {form.data.recipient_type === 'parents_class' && (
                                <div className="col-span-2">
                                    <Label>Select Classes</Label>
                                    <Select
                                        multiple
                                        value={form.data.class_ids}
                                        onChange={(e) => {
                                            const values = Array.from(e.target.selectedOptions).map((o) =>
                                                parseInt(o.value, 10)
                                            );
                                            form.setData('class_ids', values);
                                        }}
                                        disabled={previewing || sending}
                                    >
                                        {classes?.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name} ({c.code})
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            )}
                            {form.data.recipient_type === 'custom' && (
                                <div className="col-span-2">
                                    <Label>Custom Phone Numbers (comma-separated)</Label>
                                    <Textarea
                                        value={form.data.phone_numbers.join(', ')}
                                        onChange={(e) => {
                                            form.setData(
                                                'phone_numbers',
                                                e.target.value.split(',').map((s) => s.trim()).filter(Boolean)
                                            );
                                        }}
                                        rows={3}
                                        placeholder="2557XXXXXXXX, 2557YYYYYYYY"
                                        disabled={previewing || sending}
                                    />
                                    <p className="mt-1 text-xs text-slate-500">
                                        Tanzanian format: 2557XXXXXXXX or +2557XXXXXXXX
                                    </p>
                                </div>
                            )}
                            <div className="col-span-2">
                                <Label>Sender ID</Label>
                                <Input
                                    value={form.data.sender_id}
                                    onChange={(e) => form.setData('sender_id', e.target.value)}
                                    placeholder="DARSMS"
                                    disabled={previewing || sending}
                                />
                                <p className="mt-1 text-xs text-slate-500">
                                    Approved sender ID configured in SMS Provider Settings.
                                </p>
                            </div>
                            <div className="col-span-2">
                                <Label>Campaign Name (optional)</Label>
                                <Input
                                    value={form.data.campaign_name}
                                    onChange={(e) => form.setData('campaign_name', e.target.value)}
                                    placeholder="e.g. Term 1 Results"
                                    disabled={previewing || sending}
                                />
                            </div>
                            <div className="col-span-2">
                                <Label>Message</Label>
                                <Textarea
                                    value={form.data.message}
                                    onChange={(e) => form.setData('message', e.target.value)}
                                    rows={4}
                                    placeholder="Type your message here..."
                                    disabled={previewing || sending}
                                />
                                <p className="mt-1 text-xs text-slate-500">
                                    {form.data.message.length} / 1600 characters
                                </p>
                            </div>
                            <div className="col-span-2">
                                <Label>Available Templates</Label>
                                <Select
                                    value=""
                                    onChange={(e) => {
                                        const selected = templates?.find((t) => t.id === parseInt(e.target.value, 10));
                                        if (selected) useTemplate(selected);
                                        e.target.value = '';
                                    }}
                                    disabled={previewing || sending}
                                >
                                    <option value="">Select a template...</option>
                                    {templates?.map((t) => (
                                        <option key={t.id} value={t.id}>{t.name}</option>
                                    ))}
                                </Select>
                            </div>
                        </div>
                        <div className="col-span-2 pt-4">
                            <Button
                                type="submit"
                                disabled={previewing || sending || !can_send || !form.data.message.trim()}
                            >
                                {previewing ? 'Previewing...' : 'Preview Recipients'}
                            </Button>
                        </div>
                    </form>

                    {error && (
                        <div className="mt-4 p-3 rounded bg-rose-50 border border-rose-100">
                            <p className="text-xs text-rose-700">{error}</p>
                        </div>
                    )}
                </Card>

                {confirming && recipients.length > 0 && (
                    <Card className="col-span-1 xl:col-span-4">
                        <header className="mb-4 flex items-center justify-between gap-2">
                            <h3 className="font-display text-base font-bold text-ink">Confirm & Send</h3>
                        </header>

                        <div className="space-y-3 text-sm">
                            <div className="grid gap-2">
                                <span className="font-bold">Total recipients:</span>
                                <span className="font-bold text-ink">{recipients.length}</span>
                                <span className="font-bold">Valid phone numbers:</span>
                                <span className="font-bold text-emerald-600">{validCount}</span>
                                <span className="font-bold">Invalid / missing phones:</span>
                                <span className="font-bold text-rose-600">{invalidCount}</span>
                                <span className="font-bold">Duplicates removed:</span>
                                <span className="font-bold text-amber-600">{duplicateCount}</span>
                            </div>

                            {invalidPhones.length && (
                                <div className="p-3 rounded bg-rose-50 border border-rose-100">
                                    <p className="text-xs text-rose-700 font-bold mb-1">Invalid phone numbers:</p>
                                    <ul className="text-xs text-rose-600 mt-1 list-disc list-inside">
                                        {invalidPhones.map((p, i) => (
                                            <li key={i}>{p}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {duplicateCount && (
                                <div className="p-3 rounded bg-amber-50 border border-amber-100">
                                    <p className="text-xs text-amber-700 font-bold mb-1">
                                        Duplicate phone numbers removed before sending:
                                    </p>
                                    <ul className="text-xs text-amber-600 mt-1 list-disc list-inside">
                                        {Object.entries(duplicates).map(([phone, count]) => (
                                            <li key={phone}>
                                                {maskPhone(phone)} × {count}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <div className="p-3 rounded bg-slate-50 border border-slate-100">
                                <p className="text-xs font-bold text-slate-700 mb-1">Sender ID:</p>
                                <p className="text-xs font-mono text-slate-600">
                                    {form.data.sender_id || '(uses provider default)'}
                                </p>
                                <p className="text-xs font-bold text-slate-700 mb-1 mt-2">Message:</p>
                                    <p className="text-xs text-slate-600 break-words">{form.data.message}</p>
                            </div>

                            {unresolved.length && (
                                <div className="p-3 rounded bg-amber-50 border border-amber-100">
                                    <AlertCircle className="w-4 h-4 mr-1 text-amber-700" />
                                    <span className="text-xs text-amber-700">
                                        Message contains variables not in the allowed list or that cannot be
                                        resolved for bulk sending: {unresolved.join(', ')}. They will be sent as-is.
                                    </span>
                                </div>
                            )}

                            {form.data.message.match(/\{([a-z_]+)\}/g) && !unresolved.length && (
                                <div className="p-3 rounded bg-blue-50 border border-blue-100">
                                    <span className="text-xs text-blue-700">
                                        Message uses template variables. Bulk sending sends the same message to all
                                        recipients; variables are not personalized per recipient.
                                    </span>
                                </div>
                            )}
                        </div>

                        <div className="mt-5 flex items-center justify-between gap-3 pt-4 border-t border-slate-100">
                            <Button
                                variant="outline"
                                onClick={() => setConfirming(false)}
                                disabled={sending}
                            >
                                Back
                            </Button>
                            <Button
                                onClick={handleSend}
                                    disabled={
                                        sending ||
                                        !can_send ||
                                        !form.data.message.trim() ||
                                        recipients.length === 0
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
                                        Confirm & Send ({recipients.length} recipients)
                                    </>
                                )}
                            </Button>
                        </div>
                    </Card>
                )}

                {previewing && recipients.length > 0 && !confirming && (
                    <Card className="col-span-1 xl:col-span-4">
                        <header className="mb-4 flex items-center justify-between gap-2">
                            <h3 className="font-display text-base font-bold text-ink">Recipient Preview</h3>
                        </header>
                        <div className="space-y-4">
                            <div className="grid gap-2 text-sm">
                                <span className="font-bold">Total recipients:</span>
                                <span className="font-bold text-ink">{recipients.length}</span>
                                <span className="font-bold">Valid phone numbers:</span>
                                <span className="font-bold text-emerald-600">{validCount}</span>
                                <span className="font-bold">Invalid / missing phones:</span>
                                <span className="font-bold text-rose-600">{invalidCount}</span>
                                <span className="font-bold">Duplicate numbers removed:</span>
                                <span className="font-bold text-amber-600">{duplicateCount}</span>
                            </div>
                            {invalidPhones.length && (
                                <div className="p-3 rounded bg-rose-50 border border-rose-100">
                                    <p className="text-xs text-rose-700 font-bold mb-1">Invalid phone numbers:</p>
                                    <ul className="text-xs text-rose-600 mt-1 list-disc list-inside">
                                        {invalidPhones.map((p, i) => (
                                            <li key={i}>{p}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {duplicateCount && (
                                <div className="p-3 rounded bg-amber-50 border border-amber-100">
                                    <p className="text-xs text-amber-700 font-bold mb-1">
                                        Duplicates detected (removed before sending):
                                    </p>
                                    <ul className="text-xs text-amber-600 mt-1 list-disc list-inside">
                                        {Object.entries(duplicates).map(([phone, count]) => (
                                            <li key={phone}>
                                                {maskPhone(phone)} × {count}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                            <th className="pb-3 font-bold">Name</th>
                                            <th className="pb-3 font-bold">Phone</th>
                                            <th className="pb-3 font-bold">Type</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {recipients.map((r) => (
                                            <tr key={r.phone} className="hover:bg-canvas">
                                                <td className="py-2 text-slate-600">{r.name}</td>
                                                <td className="py-2 font-mono text-slate-600">{maskPhone(r.phone)}</td>
                                                <td className="py-2 text-slate-600">
                                                    {r.is_custom ? 'Custom' : 'Parent/Guardian'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="pt-4 border-t border-slate-100">
                                <Button
                                    onClick={() => setConfirming(true)}
                                    disabled={sending || !can_send}
                                >
                                    <Send className="w-4 h-4 mr-2" />
                                    Confirm & Send ({recipients.length} recipients)
                                </Button>
                            </div>
                        </div>
                    </Card>
                )}
            </div>
        </DashboardLayout>
    );
}

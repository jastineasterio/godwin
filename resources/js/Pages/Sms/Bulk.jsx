import { usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Card, Input, Label, Badge, Button, Select, Textarea, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Users, Phone, FileText, Send } from 'lucide-react';

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
    const [recipients, setRecipients] = useState([]);
    const [invalidPhones, setInvalidPhones] = useState([]);
    const [duplicates, setDuplicates] = useState({});

    const recipientOptions = [
        { value: 'parents_all', label: 'All Parents / Guardians' },
        { value: 'parents_class', label: 'Parents by Class' },
        { value: 'parents_students', label: 'Parents of Selected Students' },
        { value: 'teachers', label: 'Teachers' },
        { value: 'staff', label: 'Staff' },
        { value: 'custom', label: 'Custom Phone Numbers' },
    ];

    const handlePreview = (e) => {
        e.preventDefault();
        const formData = new FormData();
        formData.append('recipient_type', form.recipient_type);
        form.class_ids.forEach(id => formData.append('class_ids[]', id));
        form.student_ids.forEach(id => formData.append('student_ids[]', id));
        form.phone_numbers.forEach(p => formData.append('phone_numbers[]', p));

        fetch(route('sms.bulk.preview'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: formData,
        }).then(async (r) => {
            const data = await r.json();
            setRecipients(data.recipients || []);
            setInvalidPhones(data.invalid_phones || []);
            setDuplicates(data.duplicates || {});
            setPreviewing(true);
        });
    };

    const handleSend = (e) => {
        e.preventDefault();
        const recipientList = recipients.map(r => ({ phone: r.phone, name: r.name }));
        const formData = new FormData();
        formData.append('recipients', JSON.stringify(recipientList));
        formData.append('message', form.message);
        formData.append('sender_id', form.sender_id);
        formData.append('campaign_name', form.campaign_name);

        fetch(route('sms.bulk.send'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: formData,
        }).then(async (r) => {
            const data = await r.json();
            if (r.ok && data.success) {
                alert(data.message);
                window.location.href = '/sms/dashboard';
            } else {
                alert(data.error || 'Failed to send bulk SMS.');
            }
        });
    };

    const useTemplate = (template) => {
        form.setData('message', template.template);
    };

    return (
        <DashboardLayout title="Bulk SMS Composer" subtitle="Send SMS to parents, teachers, staff, or custom numbers.">
            {flash && <Flash />}

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
                                <Select value={form.recipient_type} onChange={(e) => form.setData('recipient_type', e.target.value)}>
                                    {recipientOptions.map((o) => (
                                        <option key={o.value} value={o.value}>{o.label}</option>
                                    ))}
                                </Select>
                            </div>
                            {form.recipient_type === 'parents_class' && (
                                <div className="col-span-2">
                                    <Label>Select Classes</Label>
                                    <Select multiple value={form.class_ids} onChange={(e) => {
                                        const values = Array.from(e.target.selectedOptions).map(o => parseInt(o.value));
                                        form.setData('class_ids', values);
                                    }}>
                                        {classes?.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name} ({c.code})</option>
                                        ))}
                                    </Select>
                                </div>
                            )}
                            {form.recipient_type === 'custom' && (
                                <div className="col-span-2">
                                    <Label>Custom Phone Numbers (comma-separated)</Label>
                                    <Textarea value={form.phone_numbers.join(', ')} onChange={(e) => {
                                        form.setData('phone_numbers', e.target.value.split(',').map(s => s.trim()).filter(Boolean));
                                    }} rows={3} placeholder="2557XXXXXXXX, 2557YYYYYYYY" />
                                    <p className="mt-1 text-xs text-slate-500">Tanzanian format: 2557XXXXXXXX</p>
                                </div>
                            )}
                            <div className="col-span-2">
                                <Label>Sender ID</Label>
                                <Input value={form.sender_id} onChange={(e) => form.setData('sender_id', e.target.value)} placeholder="DARSMS" />
                            </div>
                            <div className="col-span-2">
                                <Label>Campaign Name (optional)</Label>
                                <Input value={form.campaign_name} onChange={(e) => form.setData('campaign_name', e.target.value)} placeholder="e.g. Term 1 Results" />
                            </div>
                            <div className="col-span-2">
                                <Label>Message</Label>
                                <Textarea value={form.message} onChange={(e) => form.setData('message', e.target.value)} rows={4} placeholder="Type your message here..." />
                                <p className="mt-1 text-xs text-slate-500">Max 1600 characters.</p>
                            </div>
                            <div className="col-span-2">
                                <Label>Available Templates</Label>
                                <Select value="" onChange={(e) => {
                                    const selected = templates?.find(t => t.id === parseInt(e.target.value));
                                    if (selected) useTemplate(selected);
                                    e.target.value = '';
                                }}>
                                    <option value="">Select a template...</option>
                                    {templates?.map((t) => (
                                        <option key={t.id} value={t.id}>{t.name}</option>
                                    ))}
                                </Select>
                            </div>
                        </div>
                        <div className="col-span-2 pt-4">
                            <Button type="submit" disabled={previewing || !can_send}>
                                {previewing ? 'Previewing...' : 'Preview Recipients'}
                            </Button>
                        </div>
                    </form>
                </Card>

                {previewing && recipients.length > 0 && (
                    <Card className="col-span-1 xl:col-span-4">
                        <header className="mb-4 flex items-center justify-between gap-2">
                            <h3 className="font-display text-base font-bold text-ink">Recipient Preview</h3>
                        </header>
                        <div className="space-y-4">
                            <div className="grid gap-2 text-sm">
                                <span className="font-bold">Total Recipients:</span>
                                <span className="font-bold text-ink">{recipients.length}</span>
                                <span className="font-bold">Invalid / Missing Phones:</span>
                                <span className="font-bold text-rose-600">{invalidPhones.length}</span>
                                <span className="font-bold">Duplicate Numbers:</span>
                                <span className="font-bold text-amber-600">{Object.keys(duplicates).length}</span>
                            </div>
                            {invalidPhones.length && (
                                <div className="p-3 rounded bg-rose-50 border border-rose-100">
                                    <p className="text-xs text-rose-700 font-bold">Invalid phone numbers:</p>
                                    <ul className="text-xs text-rose-600 mt-1 list-disc list-inside">
                                        {invalidPhones.map((p, i) => <li key={i}>{p}</li>)}
                                    </ul>
                                </div>
                            )}
                            {Object.keys(duplicates).length && (
                                <div className="p-3 rounded bg-amber-50 border border-amber-100">
                                    <p className="text-xs text-amber-700 font-bold">Duplicate phone numbers detected (will be deduplicated):</p>
                                    <ul className="text-xs text-amber-600 mt-1 list-disc list-inside">
                                        {Object.entries(duplicates).map(([phone, count]) => (
                                            <li key={phone}>{phone} × {count}</li>
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
                                                <td className="py-2 font-mono text-slate-600">{r.phone}</td>
                                                <td className="py-2 text-slate-600">{r.is_custom ? 'Custom' : 'Parent/Guardian'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="pt-4 border-t border-slate-100">
                                <div className="mb-4">
                                    <Label>Message to Send</Label>
                                    <Textarea value={form.message} rows={3} className="bg-slate-50" />
                                </div>
                                <Button onClick={handleSend} disabled={!can_send}>
                                    <Send className="h-4 w-4 mr-2" /> Confirm & Send ({recipients.length} recipients)
                                </Button>
                            </div>
                        </div>
                    </Card>
                )}
            </div>
        </DashboardLayout>
    );
}

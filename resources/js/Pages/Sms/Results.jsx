import { usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Card, Input, Label, Badge, Button, Select, Textarea, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { GraduationCap, Phone, Send, Clock } from 'lucide-react';

export default function SmsResults({ examinations, default_template, templates }) {
    const { flash } = usePage().props;

    const form = useForm({
        examination_id: '',
        template: default_template || '',
        message: '',
        student_ids: [],
        is_resend: false,
        resend_reason: '',
        sender_id: '',
    });

    const [previewing, setPreviewing] = useState(false);
    const [previewData, setPreviewData] = useState(null);

    const handlePreview = (e) => {
        e.preventDefault();
        const formData = new FormData();
        formData.append('examination_id', form.examination_id);
        formData.append('template', form.template);

        fetch(route('sms.results.preview'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: formData,
        }).then(async (r) => {
            const data = await r.json();
            setPreviewData(data);
            setPreviewing(true);
        });
    };

    const handleSend = (e) => {
        e.preventDefault();
        const formData = new FormData();
        formData.append('examination_id', form.examination_id);
        formData.append('student_ids', JSON.stringify(form.student_ids));
        formData.append('message', form.message);
        formData.append('template_id', form.template_id || '');
        formData.append('sender_id', form.sender_id);
        formData.append('is_resend', form.is_resend ? '1' : '0');
        formData.append('resend_reason', form.resend_reason);

        fetch(route('sms.results.send'), {
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
                window.location.href = '/sms/results/history';
            } else {
                alert(data.error || 'Failed to send results SMS.');
            }
        });
    };

    const useTemplate = (template) => {
        form.setData('template', template.template);
        form.setData('message', template.template);
    };

    return (
        <DashboardLayout title="Results SMS" subtitle="Notify parents when student examination results are published.">
            {flash && <Flash />}

            <div className="grid gap-6">
                <Card className="col-span-1 xl:col-span-5">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Select Examination</h3>
                    </header>
                    <form onSubmit={handlePreview}>
                        <div className="space-y-4">
                            <div>
                                <Label>Examination</Label>
                                <Select value={form.examination_id} onChange={(e) => form.setData('examination_id', e.target.value)}>
                                    <option value="">Select an examination...</option>
                                    {examinations?.map((ex) => (
                                        <option key={ex.id} value={ex.id}>{ex.name} — {ex.academic_year} / {ex.term}</option>
                                    ))}
                                </Select>
                            </div>
                            <div>
                                <Label>Message Template</Label>
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
                            <div>
                                <Label>Custom Message</Label>
                                <Textarea value={form.message} onChange={(e) => form.setData('message', e.target.value)} rows={4} placeholder="Compose your results SMS message..." />
                                <p className="mt-1 text-xs text-slate-500">{'Allowed variables: {parent_name}, {student_name}, {class_name}, {term_name}, {exam_name}, {school_name}, {date}'}</p>
                            </div>
                            <div className="pt-4">
                                <Button type="submit" disabled={previewing}>
                                    {previewing ? 'Loading preview...' : 'Preview Recipients'}
                                </Button>
                            </div>
                        </div>
                    </form>
                </Card>

                {previewing && previewData && (
                    <Card className="col-span-1 xl:col-span-7">
                        <header className="mb-4 flex items-center justify-between gap-2">
                            <h3 className="font-display text-base font-bold text-ink">Preview: {previewData.examination?.name}</h3>
                        </header>
                        <div className="space-y-4">
                            <div className="grid gap-2 text-sm mb-4">
                                <span className="font-bold">Total Students:</span>
                                <span className="font-bold text-ink">{previewData.total_students}</span>
                                <span className="font-bold">With Valid Phones:</span>
                                <span className="font-bold text-emerald-600">{previewData.valid_phone_numbers}</span>
                                <span className="font-bold">Missing Phones:</span>
                                <span className="font-bold text-rose-600">{previewData.missing_phone_count}</span>
                                <span className="font-bold">Duplicate Recipients:</span>
                                <span className="font-bold text-amber-600">{Object.keys(previewData.duplicate_recipients || {}).length}</span>
                            </div>

                            {previewData.sample_message && (
                                <div className="p-4 rounded-xl bg-slate-50 border border-slate-100">
                                    <p className="text-xs font-bold uppercase tracking-wider text-slate-400">Sample Message</p>
                                    <p className="mt-1 text-slate-600">{previewData.sample_message}</p>
                                </div>
                            )}

                            {previewData.missing_phone_students?.length && (
                                <div className="p-3 rounded bg-rose-50 border border-rose-100">
                                    <p className="text-xs text-rose-700 font-bold">Students without guardian phone numbers:</p>
                                    <ul className="text-xs text-rose-600 mt-1 list-disc list-inside">
                                        {previewData.missing_phone_students.map((s) => (
                                            <li key={s.student_id}>{s.student_name} ({s.class_name})</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                            <th className="pb-3 font-bold">Student</th>
                                            <th className="pb-3 font-bold">Class</th>
                                            <th className="pb-3 font-bold">Guardian</th>
                                            <th className="pb-3 font-bold">Phone</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {previewData.students?.map((s) => (
                                            <tr key={s.student_id} className="hover:bg-canvas">
                                                <td className="py-2 font-bold text-ink">{s.student_name}</td>
                                                <td className="py-2 text-slate-600">{s.class_name}</td>
                                                <td className="py-2 text-slate-600">{s.parent_name ?? 'N/A'}</td>
                                                <td className="py-2 font-mono text-slate-600">{s.parent_phone}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="pt-4 border-t border-slate-100">
                                <Label>Final Message</Label>
                                <Textarea value={form.message} rows={4} onChange={(e) => form.setData('message', e.target.value)} />
                                <div className="mt-4 flex gap-2">
                                    <Button onClick={handleSend} disabled={!previewData.students.length}>
                                        <Send className="h-4 w-4 mr-2" /> Send Results SMS
                                    </Button>
                                </div>
                                <p className="mt-2 text-xs text-slate-500">Each parent will receive this message once. Duplicate notifications are prevented.</p>
                            </div>
                        </div>
                    </Card>
                )}
            </div>
        </DashboardLayout>
    );
}

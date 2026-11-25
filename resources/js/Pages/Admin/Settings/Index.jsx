import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Save, CheckCircle2 } from 'lucide-react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import { Flash } from '../../../Components/ui';
import { Field } from '../../../Components/form';

const SECTIONS = [
    { key: 'general', label: 'General', icon: '🏫' },
    { key: 'contact', label: 'Contact', icon: '📞' },
    { key: 'admissions', label: 'Admissions', icon: '📋' },
    { key: 'branding', label: 'Branding', icon: '🎨' },
];

const DEFAULTS = {
    general: {
        school_name: 'God-Win Daycare & Nursery School',
        school_motto: '',
        school_email: '',
        school_phone: '',
        school_address: '',
    },
    contact: {
        contact_email: '',
        contact_phone: '',
        whatsapp: '',
        address: '',
        google_maps_url: '',
    },
    admissions: {
        admissions_open: 'true',
        application_message: 'Admissions are currently open.',
        application_deadline: '',
        application_submitted_sms_enabled: 'true',
        application_accepted_sms_enabled: 'true',
    },
    branding: {
        primary_color: '#01579B',
        secondary_color: '#1E88E5',
        logo_url: '',
        favicon_url: '',
    },
};

export default function SettingsIndex({ settings, groups }) {
    const { flash } = usePage().props;
    const [activeTab, setActiveTab] = useState('general');

    const buildInitialData = () => {
        const data = {};
        groups.forEach((group) => {
            const defaults = DEFAULTS[group] || {};
            const groupSettings = settings?.[group] || [];
            const map = Object.fromEntries(
                groupSettings.map((s) => [s.key.replace(group + '.', ''), s.value])
            );
            Object.keys(defaults).forEach((key) => {
                data[key] = map[key] ?? defaults[key];
            });
        });
        return data;
    };

    const form = useForm(buildInitialData());

    const submit = (e) => {
        e.preventDefault();
        const groupDefaults = DEFAULTS[activeTab] || {};
        const payload = {};
        Object.keys(groupDefaults).forEach((key) => {
            payload[key] = form.data[key];
        });
        form.transform(() => payload);
        form.put(`/settings/${activeTab}`, {
            preserveScroll: true,
            onFinish: () => {
                form.transform((data) => data);
            },
        });
    };

    const isAdmissions = activeTab === 'admissions';

    return (
        <DashboardLayout title="Settings" subtitle="School system configuration">
            <Flash className="mb-5" />

            {flash?.success && (
                <div className="mb-5 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    <CheckCircle2 className="h-4 w-4" />
                    {flash.success}
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-4">
                {/* Tabs */}
                <div className="lg:col-span-1">
                    <div className="rounded-2xl border border-slate-200 bg-white p-2">
                        {SECTIONS.map((s) => (
                            <button
                                key={s.key}
                                type="button"
                                onClick={() => setActiveTab(s.key)}
                                className={`flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-semibold transition ${
                                    activeTab === s.key
                                        ? 'bg-primary text-white'
                                        : 'text-slate-600 hover:bg-slate-50'
                                }`}
                            >
                                <span className="text-base">{s.icon}</span>
                                {s.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Form */}
                <div className="lg:col-span-3">
                    <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-6">
                        <h2 className="section-title mb-5">
                            {SECTIONS.find((s) => s.key === activeTab)?.label} Settings
                        </h2>

                        {activeTab === 'general' && (
                            <div className="grid gap-4">
                                <Field label="School Name" required error={form.errors.school_name}>
                                    <input type="text" value={form.data.school_name ?? ''} onChange={(e) => form.setData('school_name', e.target.value)} className="input" />
                                </Field>
                                <Field label="School Motto" error={form.errors.school_motto}>
                                    <input type="text" value={form.data.school_motto ?? ''} onChange={(e) => form.setData('school_motto', e.target.value)} className="input" />
                                </Field>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field label="School Email" error={form.errors.school_email}>
                                        <input type="email" value={form.data.school_email ?? ''} onChange={(e) => form.setData('school_email', e.target.value)} className="input" />
                                    </Field>
                                    <Field label="School Phone" error={form.errors.school_phone}>
                                        <input type="tel" value={form.data.school_phone ?? ''} onChange={(e) => form.setData('school_phone', e.target.value)} className="input" />
                                    </Field>
                                </div>
                                <Field label="School Address" error={form.errors.school_address}>
                                    <input type="text" value={form.data.school_address ?? ''} onChange={(e) => form.setData('school_address', e.target.value)} className="input" />
                                </Field>
                            </div>
                        )}

                        {activeTab === 'contact' && (
                            <div className="grid gap-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field label="Contact Email" error={form.errors.contact_email}>
                                        <input type="email" value={form.data.contact_email ?? ''} onChange={(e) => form.setData('contact_email', e.target.value)} className="input" />
                                    </Field>
                                    <Field label="Contact Phone" error={form.errors.contact_phone}>
                                        <input type="tel" value={form.data.contact_phone ?? ''} onChange={(e) => form.setData('contact_phone', e.target.value)} className="input" />
                                    </Field>
                                </div>
                                <Field label="WhatsApp" error={form.errors.whatsapp}>
                                    <input type="tel" value={form.data.whatsapp ?? ''} onChange={(e) => form.setData('whatsapp', e.target.value)} className="input" />
                                </Field>
                                <Field label="Address" error={form.errors.address}>
                                    <input type="text" value={form.data.address ?? ''} onChange={(e) => form.setData('address', e.target.value)} className="input" />
                                </Field>
                                <Field label="Google Maps URL" error={form.errors.google_maps_url}>
                                    <input type="url" value={form.data.google_maps_url ?? ''} onChange={(e) => form.setData('google_maps_url', e.target.value)} className="input" placeholder="https://maps.google.com/..." />
                                </Field>
                            </div>
                        )}

                        {activeTab === 'admissions' && (
                            <div className="grid gap-4">
                                <label className="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
                                    <input
                                        type="checkbox"
                                        checked={form.data.admissions_open === 'true' || form.data.admissions_open === true || form.data.admissions_open === '1'}
                                        onChange={(e) => form.setData('admissions_open', e.target.checked ? 'true' : 'false')}
                                        className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                                    />
                                    <div>
                                        <span className="block text-sm font-semibold text-ink">Admissions Open</span>
                                        <span className="text-xs text-slate-500">Check to show that admissions are currently open to the public.</span>
                                    </div>
                                </label>
                                <label className="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
                                    <input
                                        type="checkbox"
                                        checked={form.data.application_submitted_sms_enabled === 'true' || form.data.application_submitted_sms_enabled === true || form.data.application_submitted_sms_enabled === '1'}
                                        onChange={(e) => form.setData('application_submitted_sms_enabled', e.target.checked ? 'true' : 'false')}
                                        className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                                    />
                                    <div>
                                        <span className="block text-sm font-semibold text-ink">SMS on Application Submitted</span>
                                        <span className="text-xs text-slate-500">Send a Kiswahili confirmation SMS to the parent when a new online application is saved.</span>
                                    </div>
                                </label>
                                <label className="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
                                    <input
                                        type="checkbox"
                                        checked={form.data.application_accepted_sms_enabled === 'true' || form.data.application_accepted_sms_enabled === true || form.data.application_accepted_sms_enabled === '1'}
                                        onChange={(e) => form.setData('application_accepted_sms_enabled', e.target.checked ? 'true' : 'false')}
                                        className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                                    />
                                    <div>
                                        <span className="block text-sm font-semibold text-ink">SMS on Application Accepted</span>
                                        <span className="text-xs text-slate-500">Send a Kiswahili acceptance SMS to the parent when staff accepts an application.</span>
                                    </div>
                                </label>
                                <Field label="Application Message" error={form.errors.application_message}>
                                    <textarea value={form.data.application_message ?? ''} onChange={(e) => form.setData('application_message', e.target.value)} rows={3} className="input" />
                                </Field>
                                <Field label="Application Deadline" error={form.errors.application_deadline}>
                                    <input type="date" value={form.data.application_deadline ?? ''} onChange={(e) => form.setData('application_deadline', e.target.value)} className="input" />
                                </Field>
                            </div>
                        )}

                        {activeTab === 'branding' && (
                            <div className="grid gap-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field label="Primary Color" required error={form.errors.primary_color}>
                                        <div className="flex items-center gap-3">
                                            <input
                                                type="color"
                                                value={form.data.primary_color ?? '#01579B'}
                                                onChange={(e) => form.setData('primary_color', e.target.value)}
                                                className="h-10 w-14 cursor-pointer rounded-lg border border-slate-200 p-1"
                                            />
                                            <input
                                                type="text"
                                                value={form.data.primary_color ?? '#01579B'}
                                                onChange={(e) => form.setData('primary_color', e.target.value)}
                                                className="input flex-1"
                                                placeholder="#01579B"
                                            />
                                        </div>
                                    </Field>
                                    <Field label="Secondary Color" required error={form.errors.secondary_color}>
                                        <div className="flex items-center gap-3">
                                            <input
                                                type="color"
                                                value={form.data.secondary_color ?? '#1E88E5'}
                                                onChange={(e) => form.setData('secondary_color', e.target.value)}
                                                className="h-10 w-14 cursor-pointer rounded-lg border border-slate-200 p-1"
                                            />
                                            <input
                                                type="text"
                                                value={form.data.secondary_color ?? '#1E88E5'}
                                                onChange={(e) => form.setData('secondary_color', e.target.value)}
                                                className="input flex-1"
                                                placeholder="#1E88E5"
                                            />
                                        </div>
                                    </Field>
                                </div>
                                <Field label="Logo URL" error={form.errors.logo_url}>
                                    <input type="url" value={form.data.logo_url ?? ''} onChange={(e) => form.setData('logo_url', e.target.value)} className="input" placeholder="https://..." />
                                </Field>
                                <Field label="Favicon URL" error={form.errors.favicon_url}>
                                    <input type="url" value={form.data.favicon_url ?? ''} onChange={(e) => form.setData('favicon_url', e.target.value)} className="input" placeholder="https://..." />
                                </Field>
                            </div>
                        )}

                        <div className="mt-6 flex items-center justify-end">
                            <button type="submit" disabled={form.processing} className="btn-primary inline-flex items-center gap-2">
                                <Save className="h-4 w-4" />
                                {form.processing ? 'Saving…' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </DashboardLayout>
    );
}

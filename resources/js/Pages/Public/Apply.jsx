import { useState } from 'react';
import { motion } from 'framer-motion';
import { useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, CheckCircle2, Send } from 'lucide-react';
import PublicLayout from '../../Layouts/PublicLayout';

/* ===========================================================================
 * Multi-step online application (Admissions 2026).
 * Client-side wizard → ONE validated POST to /apply.
 * ======================================================================== */

const STEPS = [
    { id: 1, label: 'Parent / Guardian' },
    { id: 2, label: 'Child Details' },
    { id: 3, label: 'Review & Submit' },
];

const RELATIONSHIPS = [
    { value: 'father', label: 'Father' },
    { value: 'mother', label: 'Mother' },
    { value: 'guardian', label: 'Guardian' },
    { value: 'other', label: 'Other' },
];

function Field({ label, error, children, required = true }) {
    return (
        <label className="block">
            <span className="label">
                {label} {required && <span className="text-primary">*</span>}
            </span>
            {children}
            {error && <span className="mt-1 block text-xs font-semibold text-rose-600">{error}</span>}
        </label>
    );
}

export default function Apply({ classes }) {
    const { school, flash } = usePage().props;
    const [step, setStep] = useState(1);

    const { data, setData, post, processing, errors } = useForm({
        parent_name: '',
        parent_email: '',
        parent_phone: '',
        relationship: 'mother',
        address: '',
        child_first_name: '',
        child_last_name: '',
        gender: 'female',
        dob: '',
        class_id: classes?.[0]?.id ?? '',
        message: '',
    });

    const set = (key) => (event) => setData(key, event.target.value);

    const stepOneValid = data.parent_name && data.parent_phone;
    const stepTwoValid = data.child_first_name && data.child_last_name && data.class_id;

    const submit = (event) => {
        event.preventDefault();
        post('/apply');
    };

    /* ---- Success screen (after POST redirect with flash) ---------------- */
    if (flash?.success) {
        return (
            <PublicLayout>
                <div className="mx-auto max-w-xl px-4 py-20 text-center">
                    <motion.div
                        initial={{ scale: 0.8, opacity: 0 }}
                        animate={{ scale: 1, opacity: 1 }}
                        transition={{ type: 'spring', stiffness: 200, damping: 16 }}
                        className="glass-card p-8"
                    >
                        <span className="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                            <CheckCircle2 className="h-10 w-10" />
                        </span>
                        <h1 className="mt-5 font-display text-2xl font-extrabold text-ink">
                            Application Received!
                        </h1>
                        <p className="mt-3 text-sm leading-relaxed text-slate-600">{flash.success}</p>
                        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                            <a href="/" className="btn-primary">Back to Home</a>
                            <button
                                type="button"
                                onClick={() => window.location.reload()}
                                className="btn-ghost"
                            >
                                Submit Another Application
                            </button>
                        </div>
                    </motion.div>
                </div>
            </PublicLayout>
        );
    }

    return (
        <PublicLayout>
            {/* Page header */}
            <section className="bg-gradient-to-br from-primary via-primary-600 to-rose-900 py-12 text-center text-white sm:py-16">
                <div className="mx-auto max-w-3xl px-4">
                    <span className="badge bg-accent text-ink">
                        Admissions {school.admissions_year} Open
                    </span>
                    <h1 className="mt-4 font-display text-3xl font-extrabold sm:text-4xl">
                        Online Application Form
                    </h1>
                    <p className="mt-3 text-sm text-white/85 sm:text-base">
                        Complete the form in three short steps. We will contact you within 48 hours.
                    </p>
                </div>
            </section>

            {/* Wizard */}
            <section className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
                {/* Step indicator */}
                <ol className="mb-8 flex items-center gap-2 sm:gap-4">
                    {STEPS.map((s) => (
                        <li key={s.id} className="flex flex-1 items-center gap-2">
                            <span
                                className={`grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-extrabold ${
                                    step >= s.id
                                        ? 'bg-primary text-white shadow-lg shadow-primary/30'
                                        : 'bg-slate-200 text-slate-500'
                                }`}
                            >
                                {step > s.id ? <CheckCircle2 className="h-5 w-5" /> : s.id}
                            </span>
                            <span
                                className={`hidden text-xs font-bold sm:block ${
                                    step >= s.id ? 'text-ink' : 'text-slate-400'
                                }`}
                            >
                                {s.label}
                            </span>
                            {s.id < STEPS.length && (
                                <span
                                    className={`h-0.5 flex-1 rounded ${step > s.id ? 'bg-primary' : 'bg-slate-200'}`}
                                />
                            )}
                        </li>
                    ))}
                </ol>

                <form onSubmit={submit} className="glass-card p-5 sm:p-8">
                    <motion.div
                        key={step}
                        initial={{ opacity: 0, x: 24 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.25 }}
                        className="space-y-5"
                    >
                        {/* ---------------- STEP 1 ---------------- */}
                        {step === 1 && (
                            <>
                                <h2 className="font-display text-lg font-bold text-ink">
                                    Step 1 · Parent / Guardian Information
                                </h2>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field label="Full Name" error={errors.parent_name}>
                                        <input
                                            className="input"
                                            value={data.parent_name}
                                            onChange={set('parent_name')}
                                            placeholder="e.g. Mama Neema Juma"
                                        />
                                    </Field>
                                    <Field label="Phone Number" error={errors.parent_phone}>
                                        <input
                                            className="input"
                                            value={data.parent_phone}
                                            onChange={set('parent_phone')}
                                            placeholder="07xxxxxxxx"
                                            inputMode="tel"
                                        />
                                    </Field>
                                    <Field label="Email (optional)" error={errors.parent_email} required={false}>
                                        <input
                                            className="input"
                                            type="email"
                                            value={data.parent_email}
                                            onChange={set('parent_email')}
                                            placeholder="you@example.com"
                                        />
                                    </Field>
                                    <Field label="Relationship to Child" error={errors.relationship}>
                                        <select className="input" value={data.relationship} onChange={set('relationship')}>
                                            {RELATIONSHIPS.map((r) => (
                                                <option key={r.value} value={r.value}>{r.label}</option>
                                            ))}
                                        </select>
                                    </Field>
                                    <div className="sm:col-span-2">
                                        <Field label="Home Address" error={errors.address} required={false}>
                                            <input
                                                className="input"
                                                value={data.address}
                                                onChange={set('address')}
                                                placeholder="Street, Ward, Dodoma"
                                            />
                                        </Field>
                                    </div>
                                </div>
                            </>
                        )}

                        {/* ---------------- STEP 2 ---------------- */}
                        {step === 2 && (
                            <>
                                <h2 className="font-display text-lg font-bold text-ink">
                                    Step 2 · Child Details
                                </h2>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field label="Child First Name" error={errors.child_first_name}>
                                        <input
                                            className="input"
                                            value={data.child_first_name}
                                            onChange={set('child_first_name')}
                                            placeholder="e.g. Amina"
                                        />
                                    </Field>
                                    <Field label="Child Last Name" error={errors.child_last_name}>
                                        <input
                                            className="input"
                                            value={data.child_last_name}
                                            onChange={set('child_last_name')}
                                            placeholder="e.g. Juma"
                                        />
                                    </Field>
                                    <Field label="Gender" error={errors.gender}>
                                        <select className="input" value={data.gender} onChange={set('gender')}>
                                            <option value="female">Girl</option>
                                            <option value="male">Boy</option>
                                        </select>
                                    </Field>
                                    <Field label="Date of Birth" error={errors.dob} required={false}>
                                        <input className="input" type="date" value={data.dob} onChange={set('dob')} />
                                    </Field>
                                    <div className="sm:col-span-2">
                                        <Field label="Class Applying For" error={errors.class_id}>
                                            <select className="input" value={data.class_id} onChange={set('class_id')}>
                                                {classes?.map((cls) => (
                                                    <option key={cls.id} value={cls.id}>
                                                        {cls.name} ({cls.code})
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>
                                    </div>
                                </div>
                            </>
                        )}

                        {/* ---------------- STEP 3 ---------------- */}
                        {step === 3 && (
                            <>
                                <h2 className="font-display text-lg font-bold text-ink">
                                    Step 3 · Review &amp; Submit
                                </h2>

                                <dl className="grid gap-3 rounded-xl bg-canvas p-4 text-sm sm:grid-cols-2">
                                    {[
                                        ['Parent', data.parent_name],
                                        ['Phone', data.parent_phone],
                                        ['Email', data.parent_email || '—'],
                                        [
                                            'Relationship',
                                            RELATIONSHIPS.find((r) => r.value === data.relationship)?.label,
                                        ],
                                        ['Child', `${data.child_first_name} ${data.child_last_name}`],
                                        ['Gender', data.gender === 'female' ? 'Girl' : 'Boy'],
                                        ['Date of Birth', data.dob || '—'],
                                        [
                                            'Class',
                                            classes?.find((c) => String(c.id) === String(data.class_id))?.name,
                                        ],
                                    ].map(([term, description]) => (
                                        <div key={term}>
                                            <dt className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                                {term}
                                            </dt>
                                            <dd className="font-semibold text-ink">{description || '—'}</dd>
                                        </div>
                                    ))}
                                </dl>

                                <Field label="Additional Notes" error={errors.message} required={false}>
                                    <textarea
                                        className="input min-h-[110px]"
                                        value={data.message}
                                        onChange={set('message')}
                                        placeholder="Anything you would like us to know about your child…"
                                    />
                                </Field>
                            </>
                        )}
                    </motion.div>

                    {/* Wizard actions */}
                    <div className="mt-8 flex items-center justify-between gap-3 border-t border-slate-100 pt-5">
                        <button
                            type="button"
                            onClick={() => setStep((s) => Math.max(1, s - 1))}
                            className={`btn-ghost ${step === 1 ? 'invisible' : ''}`}
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back
                        </button>

                        {step < 3 ? (
                            <button
                                type="button"
                                onClick={() => setStep((s) => s + 1)}
                                disabled={step === 1 ? !stepOneValid : !stepTwoValid}
                                className="btn-primary"
                            >
                                Continue
                                <ArrowRight className="h-4 w-4" />
                            </button>
                        ) : (
                            <button type="submit" disabled={processing} className="btn-primary">
                                {processing ? 'Submitting…' : 'Submit Application'}
                                <Send className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                </form>
            </section>
        </PublicLayout>
    );
}

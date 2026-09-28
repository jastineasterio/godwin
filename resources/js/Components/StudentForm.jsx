import { Checkbox, Field, FormActions, FormSection, Select, TextInput, Textarea } from './form';

/* ===========================================================================
 * STUDENT FORM — shared by the Create and Edit screens.
 *
 * `student` is empty when registering a new child. When `withParent` is true
 * an optional parent block is rendered (create the account + link in one go).
 * ======================================================================== */

export default function StudentForm({
    form,
    errors,
    processing,
    submit,
    cancelHref,
    classes,
    statuses,
    student = null,
    withParent = false,
}) {
    const set = (key) => (e) => form.setData(key, e.target.type === 'checkbox' ? e.target.checked : e.target.value);
    const isEdit = !!student;

    return (
        <form onSubmit={submit}>
            {/* ---------------- Child details ---------------- */}
            <FormSection title="Child Details" description="Core identity and enrolment information.">
                {!isEdit && (
                    <Field label="Registration Number" hint="Leave as generated if unsure." error={errors.reg_no}>
                        <TextInput value={form.data.reg_no ?? ''} onChange={set('reg_no')} placeholder="GWD-2026-0001" />
                    </Field>
                )}

                <Field label="First Name" required error={errors.first_name}>
                    <TextInput value={form.data.first_name} onChange={set('first_name')} placeholder="Amina" />
                </Field>

                <Field label="Last Name" required error={errors.last_name}>
                    <TextInput value={form.data.last_name} onChange={set('last_name')} placeholder="Juma" />
                </Field>

                <Field label="Other Name" error={errors.other_name} required={false}>
                    <TextInput value={form.data.other_name ?? ''} onChange={set('other_name')} placeholder="Middle name (optional)" />
                </Field>

                <Field label="Gender" required error={errors.gender}>
                    <Select value={form.data.gender} onChange={set('gender')}>
                        <option value="female">Female</option>
                        <option value="male">Male</option>
                    </Select>
                </Field>

                <Field label="Date of Birth" error={errors.dob} required={false} hint="Used to place the child in the right class.">
                    <TextInput type="date" value={form.data.dob ?? ''} onChange={set('dob')} />
                </Field>

                <Field label="Class" required error={errors.class_id}>
                    <Select value={form.data.class_id} onChange={set('class_id')}>
                        <option value="">Select class…</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>{c.name} ({c.code})</option>
                        ))}
                    </Select>
                </Field>

                <Field label="Status" required error={errors.status}>
                    <Select value={form.data.status} onChange={set('status')}>
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </Select>
                </Field>

                <Field label="Admission Date" error={errors.admission_date} required={false}>
                    <TextInput type="date" value={form.data.admission_date ?? ''} onChange={set('admission_date')} />
                </Field>

                <Field label="Blood Group" error={errors.blood_group} required={false}>
                    <TextInput value={form.data.blood_group ?? ''} onChange={set('blood_group')} placeholder="e.g. O+" />
                </Field>

                <div className="sm:col-span-2">
                    <Field label="Previous School" error={errors.previous_school} required={false}>
                        <TextInput value={form.data.previous_school ?? ''} onChange={set('previous_school')} placeholder="Optional" />
                    </Field>
                </div>
            </FormSection>

            {/* ---------------- Health & notes ---------------- */}
            <FormSection title="Health & Notes" description="Shown to teachers and on the progress report." className="mt-5">
                <div className="sm:col-span-2">
                    <Field label="Medical Notes" error={errors.medical_notes} required={false}
                        hint="Allergies, conditions, medication — keep it specific.">
                        <Textarea rows={3} value={form.data.medical_notes ?? ''} onChange={set('medical_notes')}
                            placeholder="e.g. Peanut allergy — monitor meals" />
                    </Field>
                </div>

                <div className="sm:col-span-2">
                    <Field label="Internal Notes" error={errors.notes} required={false}>
                        <Textarea rows={2} value={form.data.notes ?? ''} onChange={set('notes')} placeholder="Only visible to staff" />
                    </Field>
                </div>
            </FormSection>

            {/* ---------------- Optional parent account ---------------- */}
            <FormSection
                title="Parent / Guardian (Optional)"
                description="Create the portal login and link it to this child immediately."
                className="mt-5"
            >
                <div className="sm:col-span-2">
                    <Checkbox
                        label="Link a parent account to this student"
                        description="They will receive access to the parent portal for this child."
                        checked={form.data.with_parent}
                        onChange={set('with_parent')}
                    />
                </div>

                {form.data.with_parent && (
                    <>
                        <Field label="Parent Full Name" required error={errors.parent_name}>
                            <TextInput value={form.data.parent_name} onChange={set('parent_name')} placeholder="Bwana Juma" />
                        </Field>

                        <Field label="Phone Number" required error={errors.parent_phone} hint="Used to re-use an existing parent account.">
                            <TextInput value={form.data.parent_phone} onChange={set('parent_phone')} placeholder="0751000000" inputMode="tel" />
                        </Field>

                        <Field label="Email" error={errors.parent_email} required={false}>
                            <TextInput type="email" value={form.data.parent_email} onChange={set('parent_email')} placeholder="optional" />
                        </Field>

                        <Field label="Relationship to Child" required error={errors.relationship_type}>
                            <Select value={form.data.relationship_type} onChange={set('relationship_type')}>
                                <option value="mother">Mother</option>
                                <option value="father">Father</option>
                                <option value="guardian">Guardian</option>
                                <option value="other">Other</option>
                            </Select>
                        </Field>
                    </>
                )}
            </FormSection>

            <FormActions>
                <a href={cancelHref} className="btn-ghost">Cancel</a>
                <button type="submit" disabled={processing} className="btn-primary">
                    {processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Register Student'}
                </button>
            </FormActions>
        </form>
    );
}

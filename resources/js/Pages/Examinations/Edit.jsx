import { ChevronLeft, CalendarDays, GraduationCap, BookOpen } from 'lucide-react';
import { Link, router, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Field, FormActions, FormSection, Select, TextInput, Textarea } from '../../Components/form';
import { PageHeader } from '../../Components/page';

export default function ExaminationsEdit({
    examination,
    academicYears,
    terms,
    classes,
    subjects,
    selectedClasses,
    selectedSubjects,
}) {
    const [classIds, setClassIds] = useState(selectedClasses);
    const [subjectsConfig, setSubjectsConfig] = useState(selectedSubjects);

    const form = useForm({
        name: examination.name,
        academic_year_id: examination.academic_year_id,
        term_id: examination.term_id,
        description: examination.description ?? '',
        class_ids: [],
        subjects: [],
    }, {
        errors: {},
    });

    const addSubject = () => {
        setSubjectsConfig([...subjectsConfig, { subject_id: '', max_score: '' }]);
    };

    const removeSubject = (index) => {
        setSubjectsConfig(subjectsConfig.filter((_, i) => i !== index));
    };

    const updateSubject = (index, field, value) => {
        const newConfig = [...subjectsConfig];
        newConfig[index][field] = value;
        setSubjectsConfig(newConfig);
    };

    const submit = (e) => {
        e.preventDefault();
        form.setData('class_ids', classIds);
        form.setData('subjects', subjectsConfig);
        form.put(`/examinations/${examination.id}`);
    };

    return (
        <DashboardLayout
            title="Edit Examination"
            subtitle="Modify examination details, classes, subjects and maximum marks"
        >
            <PageHeader
                title="Edit Examination"
                subtitle={examination.name}
                back="/examinations"
            />

            <form onSubmit={submit}>
                <FormSection title="Examination Details" description="Basic information about the examination.">
                    <Field label="Examination Name" required error={form.errors.name} className="sm:col-span-2">
                        <TextInput
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Field>

                    <Field label="Academic Year" required error={form.errors.academic_year_id}>
                        <Select
                            value={form.data.academic_year_id}
                            onChange={(e) => form.setData('academic_year_id', e.target.value)}
                        >
                            <option value="">Select academic year…</option>
                            {academicYears.map((y) => (
                                <option key={y.id} value={y.id}>{y.name}</option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Term" required error={form.errors.term_id}>
                        <Select
                            value={form.data.term_id}
                            onChange={(e) => form.setData('term_id', e.target.value)}
                        >
                            <option value="">Select term…</option>
                            {terms.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name} {t.is_current ? '(current)' : ''}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Description" required={false} className="sm:col-span-2">
                        <Textarea
                            rows={3}
                            value={form.data.description ?? ''}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                    </Field>
                </FormSection>

                <FormSection title="Participating Classes">
                    <div className="mb-4">
                        <label className="label block mb-1">Select Classes</label>
                        <div className="grid gap-2 sm:grid-cols-3 md:grid-cols-4">
                            {classes.map((c) => {
                                const isSelected = classIds.includes(c.id);
                                return (
                                    <button
                                        key={c.id}
                                        type="button"
                                        onClick={() => {
                                            if (isSelected) {
                                                setClassIds(classIds.filter((id) => id !== c.id));
                                            } else {
                                                setClassIds([...classIds, c.id]);
                                            }
                                        }}
                                        className={`flex flex-col items-center gap-1 p-3 rounded-lg border transition ${
                                            isSelected
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-slate-200 text-slate-600 hover:border-primary/40'
                                        }`}
                                    >
                                        <GraduationCap className="h-5 w-5 mb-1" />
                                        <span className="text-sm font-semibold">{c.name}</span>
                                        <span className="text-[10px]">{c.code}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </FormSection>

                <FormSection title="Subjects & Maximum Marks">
                    <div className="space-y-3">
                        {subjectsConfig.map((config, index) => (
                            <div key={index} className="grid gap-3 sm:grid-cols-4 items-end border-b border-slate-100 pb-2">
                                <div className="sm:col-span-2">
                                    <Select
                                        value={config.subject_id}
                                        onChange={(e) => updateSubject(index, 'subject_id', e.target.value)}
                                        className="input"
                                    >
                                        <option value="">Select subject…</option>
                                        {subjects.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name} ({s.code})
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <div>
                                    <TextInput
                                        type="number"
                                        min="1"
                                        value={config.max_score}
                                        onChange={(e) => updateSubject(index, 'max_score', e.target.value)}
                                        placeholder="Max marks"
                                        className="input"
                                    />
                                </div>
                                <div className="flex justify-end">
                                    <button
                                        type="button"
                                        onClick={() => removeSubject(index)}
                                        className="text-rose-600 hover:text-rose-700 text-sm font-semibold"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={addSubject}
                            className="text-primary hover:text-primary/70 text-sm font-semibold flex items-center gap-1"
                        >
                            <BookOpen className="h-4 w-4" /> Add Subject
                        </button>
                    </div>
                </FormSection>

                <FormActions>
                    <Link href="/examinations" className="btn-ghost">
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="btn-primary"
                    >
                        {form.processing ? 'Updating…' : 'Update Examination'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}

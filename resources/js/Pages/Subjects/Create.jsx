import { useState } from 'react';
import { ChevronLeft, Plus } from 'lucide-react';
import { router, useForm } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import {
    Field,
    FormActions,
    FormSection,
    Select,
    TextInput,
    Textarea,
} from '../../Components/form';
import { Switch } from '../../Components/ui';
import { PageHeader } from '../../Components/page';

export default function SubjectsCreate({ classes }) {
    const [classAssignments, setClassAssignments] = useState([]);

    const form = useForm({
        name: '',
        code: '',
        description: '',
        is_active: true,
    });

    const addAssignment = () => {
        setClassAssignments([
            ...classAssignments,
            { class_id: '', teacher_id: '' },
        ]);
    };

    const removeAssignment = (index) => {
        setClassAssignments(classAssignments.filter((_, i) => i !== index));
    };

    const updateAssignment = (index, field, value) => {
        const next = [...classAssignments];
        next[index][field] = value;
        setClassAssignments(next);
    };

    const submit = (e) => {
        e.preventDefault();
        form.setData('class_ids', classAssignments.map((a) => a.class_id).filter(Boolean));
        form.setData(
            'teacher_ids',
            classAssignments
                .filter((a) => a.class_id && a.teacher_id)
                .map((a) => ({ class_id: a.class_id, teacher_id: a.teacher_id }))
        );
        form.post('/subjects');
    };

    return (
        <DashboardLayout
            title="Register Subject"
            subtitle="Enter subject details and assign it to one or more classes"
        >
            <PageHeader
                title="Register Subject"
                subtitle="Each subject can be taught in multiple classes with a responsible teacher"
                back="/subjects"
            />

            <form onSubmit={submit}>
                <FormSection title="Subject Details" description="Core subject information.">
                    <Field
                        label="Subject Name"
                        required
                        error={form.errors.name}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="e.g. Phonics, Numeracy, Bible Stories"
                        />
                    </Field>

                    <Field
                        label="Subject Code"
                        required={false}
                        error={form.errors.code}
                        className="sm:col-span-2"
                    >
                        <TextInput
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            placeholder="e.g. PHO-101"
                            hint="Unique identifier (optional)"
                        />
                    </Field>

                    <Field
                        label="Description"
                        required={false}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            rows={3}
                            value={form.data.description ?? ''}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="What is covered in this subject…"
                        />
                    </Field>

                    <Field label="Status" className="sm:col-span-2">
                        <Switch
                            checked={form.data.is_active}
                            onCheckedChange={(v) => form.setData('is_active', v)}
                        />
                        <span className="text-sm text-slate-600">
                            {form.data.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </Field>
                </FormSection>

                <FormSection
                    title="Class Assignments"
                    description="Select the classes where this subject is taught and the responsible teacher."
                >
                    <div className="mb-4">
                        <label className="label block mb-1">Assign Subject to Classes</label>
                        <div className="grid gap-3">
                            {classAssignments.length === 0 && (
                                <p className="text-xs text-slate-500">
                                    No class assigned yet. Add at least one class below.
                                </p>
                            )}
                            {classAssignments.map((assignment, index) => (
                                <div
                                    key={index}
                                    className="grid gap-3 sm:grid-cols-4 border-b border-slate-100 pb-3 items-end"
                                >
                                    <div className="sm:col-span-2">
                                        <Select
                                            value={assignment.class_id}
                                            onChange={(e) =>
                                                updateAssignment(index, 'class_id', e.target.value)
                                            }
                                            className="input"
                                        >
                                            <option value="">Select class…</option>
                                            {classes.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.name} ({c.code}) — {c.level}
                                                </option>
                                            ))}
                                        </Select>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <Select
                                            value={assignment.teacher_id}
                                            onChange={(e) =>
                                                updateAssignment(index, 'teacher_id', e.target.value)
                                            }
                                            className="input"
                                            placeholder="Teacher (optional)"
                                        >
                                            <option value="">No teacher assigned</option>
                                        </Select>
                                    </div>
                                    <div className="flex justify-end">
                                        <button
                                            type="button"
                                            onClick={() => removeAssignment(index)}
                                            className="text-rose-600 hover:text-rose-700 text-sm font-semibold"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={addAssignment}
                            className="text-primary hover:text-primary/70 text-sm font-semibold flex items-center gap-1 mt-2"
                        >
                            <Plus className="h-4 w-4" /> Add class assignment
                        </button>
                    </div>
                </FormSection>

                <FormActions>
                    <a href="/subjects" className="btn-ghost">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="btn-primary"
                    >
                        {form.processing ? 'Creating…' : 'Register Subject'}
                    </button>
                </FormActions>
            </form>
        </DashboardLayout>
    );
}

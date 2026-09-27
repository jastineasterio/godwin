import { useForm } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { PageHeader } from '../../Components/page';
import StudentForm from '../../Components/StudentForm';

/** Edit an existing student (registration number is immutable). */
export default function StudentEdit({ student, classes, statuses }) {
    const form = useForm({
        first_name: student.first_name ?? '',
        last_name: student.last_name ?? '',
        other_name: student.other_name ?? '',
        gender: student.gender ?? 'female',
        dob: student.dob ?? '',
        class_id: student.class_id ?? '',
        blood_group: student.blood_group ?? '',
        medical_notes: student.medical_notes ?? '',
        admission_date: student.admission_date ?? '',
        previous_school: student.previous_school ?? '',
        status: student.status ?? 'active',
        notes: student.notes ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.put(`/students/${student.id}`);
    };

    return (
        <DashboardLayout title="Student Management" subtitle="Update pupil record.">
            <PageHeader
                title={`Edit ${student.full_name}`}
                subtitle={`Registration number ${student.reg_no} (cannot be changed)`}
                back={`/students/${student.id}`}
            />

            <StudentForm
                form={form}
                errors={form.errors}
                processing={form.processing}
                submit={submit}
                cancelHref={`/students/${student.id}`}
                classes={classes}
                statuses={statuses}
                student={student}
            />
        </DashboardLayout>
    );
}
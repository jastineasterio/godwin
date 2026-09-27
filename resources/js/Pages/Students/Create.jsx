import { useForm } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { PageHeader } from '../../Components/page';
import StudentForm from '../../Components/StudentForm';

/** Register a new student (optionally creating their parent account). */
export default function StudentCreate({ classes, statuses, nextRegNo }) {
    const form = useForm({
        reg_no: nextRegNo ?? '',
        first_name: '',
        last_name: '',
        other_name: '',
        gender: 'female',
        dob: '',
        class_id: classes[0]?.id ?? '',
        blood_group: '',
        medical_notes: '',
        admission_date: new Date().toISOString().slice(0, 10),
        previous_school: '',
        status: 'active',
        notes: '',
        // optional inline parent
        with_parent: false,
        parent_name: '',
        parent_phone: '',
        parent_email: '',
        relationship_type: 'mother',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post('/students');
    };

    return (
        <DashboardLayout title="Student Management" subtitle="Register a new pupil into the school.">
            <PageHeader
                title="Register New Student"
                subtitle="All fields marked with * are required."
                back="/students"
            />

            <StudentForm
                form={form}
                errors={form.errors}
                processing={form.processing}
                submit={submit}
                cancelHref="/students"
                classes={classes}
                statuses={statuses}
                withParent
            />
        </DashboardLayout>
    );
}
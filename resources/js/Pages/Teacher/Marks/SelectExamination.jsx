import { Link, usePage } from '@inertiajs/react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import { PageHeader } from '../../../Components/page';
import { EmptyState } from '../../../Components/ui';
import { Users, ChevronLeft } from 'lucide-react';

export default function TeacherSelectExamination({ class: klass, subject, examinations }) {
    return (
        <DashboardLayout
            title="Record Marks"
            subtitle="Select the examination you want to enter marks for"
        >
            <PageHeader
                title="Step 3 — Select Examination"
                subtitle={`${klass.name} · ${subject.name}`}
                back="/teacher/marks/subjects"
            />

            <div className="glass-card p-4 sm:p-5">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-sm font-bold text-ink">{klass.name}</p>
                        <p className="text-xs text-slate-500 truncate">
                            Subject: {subject.name} ({subject.code})
                        </p>
                    </div>
                    <Link
                        href={`/teacher/marks/subjects?class_id=${klass.id}`}
                        className="text-xs font-bold text-primary hover:underline flex items-center gap-1"
                    >
                        <ChevronLeft className="h-3 w-3" /> Back to subjects
                    </Link>
                </div>

                {examinations.length === 0 ? (
                    <EmptyState
                        title="No published examinations"
                        hint={
                            'There are no published examinations available for this ' +
                            'class and subject yet. Ask the Head of School to publish one.'
                        }
                    />
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {examinations.map((exam) => (
                            <Link
                                key={exam.id}
                                href={
                                    `/teacher/examinations/${exam.id}/results` +
                                    `?class_id=${klass.id}&subject_id=${subject.id}`
                                }
                                className="block rounded-xl border border-slate-200 p-4 hover:bg-primary/5 hover:border-primary/30 transition"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="font-bold text-ink leading-tight">
                                            {exam.name}
                                        </p>
                                        <p className="text-xs text-slate-500 truncate">
                                            {exam.academic_year} · {exam.term}
                                        </p>
                                    </div>
                                    <Users className="h-4 w-4 text-slate-400 shrink-0" />
                                </div>
                                <p className="mt-2 text-xs font-bold text-primary">
                                    Record marks
                                </p>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}

import { Link, usePage } from '@inertiajs/react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import { PageHeader } from '../../../Components/page';
import { EmptyState } from '../../../Components/ui';
import { Users, ChevronLeft } from 'lucide-react';

export default function TeacherSelectSubject({ class: klass, subjects }) {
    return (
        <DashboardLayout
            title="Record Marks"
            subtitle="Select the subject you want to enter marks for"
        >
            <PageHeader
                title="Step 2 — Select Subject"
                subtitle={`${klass.name} (${klass.code})`}
                back="/teacher/marks"
            />

            <div className="glass-card p-4 sm:p-5">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <p className="text-sm font-bold text-ink">{klass.name}</p>
                        <p className="text-xs text-slate-500">
                            Class code: {klass.code}
                        </p>
                    </div>
                    <Link
                        href="/teacher/marks"
                        className="text-xs font-bold text-primary hover:underline flex items-center gap-1"
                    >
                        <ChevronLeft className="h-3 w-3" /> Back to classes
                    </Link>
                </div>

                {subjects.length === 0 ? (
                    <EmptyState
                        title="No subjects assigned"
                        hint={
                            "You are not assigned to teach any subject in this class. " +
                            'Contact the Head of School to be assigned.'
                        }
                    />
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {subjects.map((subject) => (
                            <Link
                                key={subject.id}
                                href={
                                    '/teacher/marks/examinations' +
                                    `?class_id=${klass.id}&subject_id=${subject.id}`
                                }
                                className="block rounded-xl border border-slate-200 p-4 hover:bg-primary/5 hover:border-primary/30 transition"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="font-bold text-ink leading-tight">
                                            {subject.name}
                                        </p>
                                        <p className="text-xs text-slate-500 truncate">
                                            Code: {subject.code}
                                        </p>
                                    </div>
                                    <Users className="h-4 w-4 text-slate-400 shrink-0" />
                                </div>
                                <p className="mt-2 text-xs font-bold text-primary">
                                    Continue to examinations
                                </p>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}

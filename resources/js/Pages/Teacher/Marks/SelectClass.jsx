import { Link, usePage } from '@inertiajs/react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import { PageHeader } from '../../../Components/page';
import { EmptyState } from '../../../Components/ui';
import { Users } from 'lucide-react';

export default function TeacherSelectClass({ classes }) {
    return (
        <DashboardLayout
            title="Record Marks"
            subtitle="Select the class you want to enter marks for"
        >
            <PageHeader
                title="Step 1 — Select Class"
                subtitle="Choose one of your assigned classes"
                back="/teacher/examinations"
            />

            {classes.length === 0 ? (
                <EmptyState
                    title="No classes assigned"
                    hint="You are not currently assigned to teach any class. Contact the Head of School to be assigned."
                />
            ) : (
                <div className="glass-card p-4 sm:p-5">
                    <div className="hidden overflow-x-auto sm:block">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Class</th>
                                    <th className="pb-3 font-bold text-right">Students</th>
                                    <th className="pb-3 font-bold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {classes.map((klass) => (
                                    <tr key={klass.id} className="transition hover:bg-canvas/60">
                                        <td className="py-3 font-bold text-ink">
                                            {klass.name} <span className="ml-2 text-xs text-slate-400">{klass.code}</span>
                                        </td>
                                        <td className="py-3 text-slate-600 text-right">
                                            {klass.students_count} students
                                        </td>
                                        <td className="py-3 text-right">
                                            <Link
                                                href={
                                                    '/teacher/marks/subjects' +
                                                    `?class_id=${klass.id}`
                                                }
                                                className="btn-primary text-xs px-3 py-1.5"
                                            >
                                                Select <Users className="h-3 w-3 ml-1" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <ul className="space-y-3 sm:hidden">
                        {classes.map((klass) => (
                            <li key={klass.id} className="rounded-xl bg-canvas p-4 flex items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-bold text-ink">{klass.name}</p>
                                    <p className="text-xs text-slate-500">
                                        {klass.code} · {klass.students_count} students
                                    </p>
                                </div>
                                <Link
                                    href={`/teacher/marks/subjects?class_id=${klass.id}`}
                                    className="btn-primary text-xs px-3 py-1.5"
                                >
                                    Select <Users className="h-3 w-3 ml-1" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </DashboardLayout>
    );
}

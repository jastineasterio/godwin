import { BookOpen, ChevronRight, Clock, Users } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState } from '../../Components/ui';
import { PageHeader } from '../../Components/page';

const STATUS_TONE = { draft: 'muted', published: 'secondary', closed: 'danger' };

export default function TeacherAssignedExaminations({ examinations }) {
    return (
        <DashboardLayout
            title="Assigned Examinations"
            subtitle="Examinations configured by the Head of School for your classes"
        >
            <PageHeader title="Assigned Examinations" subtitle="Select an examination to record results" />

            {examinations.length === 0 ? (
                <EmptyState
                    title="No assigned examinations"
                    hint="The Head of School has not published any examinations for your classes yet."
                />
            ) : (
                <div className="glass-card p-4 sm:p-5">
                    <div className="hidden overflow-x-auto sm:block">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Examination</th>
                                    <th className="pb-3 font-bold">Academic Year</th>
                                    <th className="pb-3 font-bold">Term</th>
                                    <th className="pb-3 font-bold">Status</th>
                                    <th className="pb-3 font-bold">Classes</th>
                                    <th className="pb-3 font-bold">Subjects (max marks)</th>
                                    <th className="pb-3 font-bold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {examinations.map((exam) => (
                                    <tr key={exam.id} className="transition hover:bg-canvas/60">
                                        <td className="py-3 font-bold text-ink">{exam.name}</td>
                                        <td className="py-3 text-slate-600">{exam.academic_year ?? '—'}</td>
                                        <td className="py-3 text-slate-600">{exam.term ?? '—'}</td>
                                        <td className="py-3">
                                            <Badge tone={STATUS_TONE[exam.status] ?? 'muted'}>
                                                {exam.status}
                                            </Badge>
                                        </td>
                                        <td className="py-3 text-slate-600">
                                            {exam.classes.map((c) => `${c.name} (${c.code})`).join(', ')}
                                        </td>
                                        <td className="py-3 text-slate-600">
                                            {exam.subjects
                                                .map((s) => `${s.name} (${s.max_score})`)
                                                .join(', ')}
                                        </td>
                                        <td className="py-3 text-right">
                                            {exam.status !== 'published' ? (
                                                <span className="text-xs text-slate-400">—</span>
                                            ) : (
                                                <Link
                                                    href={
                                                        `/teacher/examinations/${exam.id}/results` +
                                                        `?class_id=${exam.classes[0]?.id ?? ''}` +
                                                        `&subject_id=${exam.subjects[0]?.id ?? ''}`
                                                    }
                                                    className="btn-primary text-xs px-3 py-1.5"
                                                >
                                                    Record Results <ChevronRight className="h-3 w-3 ml-1" />
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <ul className="space-y-3 sm:hidden">
                        {examinations.map((exam) => (
                            <li key={exam.id} className="rounded-xl bg-canvas p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-bold text-ink">{exam.name}</p>
                                        <p className="text-xs text-slate-500">
                                            {exam.academic_year} · {exam.term} · {exam.status}
                                        </p>
                                        <p className="text-xs text-slate-500 truncate">
                                            {exam.classes.map((c) => c.name).join(', ')} ·
                                            {exam.subjects.map((s) => `${s.name} /${s.max_score}`).join(', ')}
                                        </p>
                                    </div>
                                    {exam.status === 'published' ? (
                                        <Link
                                            href={
                                                `/teacher/examinations/${exam.id}/results` +
                                                `?class_id=${exam.classes[0]?.id ?? ''}` +
                                                `&subject_id=${exam.subjects[0]?.id ?? ''}`
                                            }
                                            className="btn-primary text-xs px-3 py-1.5"
                                        >
                                            Record Results
                                        </Link>
                                    ) : (
                                        <span className="text-xs text-slate-400 px-3 py-1.5">—</span>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </DashboardLayout>
    );
}

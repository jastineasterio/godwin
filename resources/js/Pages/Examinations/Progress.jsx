import { ChevronLeft, ClipboardList, GraduationCap, Users } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, ProgressBar, StatCard } from '../../Components/ui';
import { PageHeader } from '../../Components/page';

export default function ExaminationsProgress({ examination, stats, classProgress, missingResults }) {
    return (
        <DashboardLayout
            title="Result Submission Progress"
            subtitle={`Review submission status for ${examination.name}`}
        >
            <PageHeader
                title="Result Submission Progress"
                subtitle={`Monitor teacher result entry for ${examination.name}`}
                back="/examinations"
            />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <StatCard
                    icon={GraduationCap}
                    label="Total Students"
                    value={stats.total_students}
                    accent="primary"
                />
                <StatCard
                    icon={ClipboardList}
                    label="Expected Results"
                    value={stats.total_expected}
                    accent="secondary"
                />
                <StatCard
                    icon={Users}
                    label="Submitted"
                    value={stats.submitted}
                    accent="secondary"
                />
                <StatCard
                    icon={GraduationCap}
                    label="Approved"
                    value={stats.approved}
                    accent="success"
                />
                <StatCard
                    icon={Users}
                    label="Draft"
                    value={stats.draft}
                    accent="accent"
                />
                <StatCard
                    icon={ClipboardList}
                    label="Completion"
                    value={`${stats.completion}%`}
                    accent="primary"
                />
            </div>

            <div className="mb-6">
                <Card title="Overall Completion">
                    <ProgressBar value={stats.completion} color="bg-primary" label="Result Submission Progress" />
                </Card>
            </div>

            <Card title="Class-by-Class Progress" subtitle="Submission status per participating class" className="mb-6">
                <div className="hidden overflow-x-auto sm:block">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                <th className="pb-3 font-bold">Class</th>
                                <th className="pb-3 font-bold">Students</th>
                                <th className="pb-3 font-bold">Expected</th>
                                <th className="pb-3 font-bold">Recorded</th>
                                <th className="pb-3 font-bold text-right">Completion</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {classProgress.map((c) => (
                                <tr key={c.id} className="transition hover:bg-canvas/60">
                                    <td className="py-3 font-bold text-ink">
                                        {c.name} <span className="text-[11px] text-slate-400">({c.code})</span>
                                    </td>
                                    <td className="py-3 text-slate-600">{c.students}</td>
                                    <td className="py-3 text-slate-600">{c.expected}</td>
                                    <td className="py-3 text-slate-600">{c.recorded}</td>
                                    <td className="py-3 text-right">
                                        <ProgressBar value={c.completion} color="bg-secondary" />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            {missingResults.length > 0 && (
                <Card title="Missing Results" subtitle="Students without recorded results for each subject" className="mb-6">
                    <div className="hidden overflow-x-auto sm:block">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Student</th>
                                    <th className="pb-3 font-bold">Admission No.</th>
                                    <th className="pb-3 font-bold">Class</th>
                                    <th className="pb-3 font-bold">Subject</th>
                                    <th className="pb-3 font-bold text-right">Max Marks</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {missingResults.map((r) => (
                                    <tr key={`${r.student_id}-${r.subject_name}`} className="transition hover:bg-canvas/60">
                                        <td className="py-3 font-bold text-ink">{r.student_name}</td>
                                        <td className="py-3 text-slate-600">{r.reg_no}</td>
                                        <td className="py-3 text-slate-600">{r.class_name}</td>
                                        <td className="py-3 text-slate-600">{r.subject_name}</td>
                                        <td className="py-3 text-right text-slate-600">{r.max_score}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            <div className="mt-6 flex gap-2">
                <Link href="/examinations" className="btn-ghost px-4 py-2">
                    ← Back to Examinations
                </Link>
            </div>
        </DashboardLayout>
    );
}

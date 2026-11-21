import { useState } from 'react';
import { BookOpen, Filter, GraduationCap, Info, Percent, Users } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, ProgressBar, StatCard } from '../../Components/ui';
import { BarChartCard, ChartCard, DonutChart, TrendChart } from '../../Components/charts';
import { BRAND } from '../../Components/ui';
import { PageHeader } from '../../Components/page';

export default function ResultsIndex({
    overview,
    studentPerformance,
    classAnalytics,
    subjectAnalytics,
    examinationAnalytics,
    filters,
    is_read_only = false,
    user_role = '',
    grade_distribution = [],
}) {
    const [search, setSearch] = useState('');

    const ROLE_LABELS = {
        teacher: 'Teacher',
        head_of_school: 'Head of School',
        senior_pastor: 'Senior Pastor',
    };
    const user_role_label = ROLE_LABELS[user_role] ?? user_role;

    const printParams = new URLSearchParams();
    ['academic_year_id', 'term_id', 'examination_id', 'class_id', 'subject_id'].forEach((key) => {
        const value = filters.selected[key];
        if (value) printParams.set(key, value);
    });
    const printHref = `/results-analytics/print${printParams.toString() ? '?' + printParams.toString() : ''}`;

    const filteredStudents = studentPerformance.filter((s) =>
        s.student_name.toLowerCase().includes(search.toLowerCase())
    );

    return (
        <DashboardLayout
            title="Results & Analytics"
            subtitle="Student performance, class analytics and subject comparisons"
        >
            <PageHeader
                title="Results & Analytics"
                subtitle="Track academic performance across your classes"
                action={
                    <a
                        href={printHref}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="btn-ghost"
                    >
                        Print Report
                    </a>
                }
            />

            {/* Read-only indicator for leadership roles */}
            {is_read_only && (
                <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div className="flex items-start gap-3">
                        <Info className="h-5 w-5 text-amber-700 shrink-0 mt-0.5" />
                        <div className="text-sm text-amber-800">
                            <p className="font-bold">Read-Only Access</p>
                            <p className="text-[12px] text-amber-700">
                                You are viewing school-wide results as {user_role_label}. Marks entry, submission,
                                approval and return are handled through the dedicated marks workflow — no edits can be
                                made from this page.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Filters */}
            <div className="mb-6 glass-card p-4 sm:p-5">
                <div className="flex items-center gap-2 mb-4">
                    <Filter className="h-4 w-4 text-slate-500" />
                    <span className="text-sm font-semibold text-ink">Filters</span>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-bold uppercase tracking-wider text-slate-400">
                            Academic Year
                        </label>
                        <select
                            className="input"
                            value={filters.selected.academic_year_id ?? ''}
                            onChange={(e) =>
                                router.get('/results-analytics', { academic_year_id: e.target.value }, { replace: true })
                            }
                        >
                            <option value="">All years</option>
                            {filters.academic_years.map((y) => (
                                <option key={y.id} value={y.id}>{y.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-bold uppercase tracking-wider text-slate-400">
                            Term
                        </label>
                        <select
                            className="input"
                            value={filters.selected.term_id ?? ''}
                            onChange={(e) =>
                                router.get('/results-analytics', { term_id: e.target.value }, { replace: true })
                            }
                        >
                            <option value="">All terms</option>
                            {filters.terms.map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-bold uppercase tracking-wider text-slate-400">
                            Examination
                        </label>
                        <select
                            className="input"
                            value={filters.selected.examination_id ?? ''}
                            onChange={(e) =>
                                router.get('/results-analytics', { examination_id: e.target.value }, { replace: true })
                            }
                        >
                            <option value="">All examinations</option>
                            {filters.examinations.map((e) => (
                                <option key={e.id} value={e.id}>{e.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-bold uppercase tracking-wider text-slate-400">
                            Class
                        </label>
                        <select
                            className="input"
                            value={filters.selected.class_id ?? ''}
                            onChange={(e) =>
                                router.get('/results-analytics', { class_id: e.target.value }, { replace: true })
                            }
                        >
                            <option value="">All classes</option>
                            {filters.classes.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs font-bold uppercase tracking-wider text-slate-400">
                            Subject
                        </label>
                        <select
                            className="input"
                            value={filters.selected.subject_id ?? ''}
                            onChange={(e) =>
                                router.get('/results-analytics', { subject_id: e.target.value }, { replace: true })
                            }
                        >
                            <option value="">All subjects</option>
                            {filters.subjects.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {/* Overview KPIs */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard
                    icon={BookOpen}
                    label="Total Examinations"
                    value={overview.total_examinations}
                    accent="primary"
                />
                <StatCard
                    icon={Users}
                    label="Results Recorded"
                    value={overview.results_recorded}
                    accent="secondary"
                />
                <StatCard
                    icon={Percent}
                    label="Pending Submissions"
                    value={overview.pending_submissions}
                    accent="accent"
                />
                <StatCard
                    icon={GraduationCap}
                    label="Approved Results"
                    value={overview.approved_results}
                    accent="primary"
                />
                <StatCard
                    icon={Percent}
                    label="Submitted Awaiting Approval"
                    value={overview.submitted_awaiting_approval ?? 0}
                    accent="accent"
                />
                <StatCard
                    icon={Percent}
                    label="Class Average"
                    value={`${overview.class_average}%`}
                    accent="secondary"
                />
            </div>

            {/* Charts row */}
            <div className="mb-6 grid gap-6 md:grid-cols-2">
                <ChartCard
                    title="Class Averages"
                    subtitle="Average percentage by class"
                    className="xl:col-span-2"
                >
                    <BarChartCard
                        data={classAnalytics.map((c) => ({
                            name: c.name,
                            average: c.average,
                        }))}
                        xKey="name"
                        yKey="average"
                        color={BRAND.primary}
                        unit="%"
                    />
                </ChartCard>
                <ChartCard
                    title="Subject Performance"
                    subtitle="Average percentage by subject"
                    className="xl:col-span-2"
                >
                    <BarChartCard
                        data={subjectAnalytics.map((s) => ({
                            name: s.name,
                            average: s.average,
                        }))}
                        xKey="name"
                        yKey="average"
                        color={BRAND.secondary}
                        unit="%"
                    />
                </ChartCard>
            </div>

            {/* Examination performance trend (reuses existing examination analytics) */}
            <div className="mb-6">
                <ChartCard
                    title="Examination Performance Trend"
                    subtitle="Average percentage across examinations"
                >
                    <TrendChart
                        data={examinationAnalytics.map((e) => ({
                            examination: e.name,
                            average: e.average,
                        }))}
                        xKey="examination"
                        yKey="average"
                        color={BRAND.primary}
                        unit="%"
                    />
                </ChartCard>
            </div>

            {/* Grade distribution */}
            <div className="mb-6">
                <ChartCard title="Grade Distribution" subtitle="Distribution of grades across all recorded results">
                    <DonutChart
                        data={(grade_distribution ?? []).map((g) => ({
                            name: g.label,
                            value: g.count,
                        }))}
                    />
                </ChartCard>
            </div>

            {/* Class Analytics Table */}
            <Card title="Class Analytics" subtitle="Performance breakdown by class" className="mb-6">
                <div className="hidden overflow-x-auto sm:block">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                <th className="pb-3 font-bold">Class</th>
                                <th className="pb-3 font-bold">Average</th>
                                <th className="pb-3 font-bold">Highest</th>
                                <th className="pb-3 font-bold">Lowest</th>
                                <th className="pb-3 font-bold">Results</th>
                                <th className="pb-3 font-bold text-right">Progress</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {classAnalytics.map((c) => (
                                <tr key={c.id} className="transition hover:bg-canvas/60">
                                    <td className="py-3 font-bold text-ink">{c.name}</td>
                                    <td className="py-3 text-slate-600">{c.average}%</td>
                                    <td className="py-3 text-slate-600">{c.highest}%</td>
                                    <td className="py-3 text-slate-600">{c.lowest}%</td>
                                    <td className="py-3 text-slate-600">{c.results_recorded}</td>
                                    <td className="py-3 text-right">
                                        <ProgressBar value={c.average} color="bg-primary" />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            {/* Student Performance Table */}
            <Card title="Student Performance" subtitle="Individual results across examinations and subjects">
                <div className="mb-4">
                    <input
                        type="search"
                        placeholder="Search students by name or admission number…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="input pl-10 w-full max-w-md"
                    />
                </div>
                <div className="hidden overflow-x-auto sm:block">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                <th className="pb-3 font-bold">Student</th>
                                <th className="pb-3 font-bold">Subject</th>
                                <th className="pb-3 font-bold">Examination</th>
                                <th className="pb-3 font-bold text-right">Marks</th>
                                <th className="pb-3 font-bold text-right">Max</th>
                                <th className="pb-3 font-bold">Grade</th>
                                <th className="pb-3 font-bold text-right">%</th>
                                <th className="pb-3 font-bold">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {filteredStudents.length === 0 ? (
                                <tr>
                                    <td colSpan="8" className="py-8 text-center text-slate-500">
                                        No student results found.
                                    </td>
                                </tr>
                            ) : (
                                filteredStudents.map((s) => (
                                    <tr key={`${s.student_name}-${s.subject}-${s.examination}`} className="transition hover:bg-canvas/60">
                                        <td className="py-3 font-bold text-ink">
                                            {s.student_name}
                                            <br />
                                            <span className="text-[11px] text-slate-500">{s.reg_no}</span>
                                        </td>
                                        <td className="py-3 text-slate-600">{s.subject}</td>
                                        <td className="py-3 text-slate-600">{s.examination}</td>
                                        <td className="py-3 text-right font-bold text-ink">
                                            {s.marks_obtained}
                                        </td>
                                        <td className="py-3 text-right text-slate-600">{s.max_marks}</td>
                                        <td className="py-3">
                                            <Badge
                                                tone={
                                                    s.percentage >= 75
                                                        ? 'success'
                                                        : s.percentage >= 50
                                                        ? 'warning'
                                                        : 'danger'
                                                }
                                            >
                                                {s.grade}
                                            </Badge>
                                        </td>
                                        <td className="py-3 text-right">
                                            {s.percentage !== null ? `${s.percentage}%` : '—'}
                                        </td>
                                        <td className="py-3">
                                            <Badge tone={s.status === 'approved' ? 'success' : 'muted'}>
                                                {s.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </Card>
        </DashboardLayout>
    );
}

import { ClipboardList, GraduationCap, TrendingUp, Users, Award } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { EmptyState, StatCard } from '../../Components/ui';
import { BarChartCard, DonutChart } from '../../Components/charts';
import { PageHeader } from '../../Components/page';

const GRADE_COLOR = {
    'A (80–100)': '#059669',
    'B (70–79)': '#01579B',
    'C (60–69)': '#1E88E5',
    'D (50–59)': '#FBC02D',
    'F (0–49)': '#B91C1C',
};

export default function ExaminationsAnalytics({ examination, summary, byClass, bySubject, gradeDistribution, needsSupport, studentResults }) {
    return (
        <DashboardLayout
            title="Examination Analytics"
            subtitle={`Performance analysis for ${examination.name}`}
        >
            <PageHeader
                title="Examination Analytics"
                subtitle={`${examination.academic_year} · ${examination.term} · ${examination.status}`}
                back="/examinations"
            />

            {/* ---------- Summary ---------- */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard
                    index={0}
                    icon={GraduationCap}
                    label="Students"
                    value={summary.total_students}
                    accent="primary"
                />
                <StatCard
                    index={1}
                    icon={ClipboardList}
                    label="Results Recorded"
                    value={summary.results_recorded}
                    accent="secondary"
                />
                <StatCard
                    index={2}
                    icon={TrendingUp}
                    label="Overall Average"
                    value={`${summary.overall_average}%`}
                    accent={summary.overall_average >= 60 ? 'secondary' : 'accent'}
                />
                <StatCard
                    index={3}
                    icon={Award}
                    label="Pass Rate"
                    value={`${summary.pass_rate}%`}
                    accent={summary.pass_rate >= 60 ? 'secondary' : 'danger'}
                />
                <StatCard
                    index={4}
                    icon={Users}
                    label="Needs Support"
                    value={needsSupport.length}
                    accent={needsSupport.length ? 'danger' : 'secondary'}
                />
            </div>

            <div className="mt-4 flex gap-3">
                {summary.highest && (
                    <div className="glass-card p-4 sm:p-5 flex-1">
                        <p className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Highest Mark</p>
                        <p className="text-sm font-bold text-ink">{summary.highest.student}</p>
                        <p className="text-xs text-slate-500">{summary.highest.subject} · {summary.highest.percentage}%</p>
                    </div>
                )}
                {summary.lowest && (
                    <div className="glass-card p-4 sm:p-5 flex-1">
                        <p className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Lowest Mark</p>
                        <p className="text-sm font-bold text-ink">{summary.lowest.student}</p>
                        <p className="text-xs text-slate-500">{summary.lowest.subject} · {summary.lowest.percentage}%</p>
                    </div>
                )}
            </div>

            {/* ---------- Charts ---------- */}
            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <ChartCard
                    title="Performance by Class"
                    subtitle="Average percentage per class"
                >
                    <BarChartCard
                        data={byClass.map((c) => ({
                            class: c.name,
                            average: c.average,
                        }))}
                        xKey="class"
                        yKey="average"
                        color="#01579B"
                        unit="%"
                    />
                </ChartCard>

                <ChartCard
                    title="Performance by Subject"
                    subtitle="Average percentage per subject"
                >
                    <BarChartCard
                        data={bySubject.map((s) => ({
                            subject: s.name,
                            average: s.average,
                        }))}
                        xKey="subject"
                        yKey="average"
                        color="#1E88E5"
                        unit="%"
                    />
                </ChartCard>

                <ChartCard
                    title="Grade Distribution"
                    subtitle="Students per grade band"
                >
                    <DonutChart
                        data={gradeDistribution.map((g) => ({
                            name: g.label,
                            value: g.count,
                        }))}
                        colors={[GRADE_COLOR['A (80–100)'], GRADE_COLOR['B (70–79)'], GRADE_COLOR['C (60–69)'], GRADE_COLOR['D (50–59)'], GRADE_COLOR['F (0–49)']]}
                    />
                </ChartCard>

                <ChartCard
                    title="Class Comparison"
                    subtitle="Average, pass rate, highest and lowest per class"
                >
                    <div className="chart-box">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Class</th>
                                    <th className="pb-3 font-bold text-right">Average</th>
                                    <th className="pb-3 font-bold text-right">Pass Rate</th>
                                    <th className="pb-3 font-bold text-right">Highest</th>
                                    <th className="pb-3 font-bold text-right">Lowest</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {byClass.map((c) => (
                                    <tr key={c.id} className="transition hover:bg-canvas/60">
                                        <td className="py-3 font-bold text-ink">{c.name}</td>
                                        <td className="py-3 text-right text-slate-600">{c.average}%</td>
                                        <td className="py-3 text-right text-slate-600">{c.pass_rate}%</td>
                                        <td className="py-3 text-right text-slate-600">{c.highest ?? '—'}%</td>
                                        <td className="py-3 text-right text-slate-600">{c.lowest ?? '—'}%</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </ChartCard>
            </div>

            {/* ---------- By Subject table ---------- */}
            <div className="mt-6 glass-card p-5 sm:p-6">
                <h3 className="font-display text-base font-bold text-ink mb-3">Subject Performance</h3>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                <th className="pb-3 font-bold">Subject</th>
                                <th className="pb-3 font-bold text-right">Max Score</th>
                                <th className="pb-3 font-bold text-right">Average</th>
                                <th className="pb-3 font-bold text-right">Pass Rate</th>
                                <th className="pb-3 font-bold text-right">Students</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {bySubject.map((s) => (
                                <tr key={s.id} className="transition hover:bg-canvas/60">
                                    <td className="py-3 font-bold text-ink">{s.name}</td>
                                    <td className="py-3 text-right text-slate-600">{s.max_score}</td>
                                    <td className="py-3 text-right text-slate-600">{s.average}%</td>
                                    <td className="py-3 text-right text-slate-600">{s.pass_rate}%</td>
                                    <td className="py-3 text-right text-slate-600">{s.students}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* ---------- Students needing support ---------- */}
            {needsSupport.length > 0 && (
                <div className="mt-6 glass-card p-5 sm:p-6">
                    <h3 className="font-display text-base font-bold text-ink mb-3">
                        Students Requiring Additional Support (&lt; 50%)
                    </h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Student</th>
                                    <th className="pb-3 font-bold">Reg No.</th>
                                    <th className="pb-3 font-bold">Class</th>
                                    <th className="pb-3 font-bold">Subject</th>
                                    <th className="pb-3 font-bold text-right">Percentage</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {needsSupport.map((s) => (
                                    <tr key={`${s.student_id}-${s.subject}`} className="transition hover:bg-canvas/60">
                                        <td className="py-3 font-bold text-ink">{s.student_name}</td>
                                        <td className="py-3 text-slate-600">{s.reg_no}</td>
                                        <td className="py-3 text-slate-600">{s.class}</td>
                                        <td className="py-3 text-slate-600">{s.subject}</td>
                                        <td className="py-3 text-right font-bold text-rose-600">{s.percentage}%</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* ---------- All student results ---------- */}
            <div className="mt-6 glass-card p-5 sm:p-6">
                <h3 className="font-display text-base font-bold text-ink mb-3">All Student Results</h3>
                {studentResults.length === 0 ? (
                    <EmptyState title="No results recorded yet" hint="Results appear once teachers submit marks." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Student</th>
                                    <th className="pb-3 font-bold">Reg No.</th>
                                    <th className="pb-3 font-bold">Class</th>
                                    <th className="pb-3 font-bold text-right">Average</th>
                                    <th className="pb-3 font-bold">Subjects</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {studentResults.map((s) => (
                                    <tr key={s.student_id} className="transition hover:bg-canvas/60">
                                        <td className="py-3 font-bold text-ink">{s.student_name}</td>
                                        <td className="py-3 text-slate-600">{s.reg_no}</td>
                                        <td className="py-3 text-slate-600">{s.class}</td>
                                        <td className="py-3 text-right font-semibold">
                                            {s.average}%
                                        </td>
                                        <td className="py-3 text-slate-600">
                                            {s.subjects.map((sub) => (
                                                <span key={sub.subject} className="block text-xs">
                                                    {sub.subject}: {sub.percentage}%
                                                </span>
                                            ))}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}

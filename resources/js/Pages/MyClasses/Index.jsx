import { BookOpen, ChevronRight, Clock, Users } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, EmptyState } from '../../Components/ui';
import { PageHeader } from '../../Components/page';
import { BRAND } from '../../Components/ui';

const LEVEL_LABEL = {
    daycare: 'Daycare',
    nursery: 'Nursery',
    kg1: 'KG1',
    kg2: 'KG2',
    primary: 'Primary',
};

export default function MyClassesIndex({ classes }) {
    return (
        <DashboardLayout
            title="My Classes"
            subtitle="Classes assigned to you as class teacher"
        >
            <PageHeader title="My Classes" subtitle="View your assigned classes and rosters" />

            {classes.length === 0 ? (
                <EmptyState
                    title="No classes assigned"
                    hint="You have not been assigned as class teacher for any class yet."
                />
            ) : (
                <div className="glass-card p-4 sm:p-5">
                    <div className="hidden overflow-x-auto sm:block">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold">Class</th>
                                    <th className="pb-3 font-bold">Code</th>
                                    <th className="pb-3 font-bold">Level</th>
                                    <th className="pb-3 font-bold">Students</th>
                                    <th className="pb-3 font-bold">Subjects</th>
                                    <th className="pb-3 font-bold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {classes.map((klass) => (
                                    <tr
                                        key={klass.id}
                                        className="transition hover:bg-canvas/60 group"
                                    >
                                        <td className="py-3 font-bold text-ink">
                                            {klass.name}
                                        </td>
                                        <td className="py-3 text-slate-600">
                                            <Badge tone="secondary">{klass.code}</Badge>
                                        </td>
                                        <td className="py-3 capitalize text-slate-600">
                                            {LEVEL_LABEL[klass.level] ?? klass.level}
                                        </td>
                                        <td className="py-3 font-bold text-ink">
                                            {klass.students_count}
                                        </td>
                                        <td className="py-3 text-slate-600">
                                            {klass.subjects.length > 0 ? (
                                                <span className="text-xs">
                                                    {klass.subjects.map((s) => (
                                                        <span key={s.id} className="badge muted inline-block mr-1 mb-1">
                                                            {s.code}
                                                        </span>
                                                    ))}
                                                </span>
                                            ) : (
                                                <span className="text-xs text-slate-400">—</span>
                                            )}
                                        </td>
                                        <td className="py-3 text-right">
                                            <Link
                                                href={`/my-classes/${klass.id}`}
                                                className="btn-primary text-xs px-3 py-1.5"
                                            >
                                                View Students <ChevronRight className="h-3 w-3 ml-1" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <ul className="space-y-3 sm:hidden">
                        {classes.map((klass) => (
                            <li key={klass.id} className="rounded-xl bg-canvas p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-bold text-ink">{klass.name}</p>
                                        <p className="text-xs text-slate-500">
                                            {klass.code} · {LEVEL_LABEL[klass.level] ?? klass.level}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-600">
                                            {klass.students_count} students · {klass.subjects.length} subjects
                                        </p>
                                    </div>
                                    <Link
                                        href={`/my-classes/${klass.id}`}
                                        className="btn-primary text-xs px-3 py-1.5"
                                    >
                                        View Students
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </DashboardLayout>
    );
}

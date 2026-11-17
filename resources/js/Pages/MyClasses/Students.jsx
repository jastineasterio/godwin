import { ChevronLeft, Users } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge } from '../../Components/ui';
import { PageHeader } from '../../Components/page';

const GENDER_LABEL = { male: 'Male', female: 'Female' };

export default function MyClassesStudents({ students, classIds }) {
    return (
        <DashboardLayout
            title="My Students"
            subtitle="Students across your assigned classes"
        >
            <PageHeader
                title="My Students"
                subtitle="View students in your assigned classes"
                back="/my-classes"
            />

            <div className="glass-card p-4 sm:p-5">
                {students.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
                        <p className="text-sm font-bold text-slate-600">No students found</p>
                        <p className="max-w-xs text-xs text-slate-400">
                            There are no active students in your assigned classes.
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                        <th className="pb-3 font-bold">Admission No.</th>
                                        <th className="pb-3 font-bold">Student Name</th>
                                        <th className="pb-3 font-bold">Class</th>
                                        <th className="pb-3 font-bold">Gender</th>
                                        <th className="pb-3 font-bold">DOB</th>
                                        <th className="pb-3 font-bold text-right">Age</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {students.map((s) => (
                                        <tr key={s.id} className="transition hover:bg-canvas/60">
                                            <td className="py-3 font-bold text-ink">{s.reg_no}</td>
                                            <td className="py-3 text-slate-600">{s.name}</td>
                                            <td className="py-3 text-slate-600">
                                                {s.class} <span className="text-xs text-slate-400">({s.class_code})</span>
                                            </td>
                                            <td className="py-3 capitalize text-slate-600">
                                                {GENDER_LABEL[s.gender] ?? s.gender}
                                            </td>
                                            <td className="py-3 text-slate-600">{s.dob ?? '—'}</td>
                                            <td className="py-3 text-right text-slate-600">
                                                {s.age ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="space-y-3 sm:hidden">
                            {students.map((s) => (
                                <li key={s.id} className="rounded-xl bg-canvas p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="font-bold text-ink">{s.name}</p>
                                            <p className="text-xs text-slate-500">
                                                {s.reg_no} · {s.class} ({s.class_code})
                                            </p>
                                        </div>
                                        <span className="text-right">
                                            <span className="block text-xs font-bold text-ink">
                                                {s.age ?? '—'} yrs
                                            </span>
                                            <span className="block text-[10px] text-slate-400">
                                                {GENDER_LABEL[s.gender] ?? s.gender}
                                            </span>
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>
        </DashboardLayout>
    );
}

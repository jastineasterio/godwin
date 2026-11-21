import { ChevronLeft, Mail, Phone } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge } from '../../Components/ui';
import { PageHeader } from '../../Components/page';

export default function TeachersShow({ teacher }) {
    return (
        <DashboardLayout
            title="Teacher Profile"
            subtitle={`${teacher.name} — teaching assignments and classes`}
        >
            <PageHeader
                title="Teacher Profile"
                subtitle={`Account: ${teacher.email} · ${teacher.status}`}
                back="/teachers"
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="glass-card p-5 sm:p-6">
                    <h3 className="font-display text-base font-bold text-ink mb-1">
                        {teacher.name}
                    </h3>
                    <p className="text-xs text-slate-500 mb-3">
                        {teacher.email}
                    </p>
                    <div className="flex items-center gap-2">
                        <Phone className="h-4 w-4 text-slate-400" />
                        <span className="text-sm text-slate-600">{teacher.phone ?? 'No phone on file'}</span>
                    </div>
                    <div className="mt-3 flex items-center gap-2">
                        <Mail className="h-4 w-4 text-slate-400" />
                        <span className="text-sm text-slate-600 truncate max-w-xs">{teacher.email}</span>
                    </div>
                    <div className="mt-3">
                        <Badge tone={teacher.status === 'active' ? 'success' : 'muted'}>
                            {teacher.status}
                        </Badge>
                    </div>
                    <p className="mt-2 text-xs text-slate-400">
                        Registered {teacher.created_at}
                    </p>
                </div>

                <div className="glass-card p-5 sm:p-6 sm:col-span-2">
                    <h3 className="font-display text-base font-bold text-ink mb-3">Classes</h3>
                    {teacher.classes.length ? (
                        <ul className="space-y-2">
                            {teacher.classes.map((c) => (
                                <li key={c.id} className="flex items-center justify-between gap-2 rounded-xl bg-canvas p-3">
                                    <div>
                                        <p className="text-sm font-semibold text-ink">{c.name}</p>
                                        <p className="text-xs text-slate-500">{c.code}</p>
                                    </div>
                                    <Badge tone="secondary" className="text-[10px]">
                                        {c.is_class_teacher ? 'Class Teacher' : 'Subject Teacher'}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-xs text-slate-500 text-center py-4">
                            No classes assigned yet.
                        </p>
                    )}
                </div>

                <div className="glass-card p-5 sm:p-6 sm:col-span-2">
                    <h3 className="font-display text-base font-bold text-ink mb-3">Subjects</h3>
                    {teacher.subjects.length ? (
                        <ul className="space-y-2">
                            {teacher.subjects.map((s) => (
                                <li key={s.id} className="flex items-center justify-between gap-2 rounded-xl bg-canvas p-3">
                                    <p className="text-sm font-semibold text-ink truncate">
                                        {s.name}
                                    </p>
                                    <span className="text-xs font-mono text-slate-500">{s.code ?? '—'}</span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-xs text-slate-500 text-center py-4">
                            No subjects assigned yet.
                        </p>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}

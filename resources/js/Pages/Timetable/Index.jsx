import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, EmptyState } from '../../Components/ui';
import { ConfirmDialog } from '../../Components/modal';
import { PrimaryAction } from '../../Components/page';

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

export default function TimetableIndex({ timetables, classes, children, activeClassId, canManage, days }) {
    const [deleting, setDeleting] = useState(null);
    const [selectedChild, setSelectedChild] = useState(children?.[0]?.id ?? null);

    const applyFilters = (extra = {}) => {
        const params = { ...(activeClassId ? { class_id: activeClassId } : {}) };
        router.get('/timetable', { ...params, ...extra }, { preserveState: true, replace: true });
    };

    const handleChildChange = (childId) => {
        setSelectedChild(childId);
        router.get('/timetable', { class_id: children.find((c) => c.id == childId)?.class_id }, { preserveState: true, replace: true });
    };

    const handleClassChange = (e) => {
        const classId = e.target.value;
        applyFilters({ class_id: classId ? parseInt(classId) : '' });
    };

    const grouped = groupByDay(timetables);

    return (
        <DashboardLayout title="Academics" subtitle="Weekly class timetable.">
            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h1 className="font-display text-xl font-extrabold text-ink sm:text-2xl">Timetable</h1>
                    <p className="mt-1 text-sm text-slate-500">{timetables?.length ?? 0} entries</p>
                </div>
                {canManage && (
                    <Link href="/timetable/create" className="btn-primary">
                        <Plus className="h-4 w-4" />
                        Add Entry
                    </Link>
                )}
            </div>

            {children?.length > 0 && (
                <div className="glass-card mb-5 p-4 sm:p-5">
                    <h3 className="mb-3 text-sm font-bold text-ink">Select Child</h3>
                    <div className="flex flex-wrap gap-2">
                        {children.map((child) => (
                            <button
                                key={child.id}
                                type="button"
                                onClick={() => handleChildChange(child.id)}
                                className={`rounded-xl px-4 py-2 text-sm font-bold transition ${
                                    (selectedChild == child.id)
                                        ? 'bg-primary text-white'
                                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                }`}
                            >
                                {child.name}
                                <span className="ml-1.5 text-xs opacity-80">({child.class_name})</span>
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {classes?.length > 0 && !children?.length && (
                <div className="glass-card mb-5 p-4 sm:p-5">
                    <h3 className="mb-3 text-sm font-bold text-ink">Filter by Class</h3>
                    <select
                        className="input w-auto min-w-[200px]"
                        value={activeClassId ?? ''}
                        onChange={handleClassChange}
                    >
                        <option value="">All classes</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name} ({c.code})
                            </option>
                        ))}
                    </select>
                </div>
            )}

            <div className="glass-card p-4 sm:p-5">
                {timetables?.length === 0 ? (
                    <EmptyState
                        title="No timetable entries found"
                        hint="Add a timetable entry to get started."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <div className="grid grid-cols-5 min-w-[800px]">
                            {DAYS.map((day, idx) => (
                                <div key={day} className={`${idx > 0 ? 'border-l border-slate-100' : ''}`}>
                                    <div className="border-b border-slate-100 pb-3 text-center">
                                        <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                            {day.charAt(0).toUpperCase() + day.slice(1)}
                                        </span>
                                    </div>
                                    <div className="p-2 space-y-2 min-h-[200px]">
                                        {(grouped[day] || []).map((entry) => (
                                            <div
                                                key={entry.id}
                                                className="rounded-xl border border-slate-100 bg-white p-3 shadow-sm"
                                            >
                                                <p className="text-sm font-bold text-ink">{entry.subject_name}</p>
                                                <p className="mt-1 text-xs text-slate-500">{entry.class_name}</p>
                                                {entry.teacher_name && (
                                                    <p className="mt-0.5 text-xs text-slate-500">{entry.teacher_name}</p>
                                                )}
                                                <p className="mt-1 text-xs font-semibold text-primary">
                                                    {formatTime(entry.start_time)} – {formatTime(entry.end_time)}
                                                </p>
                                                {entry.room && (
                                                    <p className="mt-0.5 text-xs text-slate-400">{entry.room}</p>
                                                )}
                                                {canManage && (
                                                    <div className="mt-2 flex gap-1">
                                                        <Link
                                                            href={`/timetable/${entry.id}/edit`}
                                                            className="flex-1 rounded-lg bg-slate-50 px-2 py-1 text-center text-xs font-bold text-slate-700 hover:bg-slate-100"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            onClick={() => setDeleting(entry)}
                                                            className="flex-1 rounded-lg bg-rose-50 px-2 py-1 text-center text-xs font-bold text-rose-600 hover:bg-rose-100"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    router.delete(`/timetable/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Delete timetable entry?"
                message={`"${deleting?.subject_name ?? 'This entry'}" for ${deleting?.class_name ?? ''} will be removed.`}
                confirmLabel="Delete"
            />
        </DashboardLayout>
    );
}

function groupByDay(timetables) {
    const map = {};
    for (const entry of timetables ?? []) {
        if (!map[entry.day]) map[entry.day] = [];
        map[entry.day].push(entry);
    }
    return map;
}

function formatTime(time) {
    if (!time) return '';
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours, 10);
    const suffix = h >= 12 ? 'PM' : 'AM';
    const displayHour = h % 12 || 12;
    return `${displayHour}:${minutes} ${suffix}`;
}

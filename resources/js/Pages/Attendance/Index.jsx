import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Check, CheckCheck, Save, X } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Card, StatCard } from '../../Components/ui';
import { PageHeader } from '../../Components/page';

/* ===========================================================================
 * DAILY ATTENDANCE MARKING
 *
 * Tapping a status updates local state instantly; one "Save register" POST
 * persists everything. Re-saving is safe — the server upserts on
 * (student_id, date), so duplicates can never be created.
 * ======================================================================== */

const QUICK = [
    { value: 'present', label: 'Present', icon: Check, active: 'bg-emerald-500 text-white border-emerald-500' },
    { value: 'late', label: 'Late', icon: CheckCheck, active: 'bg-amber-400 text-ink border-amber-400' },
    { value: 'absent', label: 'Absent', icon: X, active: 'bg-rose-500 text-white border-rose-500' },
    { value: 'excused', label: 'Excused', icon: X, active: 'bg-slate-400 text-white border-slate-400' },
];

export default function AttendanceIndex({ date, classes, activeClassId, roster, summary }) {
    const [selectedDate, setSelectedDate] = useState(date);
    const [marks, setMarks] = useState(() => Object.fromEntries(roster.map((s) => [s.id, s.status])));
    const [saving, setSaving] = useState(false);

    // Re-seed local state whenever the server sends a new roster/date
    useEffect(() => {
        setMarks(Object.fromEntries(roster.map((s) => [s.id, s.status])));
        setSelectedDate(date);
    }, [date, roster]);

    const switchContext = (params) =>
        router.get('/attendance', { date: selectedDate, class_id: activeClassId, ...params }, { preserveState: true });

    const setAll = (status) => setMarks(Object.fromEntries(roster.map((s) => [s.id, status])));

    const counts = roster.reduce((acc, student) => {
        acc[marks[student.id]] = (acc[marks[student.id]] ?? 0) + 1;
        return acc;
    }, {});

    const save = async () => {
        setSaving(true);
        try {
            await router.post(
                '/attendance',
                {
                    date: selectedDate,
                    class_id: activeClassId,
                    marks: roster.map((s) => ({ student_id: s.id, status: marks[s.id] })),
                },
                { preserveScroll: true }
            );
        } finally {
            setSaving(false);
        }
    };

    return (
        <DashboardLayout title="Attendance" subtitle="Mark the daily register in seconds.">
            <PageHeader title="Take Attendance" subtitle={`${roster.length} student(s) in the selected class`} />

            {/* ---------------- Controls ---------------- */}
            <Card className="mb-5">
                <div className="grid gap-3 sm:grid-cols-3">
                    <div>
                        <span className="label">Date</span>
                        <input
                            type="date"
                            className="input"
                            value={selectedDate}
                            onChange={(e) => setSelectedDate(e.target.value)}
                        />
                    </div>
                    <div>
                        <span className="label">Class</span>
                        <select
                            className="input"
                            value={activeClassId ?? ''}
                            onChange={(e) => switchContext({ class_id: e.target.value, date: selectedDate })}
                        >
                            {classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div className="flex items-end gap-2">
                        <button type="button" onClick={() => switchContext({ date: selectedDate })} className="btn-ghost flex-1">
                            Load
                        </button>
                        <button
                            type="button"
                            onClick={() => switchContext({ date: new Date().toISOString().slice(0, 10) })}
                            className="btn-ghost flex-1"
                        >
                            Today
                        </button>
                    </div>
                </div>
            </Card>

            {/* ---------------- Live summary ---------------- */}
            <div className="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard index={0} label="On Register" value={roster.length} accent="secondary" />
                <StatCard index={1} label="Present" value={counts.present ?? 0} accent="primary" />
                <StatCard index={2} label="Late" value={counts.late ?? 0} accent="accent" />
                <StatCard index={3} label="Absent / Excused" value={(counts.absent ?? 0) + (counts.excused ?? 0)} accent="primary" />
            </div>

            {/* ---------------- Roster ---------------- */}
            <Card
                title="Register"
                subtitle="Tap a status for each child"
                action={
                    <button type="button" onClick={() => setAll('present')} className="btn-ghost px-3 py-1.5 text-xs">
                        Mark all present
                    </button>
                }
            >
                {roster.length === 0 ? (
                    <p className="py-8 text-center text-sm text-slate-500">No active students in this class.</p>
                ) : (
                    <ul className="space-y-2.5">
                        {roster.map((student) => (
                            <li
                                key={student.id}
                                className="flex flex-col gap-3 rounded-xl bg-canvas p-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-bold text-ink">{student.name}</p>
                                    <p className="font-mono text-xs text-slate-500">{student.reg_no}</p>
                                </div>

                                {/* Quick toggles — large tap targets on mobile */}
                                <div className="grid grid-cols-4 gap-1.5 sm:flex sm:shrink-0">
                                    {QUICK.map((option) => {
                                        const active = marks[student.id] === option.value;
                                        const Icon = option.icon;

                                        return (
                                            <button
                                                key={option.value}
                                                type="button"
                                                onClick={() =>
                                                    setMarks((prev) => ({ ...prev, [student.id]: option.value }))
                                                }
                                                aria-pressed={active}
                                                className={`flex items-center justify-center gap-1 rounded-xl border-2 border-slate-200 bg-white px-2.5 py-2.5 text-xs font-bold text-slate-500 transition sm:px-3 ${
                                                    active ? option.active : 'hover:border-slate-300'
                                                }`}
                                            >
                                                <Icon className="h-4 w-4" />
                                                <span className="hidden sm:inline">{option.label}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {/* Sticky save bar */}
                <div className="sticky bottom-0 -mx-5 mt-5 flex items-center justify-between gap-3 border-t border-slate-100 bg-white/95 px-5 py-3 backdrop-blur">
                    <p className="text-xs font-semibold text-slate-500">
                        {summary.marked > 0
                            ? `${summary.marked} already saved for this date`
                            : 'Not yet saved for this date'}
                    </p>
                    <motion.button
                        type="button"
                        onClick={save}
                        disabled={saving || roster.length === 0}
                        whileTap={{ scale: 0.97 }}
                        className="btn-primary"
                    >
                        <Save className="h-4 w-4" />
                        {saving ? 'Saving…' : 'Save Register'}
                    </motion.button>
                </div>
            </Card>
        </DashboardLayout>
    );
}
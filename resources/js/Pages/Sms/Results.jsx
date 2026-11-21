import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect, useMemo, useRef } from 'react';
import { Card, Input, Label, Badge, Button, Select, Textarea, Flash } from '@/Components/ui';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { GraduationCap, Phone, Filter, Send, Mail, Loader2, Search, ChevronRight } from 'lucide-react';

/** Mask a phone number, showing only the last 4 digits. */
const maskPhone = (phone) => {
    if (!phone) return '';
    const last = phone.slice(-4);
    return '****' + last;
};

/** Status label for SMS delivery. */
const smsStatusLabel = (status) => {
    switch (status) {
        case 'delivered': return 'Delivered';
        case 'sent': return 'Sent';
        case 'queued': return 'Queued';
        case 'failed': return 'Failed — Retry available';
        case 'submission_unknown': return 'Submission unknown — Verification required';
        default: return 'Pending';
    }
};

const smsStatusTone = (status) => {
    switch (status) {
        case 'delivered': return 'success';
        case 'sent': return 'primary';
        case 'queued': return 'warning';
        case 'failed': return 'danger';
        case 'submission_unknown': return 'warning';
        default: return 'secondary';
    }
};

export default function SmsResults({ examinations, default_template, templates, classes: classesProp = [] }) {
    const { flash } = usePage().props;

    const form = useForm({
        examination_id: '',
        template: default_template || '',
        student_ids: [],
        class_id: '',
    });

    const [selectedExam, setSelectedExam] = useState(null);
    const [selectedClass, setSelectedClass] = useState(null);
    const [students, setStudents] = useState([]);
    const [missingPhoneStudents, setMissingPhoneStudents] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [sending, setSending] = useState(false);
    const [retryingStudentId, setRetryingStudentId] = useState(null);
    const [search, setSearch] = useState('');
    const [smsStatusFilter, setSmsStatusFilter] = useState('');
    const [sendResult, setSendResult] = useState(null);

    const prevExamId = useRef(selectedExam ? selectedExam.id : null);

    const eligibleStudents = students.filter((s) => s.has_phone);

    // Class options come from all active classes (reference data), independent of results.
    const classOptions = useMemo(() => {
        return (classesProp || []).slice().sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    }, [classesProp]);

    const filteredStudents = useMemo(() => {
        const q = search.toLowerCase();
        return eligibleStudents.filter((s) => {
            const match = !q ||
                s.student_name.toLowerCase().includes(q) ||
                (s.admission_number || '').toLowerCase().includes(q) ||
                (s.parent_name || '').toLowerCase().includes(q);
            const statusMatch = !smsStatusFilter || s.sms_status === smsStatusFilter;
            return match && statusMatch;
        });
    }, [students, search, smsStatusFilter]);

    // Selected students and the unique guardian phones among them, so the UI
    // can distinguish selected students from unique SMS recipients (and show
    // duplicates when several children share one guardian phone number).
    const selectedStudents = useMemo(() => {
        const idSet = new Set(form.data.student_ids);
        return filteredStudents.filter((s) => idSet.has(s.student_id));
    }, [filteredStudents, form.data.student_ids]);

    const uniquePhoneCount = useMemo(() => {
        return new Set(selectedStudents.map((s) => s.parent_phone).filter(Boolean)).size;
    }, [selectedStudents]);

    const duplicateCount = selectedStudents.length - uniquePhoneCount;

    // Reset the class selection when the examination changes so previous
    // selections never leak into a new examination.
    useEffect(() => {
        if (selectedExam && prevExamId.current !== null && prevExamId.current !== selectedExam.id) {
            setSelectedClass(null);
        }
        prevExamId.current = selectedExam ? selectedExam.id : null;
    }, [selectedExam]);

    const fetchStudents = () => {
        if (!selectedExam) {
            setStudents([]);
            setMissingPhoneStudents([]);
            setError(null);
            form.setData('student_ids', []);
            form.setData('class_id', '');
            return;
        }

        setLoading(true);
        setError(null);

        const params = new URLSearchParams({ examination_id: String(selectedExam.id) });
        if (selectedClass) {
            params.set('class_id', String(selectedClass.id));
            form.setData('class_id', selectedClass.id);
        } else {
            form.setData('class_id', '');
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch(route('sms.results.students'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: params.toString(),
        })
            .then((r) => {
                if (!r.ok) {
                    throw new Error('Failed to load students.');
                }
                return r.json();
            })
            .then((data) => {
                setStudents(data.students || []);
                setMissingPhoneStudents(data.missing_phone_students || []);
                form.setData('examination_id', selectedExam.id);
                setLoading(false);
            })
            .catch((err) => {
                setError(err.message || 'Failed to load students.');
                setStudents([]);
                setMissingPhoneStudents([]);
                setLoading(false);
            });
    };

    // Re-fetch students whenever the examination or the selected class changes.
    useEffect(() => {
        fetchStudents();
    }, [selectedExam, selectedClass]);

    const validPhoneCount = eligibleStudents.length;
    const missingPhoneCount = missingPhoneStudents.length;
    const totalApproved = validPhoneCount + missingPhoneCount;
    const totalStudents = students.length;
    const sentCount = students.filter((s) => s.sms_status === 'delivered' || s.sms_status === 'sent').length;
    const failedCount = students.filter((s) => s.sms_status === 'failed').length;
    const deliveredCount = students.filter((s) => s.sms_status === 'delivered').length;
    const pendingCount = validPhoneCount - sentCount - failedCount;

    const toggleStudent = (id) => {
        const ids = form.data.student_ids.includes(id)
            ? form.data.student_ids.filter((x) => x !== id)
            : [...form.data.student_ids, id];
        form.setData('student_ids', ids);
    };

    const handleSelectAll = () => {
        form.setData('student_ids', filteredStudents.map((s) => s.student_id));
    };

    const handleSelectNone = () => {
        form.setData('student_ids', []);
    };

    const selectedCount = form.data.student_ids.length;
    const isAllSelected = selectedCount > 0 && selectedCount === filteredStudents.length;

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    const handlePreview = () => {
        if (selectedCount === 0) return;
        const ids = filteredStudents.filter((s) => form.data.student_ids.includes(s.student_id)).map((s) => s.student_id);
        router.post(route('sms.results.preview'), {
            examination_id: form.data.examination_id,
            class_id: selectedClass ? selectedClass.id : null,
            template: form.data.template,
            student_ids: ids,
        }, { preserveScroll: true });
    };

    const handleSend = async (e) => {
        e.preventDefault();
        setSending(true);
        setSendResult(null);

        const ids = filteredStudents.filter((s) => form.data.student_ids.includes(s.student_id)).map((s) => s.student_id);
        if (ids.length === 0) {
            setSending(false);
            return;
        }

        // A retry send is authorized only when at least one selected student
        // has a confirmed failed message. Failed messages are retried; queued,
        // sent, delivered and submission_unknown messages are blocked with a
        // per-student reason returned by the backend.
        const isResend = ids.some((id) => {
            const s = students.find((x) => x.student_id === id);
            return s && s.sms_status === 'failed';
        });

        const formData = new FormData();
        formData.append('examination_id', form.data.examination_id);
        formData.append('class_id', form.data.class_id || '');
        ids.forEach((id) => formData.append('student_ids[]', id));
        formData.append('template', form.data.template);
        if (isResend) {
            formData.append('is_resend', '1');
            formData.append('resend_reason', 'Retry failed results SMS.');
        }

        try {
            const r = await fetch(route('sms.results.send'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            const data = await r.json();

            if (!r.ok) {
                setSending(false);
                setSendResult({
                    type: 'failure',
                    message: data.error || 'SMS submission failed. No messages were accepted by the provider.',
                });
                return;
            }

            setSendResult(buildSendResult(data));

            refreshStudents();
        } catch (err) {
            setSending(false);
            setSendResult({
                type: 'failure',
                message: 'Unexpected error while sending results SMS.',
            });
        }
    };

    /**
     * Build a user-facing result message from the send response, reflecting
     * retries, duplicates prevented, and verification-required states.
     */
    const buildSendResult = (data) => {
        const sent = data.sent ?? 0;
        const failed = data.failed ?? 0;
        const skipped = data.skipped ?? 0;
        const duplicatesPrevented = data.duplicates_prevented ?? 0;
        const details = data.details ?? [];

        const verificationRequired = details.some((d) => d.verification_required);
        const retried = details.some((d) => d.retried);

        if (sent > 0 && failed === 0) {
            let message = `SMS request accepted. ${sent} recipient(s) queued. Delivery is pending.`;
            if (duplicatesPrevented > 0) {
                message += ` ${duplicatesPrevented} duplicate recipient(s) were not sent multiple messages.`;
            }
            return { type: 'success', message };
        }

        if (sent > 0 && failed > 0) {
            return {
                type: 'partial',
                message: `SMS partially accepted. ${sent} recipient(s) accepted, ${failed} failed.`,
            };
        }

        if (sent === 0 && failed > 0) {
            return {
                type: 'failure',
                message: data.error || 'SMS submission failed. No messages were accepted by the provider.',
            };
        }

        if (verificationRequired) {
            return {
                type: 'info',
                message: 'Some selected submissions could not be confirmed. Verify delivery with the provider before retrying.',
            };
        }

        if (retried) {
            return {
                type: 'info',
                message: 'Failed message(s) have been retried. Queued/sent/delivered recipients were not duplicated.',
            };
        }

        if (duplicatesPrevented > 0) {
            return {
                type: 'info',
                message: 'All selected recipients were already notified for this examination (duplicates prevented).',
            };
        }

        if (skipped > 0) {
            return { type: 'info', message: 'No messages were sent. All selected students were skipped.' };
        }

        return { type: 'info', message: 'No messages were sent.' };
    };

    /** Retry a single failed results SMS through the authorized workflow. */
    const handleRetry = async (student) => {
        setRetryingStudentId(student.student_id);
        setSendResult(null);

        const formData = new FormData();
        formData.append('examination_id', form.data.examination_id);
        formData.append('class_id', form.data.class_id || '');
        formData.append('student_ids[]', student.student_id);
        formData.append('template', form.data.template);
        formData.append('is_resend', '1');
        formData.append('resend_reason', 'Retry failed results SMS.');

        try {
            const r = await fetch(route('sms.results.send'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            const data = await r.json();

            if (!r.ok) {
                setRetryingStudentId(null);
                setSendResult({
                    type: 'failure',
                    message: data.error || 'Retry failed. No messages were accepted by the provider.',
                });
                return;
            }

            setSendResult(buildSendResult(data));
            refreshStudents();
        } catch (err) {
            setRetryingStudentId(null);
            setSendResult({
                type: 'failure',
                message: 'Unexpected error while retrying results SMS.',
            });
        }
    };

    const refreshStudents = () => {
        const refreshParams = new URLSearchParams({ examination_id: form.data.examination_id });
        if (selectedClass) {
            refreshParams.set('class_id', String(selectedClass.id));
        }
        fetch(route('sms.results.students'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: refreshParams.toString(),
        }).then((res) => res.json()).then((d) => {
            setStudents(d.students || []);
            setMissingPhoneStudents(d.missing_phone_students || []);
            form.setData('student_ids', []);
            setSending(false);
            setRetryingStudentId(null);
        });
    };

    if (!selectedExam) {
        return (
            <DashboardLayout title="Results SMS" subtitle="Send examination results notifications to parents/guardians.">
                {flash && <Flash />}
                <div className="grid gap-6">
                    <Card className="col-span-1 xl:col-span-4">
                        <header className="mb-6">
                            <h3 className="font-display text-base font-bold text-ink">Select Examination</h3>
                            <p className="text-xs text-slate-500">
                                Choose a published examination to view eligible students and send results SMS.
                            </p>
                        </header>

                        <div className="space-y-3">
                            <Label>Examination</Label>
                            <Select
                                value={selectedExam ? selectedExam.id : ''}
                                onChange={(e) => {
                                    const id = parseInt(e.target.value, 10);
                                    setSelectedExam(examinations.find((ex) => ex.id === id) || null);
                                }}
                            >
                                <option value="">Select an examination...</option>
                                {examinations?.map((ex) => (
                                    <option key={ex.id} value={ex.id}>
                                        {ex.name} — {ex.academic_year} / {ex.term}
                                    </option>
                                ))}
                            </Select>
                        </div>

                            <div className="mt-6 p-4 rounded bg-slate-50 border border-slate-100">
                                <p className="text-sm text-slate-600">
                                    Only <strong>published</strong> examinations with <strong>submitted or approved</strong> results are eligible.
                                    Draft and returned results are never sent.
                                </p>
                            </div>
                    </Card>

                    {examinations?.length === 0 && (
                        <Card className="col-span-full">
                            <div className="p-8 text-center text-slate-500">
                                <GraduationCap className="mx-auto h-10 w-10 text-slate-400 mb-3" />
                                <p className="text-sm font-semibold text-ink">No published examinations found.</p>
                                <p className="text-xs text-slate-500 mt-1">
                                    Publish an examination and approve results before sending results SMS.
                                </p>
                            </div>
                        </Card>
                    )}
                </div>
            </DashboardLayout>
        );
    }

    return (
        <DashboardLayout
            title="Results SMS"
            subtitle={`${selectedExam.name} — ${selectedExam.academic_year} / ${selectedExam.term}`}
        >
            {flash && <Flash />}
            {error && (
                <div className="mb-4 p-3 rounded bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                    {error}
                </div>
            )}
            {sendResult && (
                <div className="mb-4 p-3 rounded text-sm">
                    {sendResult.type === 'success' && (
                        <div className="bg-emerald-50 border border-emerald-200 text-emerald-800">{sendResult.message}</div>
                    )}
                    {sendResult.type === 'partial' && (
                        <div className="bg-amber-50 border border-amber-200 text-amber-800">{sendResult.message}</div>
                    )}
                    {(sendResult.type === 'failure' || sendResult.type === 'info') && (
                        <div className={sendResult.type === 'failure' ? 'bg-rose-50 border border-rose-200 text-rose-800' : 'bg-slate-50 border border-slate-200 text-slate-700'}>
                            {sendResult.message}
                        </div>
                    )}
                </div>
            )}
            {loading && (
                <div className="mb-4 flex items-center gap-2 text-sm text-slate-600">
                    <Loader2 className="w-4 h-4 animate-spin" />
                    Loading students...
                </div>
            )}

            {/* Examination selector + actions bar */}
            <div className="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <Label>Examination</Label>
                    <Select
                        value={selectedExam.id}
                        onChange={(e) => {
                            const id = parseInt(e.target.value, 10);
                            setSelectedExam(examinations.find((ex) => ex.id === id) || null);
                        }}
                    >
                        {examinations?.map((ex) => (
                            <option key={ex.id} value={ex.id}>
                                {ex.name} — {ex.academic_year} / {ex.term}
                            </option>
                        ))}
                    </Select>
                </div>

                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        onClick={() => {
                            const url = new URL(window.location.href);
                            url.searchParams.set('examination_id', selectedExam.id);
                            window.location.href = route('sms.results.history') + '?examination_id=' + selectedExam.id;
                        }}
                    >
                        <Mail className="w-4 h-4 mr-2" />
                        View SMS History
                    </Button>
                    {validPhoneCount > 0 && (
                        <Button onClick={handlePreview} disabled={selectedCount === 0}>
                            <Send className="w-4 h-4 mr-2" />
                            Preview SMS ({selectedCount} selected)
                        </Button>
                    )}
                </div>
            </div>

            {/* Summary cards */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6 mb-6">
                <Card className="p-4">
                    <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Students with Eligible Results</div>
                    <div className="text-2xl font-bold text-ink">{totalApproved}</div>
                </Card>
                <Card className="p-4">
                    <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Valid Phone Numbers</div>
                    <div className="text-2xl font-bold text-ink">{validPhoneCount}</div>
                </Card>
                <Card className="p-4">
                    <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Awaiting SMS</div>
                    <div className="text-2xl font-bold text-amber-600">{Math.max(0, pendingCount)}</div>
                </Card>
                <Card className="p-4">
                    <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Sent / Delivered</div>
                    <div className="text-2xl font-bold text-emerald-600">{sentCount}</div>
                </Card>
                <Card className="p-4">
                    <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Failed</div>
                    <div className="text-2xl font-bold text-rose-600">{failedCount}</div>
                </Card>
                <Card className="p-4">
                    <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Delivered</div>
                    <div className="text-2xl font-bold text-emerald-600">{deliveredCount}</div>
                </Card>
            </div>

            {/* Filters + Template */}
            <div className="grid gap-6 mb-6">
                <Card className="col-span-1 xl:col-span-4">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Filters</h3>
                    </header>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="col-span-2 sm:col-span-3">
                            <Label>Search (student, admission no., parent)</Label>
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search students..."
                                leftIcon={<Search className="w-4 h-4" />}
                            />
                        </div>
                        <div>
                            <Label>Class</Label>
                            <Select
                                value={selectedClass ? selectedClass.id : ''}
                                onChange={(e) => {
                                    const id = e.target.value ? parseInt(e.target.value, 10) : null;
                                    setSelectedClass(id ? classOptions.find((c) => c.id === id) || null : null);
                                }}
                                disabled={!selectedExam}
                            >
                                <option value="">All Classes</option>
                                {classOptions.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name} ({c.code})
                                    </option>
                                ))}
                            </Select>
                            {selectedExam && !selectedClass && classOptions.length > 0 && (
                                <p className="text-xs text-slate-500 mt-1">
                                    Filter results by class, or leave "All Classes" to view every class.
                                </p>
                            )}
                        </div>
                        <div>
                            <Label>SMS Status</Label>
                            <Select value={smsStatusFilter} onChange={(e) => setSmsStatusFilter(e.target.value)}>
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="queued">Queued</option>
                                <option value="sent">Sent</option>
                                <option value="delivered">Delivered</option>
                                <option value="failed">Failed</option>
                                <option value="submission_unknown">Submission unknown</option>
                            </Select>
                        </div>
                    </div>
                </Card>

                <Card className="col-span-1 xl:col-span-4">
                    <header className="mb-4 flex items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold text-ink">Template</h3>
                    </header>
                    <div className="space-y-3">
                        <Select
                            value={form.data.template ? 'custom' : ''}
                            onChange={(e) => {
                                const selected = templates?.find((t) => t.id === parseInt(e.target.value, 10));
                                if (selected) form.setData('template', selected.template);
                                e.target.value = '';
                            }}
                        >
                            <option value="">Select a template...</option>
                            {templates?.map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </Select>
                        <Textarea
                            value={form.data.template}
                            onChange={(e) => form.setData('template', e.target.value)}
                            rows={4}
                            placeholder="Dear {{parent_name}}, results for {{student_name}}..."
                        />
                        <p className="text-xs text-slate-500">
                            {`Placeholders: {{parent_name}}, {{student_name}}, {{admission_number}},
                            {{class_name}}, {{examination_name}}, {{subject_summary}}, {{total_marks}},
                            {{total_max_marks}}, {{average_percentage}}, {{overall_grade}}, {{school_name}}.`}
                        </p>
                    </div>
                </Card>
            </div>

            {/* Student results table */}
            <Card>
                <header className="mb-4 flex items-center justify-between gap-2">
                    <div>
                        <h3 className="font-display text-base font-bold text-ink">Student Results</h3>
                        <p className="text-xs text-slate-500">
                            {filteredStudents.length} of {validPhoneCount} eligible students with valid phone numbers.
                            {missingPhoneCount > 0 && ` ${missingPhoneCount} student(s) missing a valid phone.`}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {selectedCount > 0 && (
                            <Button variant="outline" size="sm" onClick={handleSelectNone}>
                                Clear selection
                            </Button>
                        )}
                        <Button size="sm" onClick={handleSelectAll} disabled={filteredStudents.length === 0}>
                            {isAllSelected ? 'Select None' : 'Select All visible'}
                        </Button>
                    </div>
                </header>

                {filteredStudents.length === 0 && (
                    <div className="p-8 text-center text-slate-500">
                        <Phone className="mx-auto h-8 w-8 text-slate-400 mb-3" />
                        {!totalApproved && (
                            <>
                                <p className="text-sm font-semibold text-ink">
                                    No submitted or approved results found{selectedClass ? ` for class "{selectedClass.name}"` : ''}
                                    for this examination.
                                </p>
                                <p className="text-xs text-slate-500 mt-1">
                                    Submit results as a teacher first, or select results with submitted or approved status.
                                </p>
                            </>
                        )}
                        {totalApproved > 0 && validPhoneCount === 0 && (
                            <>
                                <p className="text-sm font-semibold text-ink">
                                    No students with a valid guardian phone number.
                                </p>
                                <p className="text-xs text-slate-500 mt-1">
                                    {missingPhoneCount} student(s) have eligible results but are missing a valid
                                    guardian phone number and cannot be notified.
                                </p>
                            </>
                        )}
                        {totalApproved > 0 && validPhoneCount > 0 && (
                            <>
                                <p className="text-sm font-semibold text-ink">No matching students.</p>
                                <p className="text-xs text-slate-500 mt-1">
                                    Try adjusting the search or SMS status filter.
                                </p>
                            </>
                        )}
                    </div>
                )}

                {filteredStudents.length > 0 && (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                                    <th className="pb-3 font-bold w-10">
                                        <input
                                            type="checkbox"
                                            checked={isAllSelected}
                                            onChange={handleSelectAll}
                                            disabled={filteredStudents.length === 0}
                                        />
                                    </th>
                                    <th className="pb-3 font-bold">Student</th>
                                    <th className="pb-3 font-bold">Admission No.</th>
                                    <th className="pb-3 font-bold">Class</th>
                                    <th className="pb-3 font-bold">Average</th>
                                    <th className="pb-3 font-bold">Grade</th>
                                    <th className="pb-3 font-bold">Parent / Guardian</th>
                                    <th className="pb-3 font-bold">Phone</th>
                                    <th className="pb-3 font-bold">Result Status</th>
                                    <th className="pb-3 font-bold">SMS Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filteredStudents.map((s) => (
                                    <tr key={s.student_id} className="hover:bg-canvas group">
                                        <td className="py-3">
                                            <input
                                                type="checkbox"
                                                checked={form.data.student_ids.includes(s.student_id)}
                                                onChange={() => toggleStudent(s.student_id)}
                                            />
                                        </td>
                                        <td className="py-3">
                                            <div className="font-semibold text-ink">{s.student_name}</div>
                                            <div className="text-xs text-slate-500 font-mono">{s.admission_number}</div>
                                        </td>
                                        <td className="py-3 text-slate-600">{s.class_name || '—'}</td>
                                        <td className="py-3 text-slate-600">{s.class_name || '—'}</td>
                                        <td className="py-3 text-right text-slate-600 font-mono">
                                            {s.average_percentage}%
                                        </td>
                                        <td className="py-3">
                                            <Badge tone={s.is_complete ? 'success' : 'warning'}>
                                                {s.overall_grade}
                                                {!s.is_complete && ' (partial)'}
                                            </Badge>
                                        </td>
                                        <td className="py-3 text-slate-600">{s.parent_name || '—'}</td>
                                        <td className="py-3 font-mono text-slate-600">
                                            {s.has_phone ? maskPhone(s.parent_phone) : '—'}
                                        </td>
                                        <td className="py-3">
                                            <Badge tone={s.result_status === 'Approved' ? 'success' : 'warning'}>
                                                {s.result_status}
                                            </Badge>
                                        </td>
                                        <td className="py-3">
                                            <Badge tone={smsStatusTone(s.sms_status)}>
                                                {smsStatusLabel(s.sms_status)}
                                            </Badge>
                                            {s.sms_status === 'failed' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleRetry(s)}
                                                    disabled={retryingStudentId === s.student_id || sending}
                                                >
                                                    {retryingStudentId === s.student_id ? 'Retrying...' : 'Retry'}
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {/* Send action bar */}
            {validPhoneCount > 0 && selectedCount > 0 && (
                <Card className="mt-6 p-4 rounded bg-slate-50 border border-slate-100">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="font-semibold text-ink">
                                {selectedCount} student(s) selected for results SMS.
                            </p>
                            {duplicateCount > 0 && (
                                <p className="text-xs text-slate-500">
                                    {uniquePhoneCount} unique guardian phone number(s) among the selection —
                                    {duplicateCount} selected student(s) share a guardian number with another selected
                                    student. Each selected student still receives their own personalized results message.
                                </p>
                            )}
                            <p className="text-xs text-slate-500">
                                Each parent will receive a personalized message with their child's results.
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Button variant="outline" onClick={handlePreview} disabled={sending}>
                                <Mail className="w-4 h-4 mr-2" />
                                Preview Messages
                            </Button>
                            <Button onClick={handleSend} disabled={sending || !form.data.template.trim()}>
                                {sending ? (
                                    <>
                                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                        Sending...
                                    </>
                                ) : (
                                    <>
                                        <Send className="w-4 h-4 mr-2" />
                                        Send SMS ({selectedCount})
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>
                </Card>
            )}
        </DashboardLayout>
    );
}

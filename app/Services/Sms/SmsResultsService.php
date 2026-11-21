<?php

namespace App\Services\Sms;

use App\Models\Assessment;
use App\Models\Examination;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\Student;
use App\Models\User;
use App\Services\GradingScale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Results SMS workflow service.
 *
 * Implements the secure, idempotent workflow for notifying parents
 * when student examination results are published.
 *
 * Submitted and approved results are eligible for Results SMS. Draft
 * and returned results are excluded. Student-parent relationships are
 * strictly enforced via the existing `parents()` / `primaryParent()`
 * relationship, and duplicate notifications are prevented via idempotency keys.
 */
class SmsResultsService
{
    protected SmsMessageService $messageService;

    protected SmsTemplateService $templateService;

    protected SmsRecipientService $recipientService;

    public function __construct(
        SmsMessageService $messageService,
        SmsTemplateService $templateService,
        SmsRecipientService $recipientService
    ) {
        $this->messageService = $messageService;
        $this->templateService = $templateService;
        $this->recipientService = $recipientService;
    }

    /**
     * Format a stored mark (decimal:2 cast) without unnecessary trailing zeros,
     * e.g. 89.00 -> "89", 89.50 -> "89.5".
     */
    private function formatMarks(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }

    /**
     * Step 1: Get eligible examinations for results SMS.
     *
     * Only published examinations are eligible.
     */
    public function getEligibleExaminations(User $user): array
    {
        // Head of School and Admin can see all published examinations.
        $query = Examination::published()
            ->with(['academicYear', 'term', 'classes', 'subjects']);

        return $query->orderByDesc('created_at')->get()->map(fn ($exam) => [
            'id' => $exam->id,
            'name' => $exam->name,
            'academic_year' => $exam->academicYear?->name,
            'term' => $exam->term?->name,
            'status' => $exam->status,
            'class_count' => $exam->classes->count(),
            'subject_count' => $exam->subjects->count(),
            'created_by' => $exam->createdBy?->name,
        ])->toArray();
    }

    /**
     * Step 2: Get eligible students for a given examination.
     *
     * Only students with submitted or approved assessment results are included.
     * Draft and returned results are excluded.
     *
     * When $classId is provided, only students (and their assessments)
     * belonging to that class are returned.
     *
     * Per-student totals, averages and grades are computed from the
     * submitted/approved marks only, excluding missing/unrecorded marks.
     *
     * Each student's result_status reflects whether their recorded
     * assessments are all Submitted, all Approved, or Mixed.
     */
    public function getEligibleStudents(int $examinationId, ?int $classId = null): array
    {
        $examination = Examination::published()->findOrFail($examinationId);

        // Assessments linked to this examination with submitted or approved status.
        $assessmentsQuery = Assessment::forExamination($examinationId)
            ->examinationResults()
            ->whereIn('status', ['submitted', 'approved'])
            ->with(['student.class', 'subject']);

        if ($classId) {
            $assessmentsQuery->forClass($classId);
        }

        $assessments = $assessmentsQuery->get();

        $studentIds = $assessments->pluck('student_id')->unique()->toArray();

        $studentsQuery = Student::whereIn('id', $studentIds)
            ->active()
            ->with(['class', 'parents']);

        if ($classId) {
            $studentsQuery->inClass($classId);
        }

        $students = $studentsQuery->get();

        $totalSubjects = $examination->subjects->count();

        $result = [];

        foreach ($students as $student) {
            $studentAssessments = $assessments->where('student_id', $student->id);

            // Only count assessments that have a recorded score.
            $recorded = $studentAssessments->filter(fn ($a) => $a->score !== null && $a->max_score > 0);

            $totalMarks = $recorded->sum(fn ($a) => (float) $a->score);
            $totalMaxMarks = $recorded->sum(fn ($a) => (float) $a->max_score);
            $averagePercentage = $recorded->isEmpty()
                ? 0.0
                : round($recorded->avg(fn ($a) => $a->percentage), 1);
            $overallGrade = GradingScale::grade($averagePercentage);
            $isComplete = $totalSubjects > 0 && $recorded->count() >= $totalSubjects;

            $subjectSummary = $recorded->map(fn ($a) => sprintf(
                '%s: %s/%s',
                $a->subject?->name ?? 'Subject',
                $this->formatMarks((float) $a->score),
                $this->formatMarks((float) $a->max_score)
            ))->implode("\n");

            // Find the parent/guardian for this student.
            $parent = $student->primaryParent();
            $parentPhone = $parent ? $this->recipientService->normalize($parent->phone) : null;

            // Result status: Approved, Submitted, or Mixed.
            $statuses = $studentAssessments->pluck('status')->unique()->sort()->values()->all();
            if ($statuses === ['approved']) {
                $resultStatus = 'Approved';
            } elseif ($statuses === ['submitted']) {
                $resultStatus = 'Submitted';
            } else {
                $resultStatus = 'Mixed';
            }

            $result[] = [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'admission_number' => $student->reg_no,
                'reg_no' => $student->reg_no,
                'class_name' => $student->class?->name,
                'class_id' => $student->class_id,
                'result_status' => $resultStatus,
                'academic_year' => $examination->academicYear?->name,
                'term' => $examination->term?->name,
                'parent_id' => $parent?->id,
                'parent_name' => $parent?->name,
                'parent_phone' => $parentPhone,
                'raw_parent_phone' => $parent?->phone,
                'has_phone' => (bool) $parentPhone,
                'subject_count' => $recorded->count(),
                'total_marks' => $totalMarks,
                'total_max_marks' => $totalMaxMarks,
                'average_percentage' => $averagePercentage,
                'overall_grade' => $overallGrade,
                'is_complete' => $isComplete,
                'subject_summary' => $subjectSummary,
                'assessments' => $studentAssessments->map(fn ($a) => [
                    'subject' => $a->subject?->name,
                    'score' => $a->score,
                    'max_score' => $a->max_score,
                    'percentage' => $a->percentage,
                ])->values()->toArray(),
            ];
        }

        // Separate those with and without phone numbers.
        $withPhone = array_values(array_filter($result, fn ($s) => $s['has_phone']));
        $withoutPhone = array_values(array_filter($result, fn ($s) => ! $s['has_phone']));

        return [
            'examination' => [
                'id' => $examination->id,
                'name' => $examination->name,
                'academic_year' => $examination->academicYear?->name,
                'term' => $examination->term?->name,
            ],
            'total_students' => count($result),
            'with_phone' => count($withPhone),
            'without_phone' => count($withoutPhone),
            'students' => $withPhone,
            'missing_phone_students' => $withoutPhone,
            'valid_phone_numbers' => count($withPhone),
        ];
    }

    /**
     * Step 4: Generate preview data for results SMS.
     *
     * Renders the fully personalized message for each eligible student
     * using the template system, so the administrator can review before
     * sending. Credentials are never exposed.
     *
     * When $classId is given, only that class's eligible students are shown.
     * When $studentIds is given, the preview is limited to those students
     * (e.g. a pre-selected subset from the Results SMS page).
     */
    public function preview(
        int $examinationId,
        string $template,
        ?User $user = null,
        ?int $classId = null,
        array $studentIds = []
    ): array {
        $eligible = $this->getEligibleStudents($examinationId, $classId);

        $schoolName = config('school.name') ?? 'God-Win Daycare & Nursery School';
        $examName = $eligible['examination']['name'] ?? '';
        $termName = $eligible['examination']['term'] ?? '';

        $allStudents = array_map(fn ($s) => [
            'student_id' => $s['student_id'],
            'student_name' => $s['student_name'],
            'admission_number' => $s['admission_number'],
            'class_name' => $s['class_name'],
            'parent_name' => $s['parent_name'] ?? 'Parent/Guardian',
            'parent_phone' => $s['parent_phone'] ?? '',
            'has_phone' => $s['has_phone'],
            'total_marks' => $s['total_marks'],
            'total_max_marks' => $s['total_max_marks'],
            'average_percentage' => $s['average_percentage'],
            'overall_grade' => $s['overall_grade'],
            'subject_summary' => $s['subject_summary'],
            'message' => $this->templateService->render($template, [
                'parent_name' => $s['parent_name'] ?? 'Parent/Guardian',
                'student_name' => $s['student_name'],
                'admission_number' => $s['admission_number'],
                'class_name' => $s['class_name'] ?? '',
                'academic_year' => $s['academic_year'] ?? '',
                'term' => $s['term'] ?? '',
                'examination_name' => $examName,
                'exam_name' => $examName,
                'term_name' => $termName,
                'subject_summary' => $s['subject_summary'],
                'total_marks' => $s['total_marks'],
                'total_max_marks' => $s['total_max_marks'],
                'average_percentage' => $s['average_percentage'],
                'overall_grade' => $s['overall_grade'],
                'school_name' => $schoolName,
            ]),
        ], $eligible['students']);

        // When a specific selection was submitted, restrict the preview to it.
        if (! empty($studentIds)) {
            $idSet = array_flip(array_values($studentIds));
            $allStudents = array_values(array_filter($allStudents, fn ($s) => isset($idSet[$s['student_id']])));
        }

        $students = $allStudents;
        $missingPhoneStudents = $eligible['missing_phone_students'];
        if (! empty($studentIds)) {
            $idSet = array_flip(array_values($studentIds));
            $missingPhoneStudents = array_values(array_filter($missingPhoneStudents, fn ($s) => isset($idSet[$s['student_id']])));
        }

        // Check for duplicate recipients (same parent for multiple children).
        $phones = array_column($students, 'parent_phone');
        $duplicates = [];
        $phoneCounts = array_count_values($phones);
        foreach ($phoneCounts as $phone => $count) {
            if ($count > 1) {
                $duplicates[$phone] = $count;
            }
        }

        return [
            'examination' => $eligible['examination'],
            'class_id' => $classId,
            'template' => $template,
            'sample_message' => ! empty($students) ? $students[0]['message'] : null,
            'total_students' => count($students) + count($missingPhoneStudents),
            'total_parents' => count($students),
            'valid_phone_numbers' => count(array_filter($students, fn ($s) => $s['has_phone'])),
            'missing_phone_count' => count($missingPhoneStudents),
            'duplicate_recipients' => $duplicates,
            'students' => $students,
            'missing_phone_students' => $missingPhoneStudents,
        ];
    }

    /**
     * Step 6: Send results SMS notifications with idempotency protection.
     *
     * Each student-parent combination gets one personalized SMS. The template
     * is rendered per-student with their own subject summary, totals and grade.
     * Duplicate notifications for the same student/examination/recipient are
     * prevented via idempotency keys. Resends are recorded separately.
     *
     * Sending is delegated to the queue (SendSmsJob) so the web request is
     * not blocked while sending large batches.
     */
    public function sendResultsNotifications(
        int $examinationId,
        array $studentIds,
        User $sendingUser,
        ?string $template = null,
        ?int $templateId = null,
        ?int $classId = null,
        array $options = []
    ): array {
        $examination = Examination::published()->findOrFail($examinationId);
        $isResend = $options['is_resend'] ?? false;
        $resendReason = $options['resend_reason'] ?? null;
        $senderId = $options['sender_id'] ?? null;

        // Resolve the template to render per-student messages.
        $templateString = $template;
        if (! $templateString && $templateId) {
            $tmpl = SmsTemplate::find($templateId);
            $templateString = $tmpl?->template ?? null;
        }

        $schoolName = config('school.name') ?? 'God-Win Daycare & Nursery School';
        $examName = $examination->name;
        $termName = $examination->term?->name ?? '';

        $results = [
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'duplicates_prevented' => 0,
            'details' => [],
        ];

        foreach ($studentIds as $studentId) {
            $student = Student::with(['parents', 'class'])->find($studentId);

            if (! $student) {
                $results['failed']++;
                $results['details'][] = [
                    'student_id' => $studentId,
                    'error' => 'Student not found.',
                ];

                continue;
            }

            // A selected student must belong to the selected class. This blocks
            // manipulated student IDs from leaking one class's/examination's
            // results to another class's parents.
            if ($classId !== null && (int) $student->class_id !== (int) $classId) {
                $results['skipped']++;
                $results['details'][] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'reason' => 'Student does not belong to the selected class.',
                ];

                continue;
            }

            // Only submitted or approved results for this examination may be notified.
            // Draft, returned, or missing results are never sent.
            // This check runs regardless of is_resend: resending never bypasses eligibility.
            $hasEligibleResult = Assessment::forExamination($examination->id)
                ->examinationResults()
                ->where('student_id', $student->id)
                ->whereIn('status', ['submitted', 'approved'])
                ->exists();

            if (! $hasEligibleResult) {
                $results['skipped']++;
                $results['details'][] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'reason' => 'No submitted or approved result for this examination.',
                ];

                continue;
            }

            $parent = $student->primaryParent();

            if (! $parent) {
                $results['skipped']++;
                $results['details'][] = [
                    'student_id' => $studentId,
                    'student_name' => $student->full_name,
                    'reason' => 'No parent/guardian linked.',
                ];

                continue;
            }

            $phone = $this->recipientService->normalize($parent->phone);

            if (! $phone) {
                $results['skipped']++;
                $results['details'][] = [
                    'student_id' => $studentId,
                    'student_name' => $student->full_name,
                    'parent_name' => $parent->name,
                    'reason' => 'No valid phone number.',
                ];

                continue;
            }

            // Render the personalized message for this student.
            $recorded = Assessment::forExamination($examination->id)
                ->examinationResults()
                ->where('student_id', $student->id)
                ->whereIn('status', ['submitted', 'approved'])
                ->with('subject')
                ->get()
                ->filter(fn ($a) => $a->score !== null && $a->max_score > 0);

            $subjectSummary = $recorded->map(fn ($a) => sprintf(
                '%s: %s/%s',
                $a->subject?->name ?? 'Subject',
                $this->formatMarks((float) $a->score),
                $this->formatMarks((float) $a->max_score)
            ))->implode("\n");

            $totalMarks = $recorded->sum(fn ($a) => (float) $a->score);
            $totalMaxMarks = $recorded->sum(fn ($a) => (float) $a->max_score);
            $averagePercentage = $recorded->isEmpty()
                ? 0.0
                : round($recorded->avg(fn ($a) => $a->percentage), 1);
            $overallGrade = GradingScale::grade($averagePercentage);

            $message = $templateString
                ? $this->templateService->render($templateString, [
                    'parent_name' => $parent->name,
                    'student_name' => $student->full_name,
                    'admission_number' => $student->reg_no,
                    'class_name' => $student->class?->name ?? '',
                    'academic_year' => $examination->academicYear?->name ?? '',
                    'term' => $termName,
                    'examination_name' => $examName,
                    'exam_name' => $examName,
                    'term_name' => $termName,
                    'subject_summary' => $subjectSummary,
                    'total_marks' => $totalMarks,
                    'total_max_marks' => $totalMaxMarks,
                    'average_percentage' => $averagePercentage,
                    'overall_grade' => $overallGrade,
                    'school_name' => $schoolName,
                ])
                : $options['message'] ?? '';

            // Idempotency key based on student + parent + examination + message hash.
            $idempotencyKey = SmsMessageService::resultsIdempotencyKey(
                $student->id,
                $parent->id,
                $examination->id,
                $message
            );

            $existing = SmsMessage::where('idempotency_key', $idempotencyKey)
                ->where('category', 'results')
                ->first();

            if ($existing) {
                if (! $isResend) {
                    // Ordinary send: block any existing record, regardless of status.
                    // This preserves duplicate protection for queued/sent/delivered/failed/unknown.
                    $results['duplicates_prevented']++;
                    $results['details'][] = $this->resultsDuplicateDetail(
                        $student, $parent, $phone, $existing, 'Already notified for this examination.'
                    );

                    continue;
                }

                // Authorized retry: status-aware handling preserves duplicate protection.
                switch ($existing->status) {
                    case 'queued':
                    case 'sent':
                    case 'delivered':
                        $results['duplicates_prevented']++;
                        $results['details'][] = $this->resultsDuplicateDetail(
                            $student, $parent, $phone, $existing, 'Already notified for this examination.'
                        );

                        continue 2;

                    case 'submission_unknown':
                        // Never automatically retry an unknown submission; reconciliation is required first.
                        $results['duplicates_prevented']++;
                        $results['details'][] = array_merge(
                            $this->resultsDuplicateDetail(
                                $student, $parent, $phone, $existing,
                                'Submission status unknown — verify delivery with the provider before retrying.'
                            ),
                            ['verification_required' => true]
                        );

                        continue 2;

                    case 'failed':
                        $retry = $this->retryFailedResultsSms($existing, $sendingUser, $resendReason);

                        if (($retry['skipped'] ?? false)) {
                            $results['skipped']++;
                            $results['details'][] = array_merge(
                                $this->resultsDuplicateDetail(
                                    $student, $parent, $phone, $existing, $retry['reason'] ?? 'Retry blocked.'
                                ),
                                ['existing_status' => $existing->status]
                            );

                            continue 2;
                        }

                        $isIdempotent = ($retry['idempotent'] ?? false);
                        $isRateLimited = ($retry['rate_limited'] ?? false);

                        if (($retry['success'] ?? false) && ! $isIdempotent && ! $isRateLimited) {
                            $results['sent']++;
                            $results['details'][] = array_merge(
                                $this->resultsSendDetail($student, $parent, $phone, $retry),
                                ['retried' => true, 'retry_of' => $existing->id]
                            );
                        } elseif ($isIdempotent) {
                            $results['duplicates_prevented']++;
                            $results['details'][] = array_merge(
                                $this->resultsDuplicateDetail(
                                    $student, $parent, $phone, $existing, 'Already retried for this examination.'
                                ),
                                ['existing_status' => $existing->status]
                            );
                        } else {
                            $results['failed']++;
                            $results['details'][] = array_merge(
                                $this->resultsSendDetail($student, $parent, $phone, $retry),
                                ['retried' => true, 'retry_of' => $existing->id]
                            );
                        }

                        continue 2;

                    default:
                        $results['duplicates_prevented']++;
                        $results['details'][] = $this->resultsDuplicateDetail(
                            $student, $parent, $phone, $existing, 'Cannot retry a message in its current status.'
                        );

                        continue 2;
                }
            }

            // No existing record: send normally. This also covers a retry for a student
            // who has never been notified for this examination.
            try {
                $sendResult = $this->messageService->send(
                    $phone,
                    $message,
                    [
                        'idempotency_key' => $idempotencyKey,
                        'user' => $sendingUser,
                        'category' => 'results',
                        'sender_id' => $senderId,
                        'metadata' => [
                            'student_id' => $student->id,
                            'parent_id' => $parent->id,
                            'examination_id' => $examination->id,
                            'template_id' => $templateId,
                            'is_resend' => $isResend,
                            'resend_reason' => $resendReason,
                        ],
                    ]
                );
            } catch (\Throwable $e) {
                // One student's send must never abort the whole batch. Record
                // the failure and continue so remaining recipients are still
                // notified. Never mark every recipient as failed because one
                // recipient or one response-parsing step failed.
                Log::error('Results SMS send failed for student', [
                    'student_id' => $student->id,
                    'phone' => $phone,
                    'examination_id' => $examination->id,
                    'error' => $e->getMessage(),
                ]);

                $results['failed']++;
                $results['details'][] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'parent_name' => $parent->name,
                    'phone' => $phone,
                    'error' => 'Send failed: '.$e->getMessage(),
                ];

                continue;
            }

            if ($sendResult['success']) {
                $results['sent']++;
                $results['details'][] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'parent_name' => $parent->name,
                    'phone' => $phone,
                    'message_id' => $sendResult['messageId'],
                    'status' => $sendResult['status'],
                ];
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'parent_name' => $parent->name,
                    'phone' => $phone,
                    'error' => $sendResult['error'] ?? 'Send failed.',
                ];
            }
        }

        return $results;
    }

    /**
     * Build a detail array for a blocked Results SMS notification.
     */
    private function resultsDuplicateDetail(
        Student $student,
        User $parent,
        string $phone,
        SmsMessage $existing,
        string $reason,
        array $extra = []
    ): array {
        return array_merge([
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'parent_name' => $parent->name,
            'phone' => $phone,
            'reason' => $reason,
            'existing_status' => $existing->status,
            'blocked' => true,
        ], $extra);
    }

    /**
     * Build a detail array for a Results SMS that was accepted by the provider.
     */
    private function resultsSendDetail(
        Student $student,
        User $parent,
        string $phone,
        array $result
    ): array {
        return [
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'parent_name' => $parent->name,
            'phone' => $phone,
            'message_id' => $result['messageId'] ?? null,
            'status' => $result['status'] ?? null,
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Retry a confirmed failed Results SMS through the authorized resend mechanism.
     *
     * The original failed record is preserved; the retry is recorded as a new
     * message with a distinct idempotency key (`sms_resend:<original id>`) and a
     * delivery event on the original record. A database transaction with a row
     * lock serializes concurrent retry attempts, and the retry idempotency key is
     * re-checked under that lock, so repeated or concurrent retry requests cannot
     * create duplicate attempts or duplicate provider calls.
     */
    private function retryFailedResultsSms(SmsMessage $existing, User $sendingUser, ?string $reason): array
    {
        return DB::transaction(function () use ($existing, $sendingUser, $reason) {
            // Re-check status under lock so concurrent retries cannot both proceed.
            $existing = SmsMessage::where('id', $existing->id)
                ->lockForUpdate()
                ->first();

            if (! $existing || $existing->status !== 'failed') {
                return [
                    'success' => false,
                    'skipped' => true,
                    'idempotent' => false,
                    'rate_limited' => false,
                    'reason' => 'Message is no longer in a retryable (failed) state.',
                    'existing_status' => $existing?->status,
                ];
            }

            // Idempotency guard for the retry record itself, checked under the same
            // lock so concurrent retry attempts cannot both reach the provider.
            $resendKey = 'sms_resend:'.$existing->id;
            $alreadyRetried = SmsMessage::where('idempotency_key', $resendKey)
                ->where('to', $existing->to)
                ->exists();

            if ($alreadyRetried) {
                return [
                    'success' => true,
                    'idempotent' => true,
                    'rate_limited' => false,
                    'messageId' => null,
                    'status' => null,
                    'error' => null,
                ];
            }

            return $this->messageService->resendMessage($existing, $sendingUser, $reason);
        });
    }

    /**
     * Get the history of results SMS notifications for an examination.
     */
    public function getResultsHistory(int $examinationId): array
    {
        return SmsMessage::where('category', 'results')
            ->where('metadata->examination_id', $examinationId)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'student_id' => $m->metadata['student_id'] ?? null,
                'parent_id' => $m->metadata['parent_id'] ?? null,
                'to' => $m->to,
                'message' => $m->message,
                'status' => $m->status,
                'provider_status' => $m->provider_status,
                'sent_at' => $m->sent_at,
                'delivered_at' => $m->delivered_at,
                'error_message' => $m->error_message,
                'sent_by' => $m->user?->name,
                'sent_at_formatted' => $m->created_at?->format('Y-m-d H:i:s'),
            ])->toArray();
    }

    /**
     * Check if a student has already been notified for an examination.
     */
    public function isNotified(int $studentId, int $parentId, int $examinationId, string $message): bool
    {
        $key = SmsMessageService::resultsIdempotencyKey($studentId, $parentId, $examinationId, $message);

        return SmsMessage::where('idempotency_key', $key)
            ->where('category', 'results')
            ->exists();
    }
}

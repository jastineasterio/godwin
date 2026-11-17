<?php

namespace App\Services\Sms;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Examination;
use App\Models\SmsMessage;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Results SMS workflow service.
 *
 * Implements the secure, idempotent workflow for notifying parents
 * when student examination results are published.
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
     * Only students with approved assessment results are included.
     * Draft, submitted (unapproved), returned, or unpublished results
     * are excluded.
     */
    public function getEligibleStudents(int $examinationId): array
    {
        $examination = Examination::published()->findOrFail($examinationId);

        // Assessments linked to this examination with approved status.
        $assessments = Assessment::forExamination($examinationId)
            ->examinationResults()
            ->where('status', 'approved')
            ->with(['student.class', 'subject'])
            ->get();

        $studentIds = $assessments->pluck('student_id')->unique()->toArray();

        $students = Student::whereIn('id', $studentIds)
            ->active()
            ->with(['class', 'parents'])
            ->get();

        $result = [];

        foreach ($students as $student) {
            $studentAssessments = $assessments->where('student_id', $student->id);

            // Find the parent/guardian for this student.
            $parent = $student->primaryParent();
            $parentPhone = $parent ? $this->recipientService->normalize($parent->phone) : null;

            $result[] = [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'reg_no' => $student->reg_no,
                'class_name' => $student->class?->name,
                'class_id' => $student->class_id,
                'parent_id' => $parent?->id,
                'parent_name' => $parent?->name,
                'parent_phone' => $parentPhone,
                'raw_parent_phone' => $parent?->phone,
                'has_phone' => (bool) $parentPhone,
                'subject_count' => $studentAssessments->count(),
                'assessments' => $studentAssessments->map(fn ($a) => [
                    'subject' => $a->subject?->name,
                    'score' => $a->score,
                    'max_score' => $a->max_score,
                    'percentage' => $a->percentage,
                ])->toArray(),
            ];
        }

        // Separate those with and without phone numbers.
        $withPhone = array_values(array_filter($result, fn ($s) => $s['has_phone']));
        $withoutPhone = array_values(array_filter($result, fn ($s) => !$s['has_phone']));

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
     */
    public function preview(
        int $examinationId,
        string $template,
        ?User $user = null
    ): array {
        $eligible = $this->getEligibleStudents($examinationId);

        $sampleMessage = null;
        if (! empty($eligible['students'])) {
            $first = $eligible['students'][0];
            $sampleMessage = $this->templateService->render($template, [
                'parent_name' => $first['parent_name'] ?? 'Parent',
                'student_name' => $first['student_name'],
                'class_name' => $first['class_name'] ?? '',
                'exam_name' => $eligible['examination']['name'],
                'term_name' => $eligible['examination']['term'] ?? '',
                'school_name' => config('school.name') ?? 'God-Win Daycare & Nursery School',
                'date' => now()->format('Y-m-d'),
            ]);
        }

        // Check for duplicate recipients (same parent for multiple children).
        $phones = array_column($eligible['students'], 'parent_phone');
        $duplicates = [];
        $phoneCounts = array_count_values($phones);
        foreach ($phoneCounts as $phone => $count) {
            if ($count > 1) {
                $duplicates[$phone] = $count;
            }
        }

        return [
            'examination' => $eligible['examination'],
            'template' => $template,
            'sample_message' => $sampleMessage,
            'total_students' => $eligible['total_students'],
            'total_parents' => $eligible['with_phone'],
            'valid_phone_numbers' => $eligible['valid_phone_numbers'],
            'missing_phone_count' => $eligible['without_phone'],
            'duplicate_recipients' => $duplicates,
            'students' => $eligible['students'],
            'missing_phone_students' => $eligible['missing_phone_students'],
        ];
    }

    /**
     * Step 6: Send results SMS notifications with idempotency protection.
     *
     * Each student-parent combination gets one SMS. Duplicate notifications
     * for the same student/examination/recipient are prevented via idempotency
     * keys. Resends are recorded separately with a reason.
     */
    public function sendResultsNotifications(
        int $examinationId,
        array $studentIds,
        string $message,
        User $sendingUser,
        ?string $templateId = null,
        array $options = []
    ): array {
        $examination = Examination::published()->findOrFail($examinationId);
        $isResend = $options['is_resend'] ?? false;
        $resendReason = $options['resend_reason'] ?? null;

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

            // Only approved results for this examination may be notified.
            // Draft, submitted, returned, or missing results are never sent.
            // This check runs regardless of is_resend: resending never bypasses approval.
            $hasApprovedResult = Assessment::forExamination($examination->id)
                ->examinationResults()
                ->where('student_id', $student->id)
                ->where('status', 'approved')
                ->exists();

            if (! $hasApprovedResult) {
                $results['skipped']++;
                $results['details'][] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'reason' => 'No approved result for this examination.',
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

            // Idempotency key based on student + parent + examination + message hash.
            $idempotencyKey = SmsMessageService::resultsIdempotencyKey(
                $student->id,
                $parent->id,
                $examination->id,
                $message
            );

            // Check if this notification already exists (unless resend).
            if (! $isResend) {
                $existing = SmsMessage::where('idempotency_key', $idempotencyKey)
                    ->where('category', 'results')
                    ->first();

                if ($existing) {
                    $results['duplicates_prevented']++;
                    $results['details'][] = [
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'parent_name' => $parent->name,
                        'phone' => $phone,
                        'reason' => 'Already notified for this examination.',
                        'existing_status' => $existing->status,
                    ];
                    continue;
                }
            }

            // Send the SMS.
            $sendResult = $this->messageService->send(
                $phone,
                $message,
                [
                    'idempotency_key' => $idempotencyKey,
                    'user' => $sendingUser,
                    'category' => 'results',
                    'sender_id' => $options['sender_id'] ?? null,
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

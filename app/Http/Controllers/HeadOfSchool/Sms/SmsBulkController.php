<?php

namespace App\Http\Controllers\HeadOfSchool\Sms;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Sms\SmsMessageService;
use App\Services\Sms\SmsRecipientService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HEAD OF SCHOOL — Bulk SMS composer.
 */
class SmsBulkController extends Controller
{
    protected SmsMessageService $messageService;
    protected SmsRecipientService $recipientService;

    public function __construct(
        SmsMessageService $messageService,
        SmsRecipientService $recipientService
    ) {
        $this->messageService = $messageService;
        $this->recipientService = $recipientService;
    }

    public function index(): Response
    {
        $classes = $this->recipientService->getClasses();
        $templates = \App\Models\SmsTemplate::active()->get(['id', 'name', 'category', 'template']);

        return Inertia::render('Sms/Bulk', [
            'classes' => $classes,
            'templates' => $templates,
            'can_send' => $this->messageService->canSend(auth()->user()),
        ]);
    }

    /** Preview recipients before sending. Returns JSON for the AJAX composer. */
    public function previewRecipients(Request $request)
    {
        if (! $this->messageService->canSend($request->user())) {
            return response()->json(['error' => 'You are not authorized to send SMS.'], 403);
        }

        $validated = $request->validate([
            'recipient_type' => ['required', 'string', 'in:parents_all,parents_class,parents_students,teachers,staff,custom'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'phone_numbers' => ['nullable', 'array'],
            'phone_numbers.*' => ['string'],
        ]);

        $recipients = [];
        $invalid = [];
        $eligibleCount = 0;

        if ($validated['recipient_type'] === 'parents_all') {
            $recipients = $this->recipientService->getParents();
            $eligibleCount = $this->eligibleParentCount(null, null);
        } elseif ($validated['recipient_type'] === 'parents_class') {
            $recipients = $this->recipientService->getParents($validated['class_ids'] ?? []);
            $eligibleCount = $this->eligibleParentCount($validated['class_ids'] ?? [], null);
        } elseif ($validated['recipient_type'] === 'parents_students') {
            $recipients = $this->recipientService->getParents(null, $validated['student_ids'] ?? []);
            $eligibleCount = $this->eligibleParentCount(null, $validated['student_ids'] ?? []);
        } elseif ($validated['recipient_type'] === 'teachers') {
            $recipients = $this->recipientService->getTeachers();
            $eligibleCount = count($recipients);
        } elseif ($validated['recipient_type'] === 'staff') {
            $recipients = $this->recipientService->getStaff();
            $eligibleCount = count($recipients);
        } elseif ($validated['recipient_type'] === 'custom') {
            $custom = $this->recipientService->getCustom($validated['phone_numbers'] ?? []);
            $recipients = $custom['recipients'];
            $invalid = $custom['invalid'];
        }

        // Detect duplicates on the raw list (before dedup) so they can be reported.
        $duplicates = $this->recipientService->findDuplicates($recipients);

        // Remove duplicate phone numbers, keeping the first occurrence.
        $seen = [];
        $unique = [];
        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'] ?? null;
            if (! $phone || isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $unique[] = $recipient;
        }
        $recipients = array_values($unique);

        $invalidCount = count($invalid);
        if ($eligibleCount > 0) {
            $invalidCount += max(0, $eligibleCount - count($recipients) - count($invalid));
        }

        return response()->json([
            'success' => true,
            'recipients' => $recipients,
            'recipient_count' => count($recipients),
            'valid_count' => count($recipients),
            'invalid_count' => $invalidCount,
            'invalid_phones' => array_values($invalid),
            'duplicates' => $duplicates,
            'duplicate_count' => count($duplicates),
            'recipient_type' => $validated['recipient_type'],
        ]);
    }

    /**
     * Count eligible users matching the given parent filter (regardless of phone validity).
     */
    private function eligibleParentCount(?array $classIds = null, ?array $studentIds = null): int
    {
        $query = \App\Models\User::parents()->active();

        if ($studentIds && ! empty($studentIds)) {
            $query->whereHas('students', function ($q) use ($studentIds) {
                $q->whereIn('students.id', $studentIds);
            });
        } elseif ($classIds && ! empty($classIds)) {
            $query->whereHas('students.class', function ($q) use ($classIds) {
                $q->whereIn('classes.id', $classIds);
            });
        }

        return (int) $query->count();
    }

    /** Send bulk SMS (queued). Returns JSON for the AJAX composer. */
    public function send(Request $request)
    {
        if (! $this->messageService->canSend($request->user())) {
            return response()->json(['error' => 'You are not authorized to send SMS.'], 403);
        }

        $validated = $request->validate([
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.phone' => ['required', 'string'],
            'recipients.*.name' => ['nullable', 'string'],
            'message' => ['required', 'string', 'max:1600'],
            'sender_id' => ['nullable', 'string', 'max:50'],
            'campaign_name' => ['nullable', 'string', 'max:100'],
        ]);

        $phones = array_values(array_unique(array_filter(
            array_column($validated['recipients'], 'phone'),
            fn ($p) => $p !== ''
        )));

        if (empty($phones)) {
            return response()->json([
                'success' => false,
                'error' => 'No valid recipients to send to.',
            ], 422);
        }

        $result = $this->messageService->sendBulk($phones, $validated['message'], [
            'user' => $request->user(),
            'category' => 'bulk',
            'sender_id' => $validated['sender_id'] ?? null,
            'campaign_name' => $validated['campaign_name'] ?? null,
            'metadata' => [
                'sent_by' => $request->user()->name,
                'sent_by_id' => $request->user()->id,
            ],
        ]);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to queue bulk SMS.',
                'code' => $result['code'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'campaign_id' => $result['campaign_id'] ?? null,
            'recipient_count' => $result['recipient_count'] ?? count($phones),
            'status' => $result['status'] ?? 'queued',
            'message' => 'Bulk SMS queued for ' . ($result['recipient_count'] ?? count($phones)) . ' recipients.',
        ]);
    }
}

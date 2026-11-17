<?php

namespace App\Http\Controllers\HeadOfSchool\Sms;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Sms\SmsMessageService;
use App\Services\Sms\SmsRecipientService;
use Illuminate\Http\RedirectResponse;
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

    /** Preview recipients before sending. */
    public function previewRecipients(Request $request): Response
    {
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

        if ($validated['recipient_type'] === 'parents_all') {
            $recipients = $this->recipientService->getParents();
        } elseif ($validated['recipient_type'] === 'parents_class') {
            $recipients = $this->recipientService->getParents($validated['class_ids'] ?? []);
        } elseif ($validated['recipient_type'] === 'parents_students') {
            $recipients = $this->recipientService->getParents(null, $validated['student_ids'] ?? []);
        } elseif ($validated['recipient_type'] === 'teachers') {
            $recipients = $this->recipientService->getTeachers();
        } elseif ($validated['recipient_type'] === 'staff') {
            $recipients = $this->recipientService->getStaff();
        } elseif ($validated['recipient_type'] === 'custom') {
            $custom = $this->recipientService->getCustom($validated['phone_numbers'] ?? []);
            $recipients = $custom['recipients'];
            $invalid = $custom['invalid'];
        }

        // Detect duplicates.
        $duplicates = $this->recipientService->findDuplicates($recipients);

        return Inertia::render('Sms/BulkPreview', [
            'recipients' => $recipients,
            'recipient_count' => count($recipients),
            'duplicates' => $duplicates,
            'invalid_phones' => $invalid ?? [],
            'recipient_type' => $validated['recipient_type'],
        ]);
    }

    /** Send bulk SMS (queued). */
    public function send(Request $request): RedirectResponse
    {
        if (! $this->messageService->canSend($request->user())) {
            return back()->with('error', 'You are not authorized to send SMS.');
        }

        $validated = $request->validate([
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.phone' => ['required', 'string'],
            'recipients.*.name' => ['nullable', 'string'],
            'message' => ['required', 'string', 'max:1600'],
            'sender_id' => ['nullable', 'string', 'max:50'],
            'campaign_name' => ['nullable', 'string', 'max:100'],
        ]);

        $phones = array_column($validated['recipients'], 'phone');

        // Detect and remove duplicates before sending.
        $uniquePhones = array_values(array_unique($phones));

        if (count($uniquePhones) < count($phones)) {
            return back()->with('error', 'Duplicate phone numbers detected. Please remove duplicates before sending.');
        }

        $result = $this->messageService->sendBulk($uniquePhones, $validated['message'], [
            'user' => $request->user(),
            'category' => 'bulk',
            'sender_id' => $validated['sender_id'] ?? null,
            'campaign_name' => $validated['campaign_name'] ?? null,
            'metadata' => [
                'sent_by' => $request->user()->name,
                'sent_by_id' => $request->user()->id,
            ],
        ]);

        if ($result['success']) {
            return back()->with('success', 'Bulk SMS queued for ' . $result['recipient_count'] . ' recipients.');
        }

        return back()->with('error', $result['error'] ?? 'Failed to queue bulk SMS.');
    }
}

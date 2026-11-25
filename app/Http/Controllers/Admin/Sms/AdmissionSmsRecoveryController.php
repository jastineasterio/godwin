<?php

namespace App\Http\Controllers\Admin\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\SmsMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ADMIN — Recovery for unresolved admission notification SMS.
 *
 * Only administrators may access this page. Head of School users retain
 * their existing resend privilege through the SMS history page and are not
 * granted any additional permission here.
 *
 * Unresolved admission notifications (status `submission_unknown` or
 * `failed`) are listed for investigation. A message is never automatically
 * resent: reconciliation requires an explicit provider status lookup, and a
 * resend requires an explicit confirmation.
 */
class AdmissionSmsRecoveryController extends Controller
{
    protected SmsMessageService $messageService;

    public function __construct(SmsMessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    /** List unresolved admission notification SMS records. */
    public function index(Request $request): Response
    {
        $this->authorizeRecovery($request->user());

        $query = SmsMessage::where('category', 'admission')
            ->whereIn('status', ['submission_unknown', 'failed'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('to', 'like', "%{$search}%")
                    ->orWhere('metadata->reference_no', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%");
            });
        }

        $messages = $query->paginate(20)->withQueryString();

        $items = $messages->getCollection()->map(fn ($m) => $this->itemArray($m))->all();

        return Inertia::render('Admin/Sms/AdmissionRecovery', [
            'messages' => [
                'data' => $items,
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
            'filters' => $request->only(['status', 'search']),
            'statuses' => ['submission_unknown', 'failed'],
        ]);
    }

    /** Reconcile a submission_unknown message via the provider status lookup. */
    public function reconcile(Request $request, SmsMessage $message)
    {
        $this->authorizeRecovery($request->user());

        if ($message->category !== 'admission') {
            abort(404, 'Admission notification not found.');
        }

        $result = $this->messageService->reconcileSubmissionUnknown($message);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /** Explicitly confirmed manual resend of an unresolved admission notification. */
    public function resend(Request $request, SmsMessage $message): RedirectResponse
    {
        $this->authorizeRecovery($request->user());

        if ($message->category !== 'admission') {
            abort(404, 'Admission notification not found.');
        }

        $validated = $request->validate([
            'confirmed' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $request->boolean('confirmed')) {
            return back()->with('error', 'You must explicitly confirm before resending an unresolved message.');
        }

        $result = $this->messageService->resendMessage(
            $message,
            $request->user(),
            $validated['reason'] ?? null
        );

        if ($result['success']) {
            return back()->with('success', 'Admission notification resent.'.($result['idempotent'] ? ' This resend was already recorded.' : ''));
        }

        return back()->with('error', $result['error'] ?? 'Resend failed.');
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    protected function authorizeRecovery(?User $user): void
    {
        if (! $user || ! $this->messageService->canManageSettings($user)) {
            abort(403, 'Only administrators can reconcile and resend admission notifications.');
        }
    }

    protected function itemArray(SmsMessage $message, bool $detail = false): array
    {
        $metadata = $message->metadata ?? [];

        $array = [
            'id' => $message->id,
            'reference_no' => $metadata['reference_no'] ?? null,
            'notification_type' => $metadata['notification_type'] ?? null,
            'recipient' => $this->maskPhone($message->to),
            'to' => $message->to,
            'status' => $message->status,
            'provider_status' => $message->provider_status,
            'provider_message_id' => $message->provider_message_id,
            'error_message' => $message->error_message,
            'is_unknown' => $message->status === 'submission_unknown',
            'is_retryable' => in_array($message->status, ['submission_unknown', 'failed'], true),
            'created_at' => $message->created_at,
            'sent_at' => $message->sent_at,
            'delivered_at' => $message->delivered_at,
        ];

        if ($detail) {
            $array['message'] = $message->message;
            $array['sender_id'] = $message->sender_id;
            $array['batch_id'] = $message->batch_id;
            $array['provider_type'] = $message->provider_type;
            $array['provider_name'] = $message->provider_name;
            $array['application_id'] = $metadata['application_id'] ?? null;
            $array['resend_of'] = $metadata['resend_of'] ?? null;
            $array['metadata'] = $metadata;
        }

        return $array;
    }

    /** Mask a phone number so only the last four digits are visible. */
    protected function maskPhone(?string $phone): string
    {
        if (! $phone) {
            return '—';
        }

        $length = strlen($phone);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($phone, -4);
    }
}

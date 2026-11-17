<?php

namespace App\Http\Controllers\HeadOfSchool\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Services\Sms\SmsMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HEAD OF SCHOOL — SMS history and delivery reports.
 */
class SmsHistoryController extends Controller
{
    protected SmsMessageService $messageService;

    public function __construct(SmsMessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    public function index(Request $request): Response
    {
        $query = SmsMessage::with('user')
            ->orderByDesc('created_at');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('to', 'like', "%{$request->query('search')}%")
                    ->orWhere('message', 'like', "%{$request->query('search')}%");
            });
        }

        $messages = $query->paginate(20)->withQueryString();

        $categories = ['general', 'bulk', 'results', 'announcements', 'notices', 'fee_reminders', 'attendance'];
        $statuses = ['queued', 'sent', 'submission_unknown', 'delivered', 'failed'];

        return Inertia::render('Sms/History', [
            'messages' => $messages,
            'categories' => $categories,
            'statuses' => $statuses,
            'filters' => $request->only(['status', 'category', 'from', 'to', 'search']),
        ]);
    }

    public function show(SmsMessage $message): Response
    {
        $message->load('user', 'deliveryEvents');

        return Inertia::render('Sms/Show', [
            'message' => [
                'id' => $message->id,
                'to' => $message->to,
                'message' => $message->message,
                'category' => $message->category,
                'status' => $message->status,
                'provider_status' => $message->provider_status,
                'provider_message_id' => $message->provider_message_id,
                'batch_id' => $message->batch_id,
                'sender_id' => $message->sender_id,
                'sent_at' => $message->sent_at,
                'delivered_at' => $message->delivered_at,
                'error_message' => $message->error_message,
                'sent_by' => $message->user?->name,
                'created_at' => $message->created_at,
                'delivery_events' => $message->deliveryEvents->map(fn ($e) => [
                    'status' => $e->status,
                    'provider_status' => $e->provider_status,
                    'occurred_at' => $e->occurred_at,
                    'error_message' => $e->error_message,
                ])->toArray(),
            ],
        ]);
    }

    /** Manually resend an SMS message (e.g. after an ambiguous timeout). */
    public function resend(Request $request, SmsMessage $message): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->messageService->resendMessage(
            $message,
            $request->user(),
            $validated['reason'] ?? null
        );

        if ($result['success']) {
            return back()->with('success', 'SMS resent successfully.'.($result['idempotent'] ? ' This resend was already recorded.' : ''));
        }

        return back()->with('error', $result['error'] ?? 'Resend failed.');
    }
}

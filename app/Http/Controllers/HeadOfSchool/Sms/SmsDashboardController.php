<?php

namespace App\Http\Controllers\HeadOfSchool\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use App\Services\Sms\SmsMessageService;
use App\Services\Sms\SmsDeliveryTrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HEAD OF SCHOOL — SMS dashboard.
 *
 * No API keys or provider secrets are exposed on this page.
 */
class SmsDashboardController extends Controller
{
    protected SmsMessageService $messageService;
    protected SmsDeliveryTrackingService $trackingService;

    public function __construct(
        SmsMessageService $messageService,
        SmsDeliveryTrackingService $trackingService
    ) {
        $this->messageService = $messageService;
        $this->trackingService = $trackingService;
    }

    public function index(): Response
    {
        $stats = $this->trackingService->getStatistics();
        $balance = $this->messageService->checkBalance();
        $capabilities = $this->messageService->getCapabilities();

        $recent = SmsMessage::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'to' => $m->to,
                'message' => $m->message,
                'category' => $m->category,
                'status' => $m->status,
                'provider_status' => $m->provider_status,
                'sent_by' => $m->user?->name,
                'sent_at' => $m->created_at,
            ])->toArray();

        $campaigns = SmsCampaign::with('user')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'category' => $c->category,
                'total_recipients' => $c->total_recipients,
                'sent_count' => $c->sent_count,
                'delivered_count' => $c->delivered_count,
                'failed_count' => $c->failed_count,
                'status' => $c->status,
                'sent_by' => $c->user?->name,
            ])->toArray();

        return Inertia::render('Dashboards/Sms', [
            'stats' => $stats,
            'balance' => $balance['balance'] ?? null,
            'balance_error' => $balance['error'] ?? null,
            'capabilities' => $capabilities,
            'recent' => $recent,
            'campaigns' => $campaigns,
        ]);
    }
}

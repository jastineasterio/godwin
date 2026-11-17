<?php

namespace App\Http\Controllers\Admin\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsPurchase;
use App\Services\Sms\SmsCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ADMIN — SMS credit purchases (DARSMS).
 *
 * Only administrators can purchase credits, view purchase history,
 * and retry payments. Credits are added by the provider after
 * payment confirmation — never manually.
 */
class SmsCreditController extends Controller
{
    protected SmsCreditService $creditService;

    public function __construct(SmsCreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /** Display the SMS credit purchase page. */
    public function index(): Response
    {
        $balance = $this->creditService->getBalance();
        $history = $this->creditService->getHistory('ALL', 1, 20);

        return Inertia::render('Admin/Sms/Credits', [
            'balance' => $balance['balance'] ?? null,
            'currency' => $balance['currency'] ?? null,
            'balance_error' => $balance['error'] ?? null,
            'purchases' => $history['purchases'] ?? [],
            'pagination' => $history['pagination'] ?? [],
            'can_purchase' => $this->creditService->canPurchase(auth()->user()),
        ]);
    }

    /** Initiate an SMS credit purchase. */
    public function purchase(Request $request): RedirectResponse
    {
        if (! $this->creditService->canPurchase($request->user())) {
            return back()->with('error', 'Only administrators can purchase SMS credits.');
        }

        $validated = $request->validate([
            'quantity_sms' => ['required', 'integer', 'min:1', 'max:1000000'],
            'payer_phone' => ['required', 'string', 'max:20'],
        ]);

        $result = $this->creditService->purchase(
            $validated['quantity_sms'],
            $validated['payer_phone'],
            $request->user()
        );

        if ($result['success']) {
            return back()->with('success', $result['message'] . ' Transaction ref: ' . ($result['transaction_ref'] ?? '—'));
        }

        return back()->with('error', $result['error'] ?? 'Purchase failed.');
    }

    /** View purchase history with filters. */
    public function history(Request $request): Response
    {
        $status = $request->query('status', 'ALL');
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $history = $this->creditService->getHistory($status, $page, $limit);

        return Inertia::render('Admin/Sms/PurchaseHistory', [
            'purchases' => $history['purchases'] ?? [],
            'pagination' => $history['pagination'] ?? [],
            'status' => $status,
        ]);
    }

    /** Retry payment for an eligible purchase. */
    public function retry(Request $request, SmsPurchase $purchase): RedirectResponse
    {
        if (! $this->creditService->canPurchase($request->user())) {
            return back()->with('error', 'Unauthorized.');
        }

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $result = $this->creditService->retry(
            $purchase->provider_purchase_id ?? '',
            $request->user(),
            $validated['phone'] ?? null
        );

        if ($result['success']) {
            return back()->with('success', 'Payment retry initiated.');
        }

        return back()->with('error', $result['error'] ?? 'Retry failed.');
    }
}

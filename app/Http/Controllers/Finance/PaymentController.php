<?php

namespace App\Http\Controllers\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * PAYMENT ENTRY + RECEIPTS.
 *
 * Recording a payment updates the invoice balance and status atomically,
 * and issues a printable receipt.
 */
class PaymentController extends Controller
{
    /**
     * Record a payment against an invoice.
     *
     * Guard rails:
     *  • amount must be > 0 and never exceed the outstanding balance
     *  • a void invoice cannot receive money
     *  • receipt numbers are sequential per year
     */
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($invoice->status === InvoiceStatus::Void) {
            return back()->withErrors(['amount' => 'A void invoice cannot receive a payment.']);
        }

        $balance = (float) $invoice->balance;

        if ((float) $data['amount'] > $balance) {
            return back()->withErrors([
                'amount' => "Payment cannot exceed the outstanding balance of TZS {$balance}.",
            ]);
        }

        $payment = DB::transaction(function () use ($data, $invoice) {
            $payment = Payment::create([
                'receipt_number' => $this->nextReceiptNumber(),
                'invoice_id' => $invoice->id,
                'student_id' => $invoice->student_id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'status' => PaymentStatus::Completed,
                'received_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $newPaid = (float) $invoice->amount_paid + (float) $data['amount'];

            $invoice->update([
                'amount_paid' => $newPaid,
                'status' => $newPaid >= (float) $invoice->total_amount
                    ? InvoiceStatus::Paid
                    : InvoiceStatus::Partial,
            ]);

            return $payment;
        });

        AuditLog::record(
            auth()->user(),
            'payment.received',
            "Receipt {$payment->receipt_number} — TZS {$payment->amount}",
            $payment
        );

        return back()->with('success', "Payment recorded. Receipt No. {$payment->receipt_number}.");
    }

    /** Refund / reverse a payment (money returns to the outstanding balance). */
    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        abort_if($payment->status === PaymentStatus::Refunded, 422, 'This payment was already refunded.');

        DB::transaction(function () use ($payment) {
            $payment->update([
                'status' => PaymentStatus::Refunded,
                'notes' => trim(($payment->notes ?? '')."\nREFUND: ".$request->string('reason')->toString()),
            ]);

            $invoice = $payment->invoice;
            $newPaid = max(0, (float) $invoice->amount_paid - (float) $payment->amount);

            $invoice->update([
                'amount_paid' => $newPaid,
                'status' => $newPaid <= 0
                    ? InvoiceStatus::Unpaid
                    : ($newPaid >= (float) $invoice->total_amount ? InvoiceStatus::Paid : InvoiceStatus::Partial),
            ]);
        });

        return back()->with('success', "Payment {$payment->receipt_number} has been refunded.");
    }

    /** Printable receipt (browser "Print / Save as PDF"). */
    public function receipt(Payment $payment): View
    {
        $payment->load(['invoice', 'student', 'receivedBy']);

        return view('print.receipt', [
            'school' => config('school'),
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'student' => $payment->student,
            'receivedBy' => $payment->receivedBy,
        ]);
    }

    /** Sequential receipt number: RCT-2026-000456. */
    protected function nextReceiptNumber(): string
    {
        $year = now()->year;

        return sprintf(
            'RCT-%d-%06d',
            $year,
            Payment::withTrashed()->whereYear('created_at', $year)->count() + 1
        );
    }
}

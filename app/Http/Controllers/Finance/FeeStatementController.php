<?php

namespace App\Http\Controllers\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PRINTABLE FEE STATEMENT for a parent — every invoice, every payment and
 * the running balance. Read-only; parents may only see their own children.
 */
class FeeStatementController extends Controller
{
    public function __invoke(Request $request, Student $student): View
    {
        $this->authorizeAccess($request, $student);

        $student->load('class:id,name,code');

        $invoices = $student->invoices()
            ->where('status', '!=', InvoiceStatus::Void->value)
            ->with('payments')
            ->orderBy('issue_date')
            ->get()
            ->map(fn ($invoice) => [
                'number' => $invoice->invoice_number,
                'issue_date' => $invoice->issue_date?->format('d M Y'),
                'due_date' => $invoice->due_date?->format('d M Y'),
                'total' => (float) $invoice->total_amount,
                'paid' => (float) $invoice->amount_paid,
                'balance' => (float) $invoice->balance,
                'status' => $invoice->status->label(),
                'items' => $invoice->items->map(fn ($i) => $i->description)->implode(', '),
            ]);

        $payments = $student->payments()
            ->with('invoice:id,invoice_number')
            ->where('status', 'completed')
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn ($p) => [
                'receipt' => $p->receipt_number,
                'date' => $p->paid_at?->format('d M Y'),
                'method' => $p->method->label(),
                'amount' => (float) $p->amount,
                'invoice' => $p->invoice?->invoice_number,
            ]);

        $totalBilled = $invoices->sum('total');
        $totalPaid = $invoices->sum('paid');

        return view('print.fee-statement', [
            'school' => config('school'),
            'student' => $student,
            'class' => $student->class,
            'invoices' => $invoices,
            'payments' => $payments,
            'totals' => [
                'billed' => $totalBilled,
                'paid' => $totalPaid,
                'balance' => max(0, $totalBilled - $totalPaid),
            ],
        ]);
    }

    /** Leadership, the accountant, and the child's own parents only. */
    protected function authorizeAccess(Request $request, Student $student): void
    {
        $user = $request->user();

        if ($user->hasAnyRole([UserRole::Admin, UserRole::Accountant, UserRole::HeadOfSchool])) {
            return;
        }

        if ($user->isParent() && $student->parents()->where('users.id', $user->id)->exists()) {
            return;
        }

        abort(403, 'You are not allowed to view this statement.');
    }
}

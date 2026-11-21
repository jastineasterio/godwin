<?php

namespace App\Http\Controllers\HeadOfSchool;

use App\Enums\ExpenseCategory;
use App\Enums\FeeType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FINANCE DASHBOARD — real school financial figures from invoices, payments
 * and expenses. Head of School oversight + accountant access.
 */
class FinanceDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeFinance($request);

        $range = $this->parseRange($request);

        $income = Payment::completed()
            ->between($range['from'], $range['to'])
            ->sum('amount');

        $expenses = Expense::query()
            ->between($range['from'], $range['to'])
            ->sum('amount');

        $netBalance = $income - $expenses;

        $outstandingFees = $this->outstandingFees();

        $feesCollected = $income;

        $pendingPayments = Invoice::whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->count();

        $transactionCount = Payment::completed()
            ->between($range['from'], $range['to'])
            ->count()
            + Expense::query()->between($range['from'], $range['to'])->count();

        $incomeByCategory = $this->incomeByCategory($range['from'], $range['to']);
        $expensesByCategory = $this->expensesByCategory($range['from'], $range['to']);
        $monthlyTrend = $this->monthlyTrend($range['from'], $range['to']);

        $stats = [
            'total_income' => (float) $income,
            'total_expenses' => (float) $expenses,
            'net_balance' => (float) $netBalance,
            'outstanding_fees' => (float) $outstandingFees,
            'fees_collected' => (float) $feesCollected,
            'pending_payments' => (int) $pendingPayments,
            'transaction_count' => (int) $transactionCount,
        ];

        return Inertia::render('Finance/Dashboard', [
            'stats' => $stats,
            'charts' => [
                'income_vs_expenses' => $this->incomeVsExpenses($range['from'], $range['to']),
                'monthly_income' => $monthlyTrend['income'],
                'monthly_expenses' => $monthlyTrend['expenses'],
                'income_by_category' => $incomeByCategory,
                'expenses_by_category' => $expensesByCategory,
                'net_balance_trend' => $monthlyTrend['net'],
            ],
            'range' => $range,
            'filters' => $request->only('period', 'from', 'to'),
        ]);
    }

    public function report(Request $request): Response
    {
        $this->authorizeFinance($request);

        $range = $this->parseRange($request);

        $income = Payment::completed()
            ->between($range['from'], $range['to'])
            ->sum('amount');

        $expenses = Expense::query()
            ->between($range['from'], $range['to'])
            ->sum('amount');

        $netBalance = $income - $expenses;

        $transactions = collect();

        // Income transactions (payments)
        Payment::completed()
            ->between($range['from'], $range['to'])
            ->with(['student:id,first_name,last_name,reg_no', 'receivedBy:id,name', 'invoice'])
            ->orderByDesc('paid_at')
            ->get()
            ->each(fn ($p) => $transactions->push([
                'date' => $p->paid_at?->toDateString(),
                'type' => 'income',
                'category' => 'Fee Payment',
                'reference' => $p->receipt_number,
                'description' => "Fee payment — {$p->student?->full_name} ({$p->student?->reg_no})",
                'amount' => (float) $p->amount,
                'recorded_by' => $p->receivedBy?->name,
            ]));

        // Expense transactions
        Expense::query()
            ->between($range['from'], $range['to'])
            ->with('recordedBy:id,name')
            ->orderByDesc('incurred_on')
            ->get()
            ->each(fn ($e) => $transactions->push([
                'date' => $e->incurred_on?->toDateString(),
                'type' => 'expense',
                'category' => $e->category->label(),
                'reference' => $e->reference,
                'description' => $e->description,
                'amount' => (float) $e->amount,
                'recorded_by' => $e->recordedBy?->name,
            ]));

        $transactions = $transactions->sortByDesc(fn ($t) => $t['date'])->values()->all();

        return Inertia::render('Finance/Report', [
            'range' => $range,
            'stats' => [
                'opening_balance' => 0,
                'total_income' => (float) $income,
                'total_expenses' => (float) $expenses,
                'net_movement' => (float) $netBalance,
                'closing_balance' => (float) $netBalance,
            ],
            'income_by_category' => $this->incomeByCategory($range['from'], $range['to']),
            'expenses_by_category' => $this->expensesByCategory($range['from'], $range['to']),
            'transactions' => $transactions,
            'outstanding_fees' => (float) $this->outstandingFees(),
        ]);
    }

    public function pdf(Request $request): View
    {
        $this->authorizeFinance($request);

        $range = $this->parseRange($request);

        $income = Payment::completed()
            ->between($range['from'], $range['to'])
            ->sum('amount');

        $expenses = Expense::query()
            ->between($range['from'], $range['to'])
            ->sum('amount');

        $netBalance = $income - $expenses;

        $transactions = collect();

        Payment::completed()
            ->between($range['from'], $range['to'])
            ->with(['student:id,first_name,last_name,reg_no', 'receivedBy:id,name'])
            ->orderByDesc('paid_at')
            ->get()
            ->each(fn ($p) => $transactions->push([
                'date' => $p->paid_at?->format('d M Y'),
                'type' => 'Income',
                'category' => 'Fee Payment',
                'reference' => $p->receipt_number,
                'description' => "Fee payment — {$p->student?->full_name}",
                'amount' => (float) $p->amount,
                'recorded_by' => $p->receivedBy?->name,
            ]));

        Expense::query()
            ->between($range['from'], $range['to'])
            ->with('recordedBy:id,name')
            ->orderByDesc('incurred_on')
            ->get()
            ->each(fn ($e) => $transactions->push([
                'date' => $e->incurred_on?->format('d M Y'),
                'type' => 'Expense',
                'category' => $e->category->label(),
                'reference' => $e->reference,
                'description' => $e->description,
                'amount' => (float) $e->amount,
                'recorded_by' => $e->recordedBy?->name,
            ]));

        $transactions = $transactions->sortByDesc(fn ($t) => $t['date'])->values()->all();

        $periodLabel = $this->periodLabel($range);

        return \Illuminate\Support\Facades\View::make('finance.report-pdf', [
            'school' => config('school'),
            'range' => $range,
            'period_label' => $periodLabel,
            'generated_at' => now()->format('d M Y, H:i'),
            'generated_by' => $request->user()->name,
            'stats' => [
                'total_income' => (float) $income,
                'total_expenses' => (float) $expenses,
                'net_balance' => (float) $netBalance,
            'outstanding_fees' => (float) $this->outstandingFees(),
            ],
            'income_by_category' => $this->incomeByCategory($range['from'], $range['to']),
            'expenses_by_category' => $this->expensesByCategory($range['from'], $range['to']),
            'transactions' => $transactions,
        ]);
    }

    public function csv(Request $request): Response
    {
        $this->authorizeFinance($request);

        $range = $this->parseRange($request);

        $transactions = collect();

        Payment::completed()
            ->between($range['from'], $range['to'])
            ->with('student:id,first_name,last_name,reg_no')
            ->orderByDesc('paid_at')
            ->get()
            ->each(fn ($p) => $transactions->push([
                'date' => $p->paid_at?->toDateString(),
                'type' => 'Income',
                'category' => 'Fee Payment',
                'reference' => $p->receipt_number,
                'description' => "Fee payment — {$p->student?->full_name} ({$p->student?->reg_no})",
                'amount' => (float) $p->amount,
                'recorded_by' => $p->student?->reg_no,
            ]));

        Expense::query()
            ->between($range['from'], $range['to'])
            ->orderByDesc('incurred_on')
            ->get()
            ->each(fn ($e) => $transactions->push([
                'date' => $e->incurred_on?->toDateString(),
                'type' => 'Expense',
                'category' => $e->category->label(),
                'reference' => $e->reference,
                'description' => $e->description,
                'amount' => (float) $e->amount,
                'recorded_by' => $e->recorded_by,
            ]));

        $transactions = $transactions->sortByDesc(fn ($t) => $t['date'])->values()->all();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="finance-report-'.$range['from'].'-to-'.$range['to'].'.csv"',
        ];

        $callback = function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Type', 'Category', 'Reference', 'Description', 'Amount (TZS)', 'Recorded By']);
            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t['date'],
                    $t['type'],
                    $t['category'],
                    $t['reference'],
                    $t['description'],
                    $t['amount'],
                    $t['recorded_by'],
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    protected function authorizeFinance(Request $request): void
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin, UserRole::Accountant]),
            403
        );
    }

    /** Outstanding fees across all non-void, non-deleted invoices (never negative). */
    protected function outstandingFees(): float
    {
        return (float) Invoice::outstanding()
            ->get()
            ->sum('balance');
    }

    /** Resolve the reporting date range from period presets or custom dates. */
    protected function parseRange(Request $request): array
    {
        $period = $request->query('period', 'current_month');
        $from = $request->query('from');
        $to = $request->query('to');

        $now = Carbon::now();

        if ($from && $to) {
            return ['from' => Carbon::parse($from)->startOfDay()->toDateString(),
                'to' => Carbon::parse($to)->endOfDay()->toDateString(),
                'period' => 'custom'];
        }

        return match ($period) {
            'current_week' => [
                'from' => $now->copy()->startOfWeek()->toDateString(),
                'to' => $now->copy()->endOfWeek()->toDateString(),
                'period' => 'current_week',
            ],
            'previous_week' => [
                'from' => $now->copy()->subWeek()->startOfWeek()->toDateString(),
                'to' => $now->copy()->subWeek()->endOfWeek()->toDateString(),
                'period' => 'previous_week',
            ],
            'current_month' => [
                'from' => $now->copy()->startOfMonth()->toDateString(),
                'to' => $now->copy()->endOfMonth()->toDateString(),
                'period' => 'current_month',
            ],
            'previous_month' => [
                'from' => $now->copy()->subMonth()->startOfMonth()->toDateString(),
                'to' => $now->copy()->subMonth()->endOfMonth()->toDateString(),
                'period' => 'previous_month',
            ],
            'current_year' => [
                'from' => $now->copy()->startOfYear()->toDateString(),
                'to' => $now->copy()->endOfYear()->toDateString(),
                'period' => 'current_year',
            ],
            'previous_year' => [
                'from' => $now->copy()->subYear()->startOfYear()->toDateString(),
                'to' => $now->copy()->subYear()->endOfYear()->toDateString(),
                'period' => 'previous_year',
            ],
            default => [
                'from' => $now->copy()->startOfMonth()->toDateString(),
                'to' => $now->copy()->endOfMonth()->toDateString(),
                'period' => 'current_month',
            ],
        };
    }

    protected function incomeByCategory(string $from, string $to): array
    {
        // Income broken down by fee type via payments -> invoices -> items -> fee structures.
        $rows = InvoiceItem::join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('payments', 'payments.invoice_id', '=', 'invoices.id')
            ->join('fee_structures', 'invoice_items.fee_structure_id', '=', 'fee_structures.id')
            ->where('payments.status', PaymentStatus::Completed->value)
            ->whereBetween('payments.paid_at', [$from, $to])
            ->selectRaw('fee_structures.type as category, SUM(payments.amount) as total')
            ->groupBy('fee_structures.type')
            ->get();

        return $rows->map(fn ($r) => [
            'name' => FeeType::tryFrom($r->category)?->label() ?? $r->category,
            'value' => (float) $r->total,
        ])->values()->all();
    }

    protected function expensesByCategory(string $from, string $to): array
    {
        $rows = Expense::query()
            ->between($from, $to)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        return $rows->map(fn ($r) => [
            'name' => $r->category ? $r->category->label() : 'Unknown',
            'value' => (float) $r->total,
        ])->values()->all();
    }

    /** Monthly income / expenses / net trend over the last 6 months. */
    protected function monthlyTrend(string $from, string $to): array
    {
        $start = Carbon::parse($from)->copy()->startOfMonth();
        $end = Carbon::parse($to)->copy()->startOfMonth();
        $months = [];

        for ($m = $start; $m->lte($end); $m = $m->copy()->addMonth()) {
            $label = $m->format('M Y');
            $monthFrom = $m->copy()->startOfMonth()->toDateString();
            $monthTo = $m->copy()->endOfMonth()->toDateString();

            $inc = (float) Payment::completed()->between($monthFrom, $monthTo)->sum('amount');
            $exp = (float) Expense::query()->between($monthFrom, $monthTo)->sum('amount');

            $months[] = [
                'month' => $label,
                'income' => $inc,
                'expenses' => $exp,
                'net' => $inc - $exp,
            ];
        }

        return [
            'income' => collect($months)->map(fn ($m) => ['month' => $m['month'], 'value' => $m['income']])->all(),
            'expenses' => collect($months)->map(fn ($m) => ['month' => $m['month'], 'value' => $m['expenses']])->all(),
            'net' => collect($months)->map(fn ($m) => ['month' => $m['month'], 'value' => $m['net']])->all(),
        ];
    }

    /** Income vs expenses grouped by month (last 6 months). */
    protected function incomeVsExpenses(string $from, string $to): array
    {
        $start = Carbon::parse($from)->copy()->startOfMonth();
        $end = Carbon::parse($to)->copy()->startOfMonth();
        $data = [];

        for ($m = $start; $m->lte($end); $m = $m->copy()->addMonth()) {
            $monthFrom = $m->copy()->startOfMonth()->toDateString();
            $monthTo = $m->copy()->endOfMonth()->toDateString();

            $data[] = [
                'month' => $m->format('M Y'),
                'income' => (float) Payment::completed()->between($monthFrom, $monthTo)->sum('amount'),
                'expenses' => (float) Expense::query()->between($monthFrom, $monthTo)->sum('amount'),
            ];
        }

        return $data;
    }

    protected function periodLabel(array $range): string
    {
        $from = Carbon::parse($range['from'])->format('d M Y');
        $to = Carbon::parse($range['to'])->format('d M Y');

        return "{$from} — {$to}";
    }
}

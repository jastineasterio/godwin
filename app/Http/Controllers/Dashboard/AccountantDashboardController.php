<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ACCOUNTANT — financial management only (no academic writes).
 */
class AccountantDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();

        // --- Financial KPI cards ------------------------------------------
        $expectedRevenue = (float) Invoice::where('status', '!=', InvoiceStatus::Void)->sum('total_amount');
        $feesPaid = (float) Payment::completed()->sum('amount');
        $outstanding = max(0, $expectedRevenue - $feesPaid);
        $monthlyExpenses = (float) Expense::between(
            $monthStart->toDateString(),
            $now->toDateString()
        )->sum('amount');

        // --- Monthly income vs expenses (grouped bar, last 6 months) ------
        $incomeVsExpense = collect(range(5, 0))->map(function (int $monthsAgo) use ($now) {
            $start = $now->copy()->subMonths($monthsAgo)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $income = Payment::completed()
                ->between($start->toDateTimeString(), $end->toDateTimeString())
                ->sum('amount');

            $expense = Expense::between($start->toDateString(), $end->toDateString())
                ->sum('amount');

            return [
                'month' => $start->format('M'),
                'income' => (float) $income,
                'expenses' => (float) $expense,
            ];
        });

        // --- Fee collection doughnut (paid vs outstanding) ----------------
        $collection = [
            ['name' => 'Collected', 'value' => round($feesPaid, 2)],
            ['name' => 'Outstanding', 'value' => round($outstanding, 2)],
        ];

        // --- Recent transactions table -------------------------------------
        $recentTransactions = Payment::with(['student:id,first_name,last_name,reg_no'])
            ->latest('paid_at')
            ->take(8)
            ->get([
                'id', 'receipt_number', 'student_id', 'amount',
                'method', 'paid_at', 'status',
            ]);

        return Inertia::render('Dashboards/Accountant', [
            'stats' => [
                ['label' => 'Total Expected Revenue', 'value' => $this->money($expectedRevenue), 'accent' => 'primary'],
                ['label' => 'Total Fees Paid', 'value' => $this->money($feesPaid), 'accent' => 'secondary'],
                ['label' => 'Outstanding Balance', 'value' => $this->money($outstanding), 'accent' => 'accent'],
                ['label' => 'Expenses This Month', 'value' => $this->money($monthlyExpenses), 'accent' => 'primary'],
            ],
            'charts' => [
                'incomeVsExpense' => $incomeVsExpense,
                'collection' => array_values(array_filter(
                    $collection,
                    fn ($slice) => $slice['value'] > 0
                )),
            ],
            'transactions' => $recentTransactions,
            'currency' => 'TZS',
        ]);
    }

    /** Format a number as Tanzanian Shillings without decimals. */
    private function money(float $amount): string
    {
        return 'TZS '.number_format($amount, 0);
    }
}

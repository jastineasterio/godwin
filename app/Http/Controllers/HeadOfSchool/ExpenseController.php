<?php

namespace App\Http\Controllers\HeadOfSchool;

use App\Enums\ExpenseCategory;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * EXPENSES — school operating expenses. Head of School can view, add, edit
 * and void expenses. Accountant remains the primary recorder; HoS oversees
 * all expenses school-wide.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin, UserRole::Accountant]),
            403
        );

        $query = Expense::query()
            ->with('recordedBy:id,name')
            ->orderByDesc('incurred_on')
            ->orderByDesc('id');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->ofType(ExpenseCategory::tryFrom($category));
        }

        if ($from = $request->query('from')) {
            $query->whereDate('incurred_on', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('incurred_on', '<=', $to);
        }

        $expenses = $query->paginate(12)
            ->withQueryString()
            ->through(fn (Expense $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'category' => $e->category->value,
            'category_label' => $e->category->label(),
            'amount' => (float) $e->amount,
            'incurred_on' => $e->incurred_on?->toDateString(),
            'reference' => $e->reference,
            'description' => $e->description,
            'receipt_path' => $e->receipt_path,
            'recorded_by' => $e->recordedBy?->name,
            'recorded_by_id' => $e->recorded_by,
            'created_at' => $e->created_at?->toDateString(),
        ]);

        return Inertia::render('Finance/Expenses', [
            'expenses' => $expenses,
            'categories' => collect(ExpenseCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'filters' => $request->only('search', 'category', 'from', 'to'),
            'total' => (float) $query->withoutGlobalScopes()->count()
                ? (float) Expense::query()
                    ->when($request->query('category'), fn ($q, $c) => $q->ofType(ExpenseCategory::tryFrom($c)))
                    ->when($request->query('from'), fn ($q, $f) => $q->whereDate('incurred_on', '>=', $f))
                    ->when($request->query('to'), fn ($q, $t) => $q->whereDate('incurred_on', '<=', $t))
                    ->sum('amount')
                : 0,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin, UserRole::Accountant]),
            403
        );

        return Inertia::render('Finance/ExpenseCreate', [
            'categories' => collect(ExpenseCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin, UserRole::Accountant]),
            403
        );

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(ExpenseCategory::values())],
            'amount' => ['required', 'numeric', 'min:0'],
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $expense = DB::transaction(function () use ($data, $request) {
            $expense = Expense::create([
                'title' => $data['title'],
                'category' => $data['category'],
                'amount' => $data['amount'],
                'incurred_on' => $data['incurred_on'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            AuditLog::record(
                $request->user(),
                'expense.created',
                "Recorded expense \"{$expense->title}\" — {$expense->amount}",
                $expense
            );

            return $expense;
        });

        return redirect()->route('expenses.index')
            ->with('success', "Expense \"{$expense->title}\" recorded.");
    }

    public function edit(Request $request, Expense $expense): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin, UserRole::Accountant]),
            403
        );

        return Inertia::render('Finance/ExpenseEdit', [
            'expense' => [
                'id' => $expense->id,
                'title' => $expense->title,
                'category' => $expense->category->value,
                'amount' => (float) $expense->amount,
                'incurred_on' => $expense->incurred_on?->toDateString(),
                'reference' => $expense->reference,
                'description' => $expense->description,
            ],
            'categories' => collect(ExpenseCategory::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin, UserRole::Accountant]),
            403
        );

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(ExpenseCategory::values())],
            'amount' => ['required', 'numeric', 'min:0'],
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($expense, $data, $request) {
            $before = $expense->only(['title', 'category', 'amount', 'incurred_on', 'reference', 'description']);

            $expense->update([
                'title' => $data['title'],
                'category' => $data['category'],
                'amount' => $data['amount'],
                'incurred_on' => $data['incurred_on'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            AuditLog::record(
                $request->user(),
                'expense.updated',
                "Updated expense \"{$expense->title}\"",
                $expense,
                ['before' => $before, 'after' => $expense->only(['title', 'category', 'amount', 'incurred_on', 'reference', 'description'])]
            );
        });

        return redirect()->route('expenses.index')
            ->with('success', "Expense \"{$expense->title}\" updated.");
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        // Never silently delete posted financial records — void via soft delete
        // with audit trail, or deactivate. Here we soft-delete and audit.
        $name = $expense->title;
        $expense->delete();

        AuditLog::record(
            $request->user(),
            'expense.deleted',
            "Voided expense \"{$name}\"",
            $expense
        );

        return redirect()->route('expenses.index')
            ->with('success', "Expense \"{$name}\" voided.");
    }
}

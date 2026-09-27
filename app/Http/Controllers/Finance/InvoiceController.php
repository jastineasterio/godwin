<?php

namespace App\Http\Controllers\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INVOICING — generate invoices for students, auto-building line items
 * from the active fee structures that apply to the student's class.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $invoices = Invoice::query()
            ->with(['student:id,first_name,last_name,reg_no,class_id', 'term:id,name'])
            ->when($request->query('search'), fn ($q, $term) => $q->whereHas('student', fn ($s) => $s->search($term)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('issue_date')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Invoice $i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'student' => $i->student?->full_name,
                'student_id' => $i->student_id,
                'term' => $i->term?->name,
                'total_amount' => (float) $i->total_amount,
                'amount_paid' => (float) $i->amount_paid,
                'balance' => (float) $i->balance,
                'status' => $i->status->value,
                'due_date' => $i->due_date?->toDateString(),
            ]);

        return Inertia::render('Finance/Invoices', [
            'invoices' => $invoices,
            'filters' => $request->only('search', 'status'),
            'statuses' => collect(InvoiceStatus::cases())->map(fn ($s) => [
                'value' => $s->value, 'label' => $s->label(),
            ]),
            'summary' => [
                'expected' => (float) Invoice::where('status', '!=', InvoiceStatus::Void->value)->sum('total_amount'),
                'paid' => (float) Invoice::sum('amount_paid'),
                'outstanding' => max(0,
                    (float) Invoice::where('status', '!=', InvoiceStatus::Void->value)->sum('total_amount')
                    - (float) Invoice::sum('amount_paid')),
            ],
        ]);
    }

    /**
     * Generate-invoice form. Choosing a student pre-loads every ACTIVE fee
     * structure applying to them (their class fees + universal fees).
     */
    public function create(Request $request): Response
    {
        $studentId = (int) $request->query('student_id', 0);

        return Inertia::render('Finance/InvoiceCreate', [
            'students' => Student::where('status', 'active')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'reg_no', 'class_id'])
                ->map(fn (Student $s) => [
                    'id' => $s->id,
                    'name' => $s->full_name,
                    'reg_no' => $s->reg_no,
                    'class' => $s->class?->name,
                ]),
            'activeStudentId' => $studentId,
            'suggestedFees' => $studentId ? $this->feesForStudent($studentId) : [],
            'terms' => Term::orderByDesc('start_date')->get(['id', 'name', 'is_current']),
            'years' => AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']),
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code']),
        ]);
    }

    /** Fee structures that apply to a student (class-specific + universal). */
    protected function feesForStudent(int $studentId): Collection
    {
        $student = Student::with('class')->findOrFail($studentId);

        return FeeStructure::active()
            ->forClass($student->class_id)
            ->orderBy('type')
            ->get(['id', 'name', 'type', 'amount', 'frequency', 'class_id'])
            ->map(fn (FeeStructure $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'type' => $f->type->value,
                'type_label' => $f->type->label(),
                'amount' => (float) $f->amount,
                'frequency' => $f->frequency->value,
            ]);
    }

    /** Create the invoice + its line items atomically. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'term_id' => ['nullable', 'exists:terms,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_structure_id' => ['nullable', 'exists:fee_structures,id'],
            'items.*.description' => ['required', 'string', 'max:150'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $subtotal = collect($data['items'])->sum(fn ($i) => $i['amount'] * $i['quantity']);
        $discount = (float) ($data['discount'] ?? 0);

        if ($discount > $subtotal) {
            return back()->withErrors(['discount' => 'The discount cannot exceed the invoice total.']);
        }

        $student = Student::findOrFail($data['student_id']);

        $invoice = DB::transaction(function () use ($data, $student, $subtotal, $discount) {
            $invoice = Invoice::create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'student_id' => $student->id,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'term_id' => $data['term_id'] ?? null,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total_amount' => $subtotal - $discount,
                'amount_paid' => 0,
                'status' => InvoiceStatus::Unpaid,
                'notes' => $data['notes'] ?? null,
                'issued_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'fee_structure_id' => $item['fee_structure_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_amount' => $item['amount'],
                    'amount' => $item['amount'] * $item['quantity'],
                ]);
            }

            return $invoice;
        });

        AuditLog::record(auth()->user(), 'invoice.created', "Issued {$invoice->invoice_number}", $invoice);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} generated successfully.");
    }

    /** Invoice detail with items + payments (payment modal lives here). */
    public function show(Invoice $invoice): Response
    {
        $invoice->load(['student:id,first_name,last_name,reg_no,class_id', 'items', 'term:id,name', 'payments.receivedBy:id,name']);

        return Inertia::render('Finance/InvoiceShow', [
            'invoice' => [
                ...$invoice->only([
                    'id', 'invoice_number', 'student_id', 'term_id', 'issue_date',
                    'due_date', 'subtotal', 'discount', 'total_amount', 'amount_paid', 'status', 'notes',
                ]),
                'subtotal' => (float) $invoice->subtotal,
                'discount' => (float) $invoice->discount,
                'total_amount' => (float) $invoice->total_amount,
                'amount_paid' => (float) $invoice->amount_paid,
                'balance' => (float) $invoice->balance,
                'progress' => $invoice->progress,
                'status_label' => $invoice->status->label(),
                'student_name' => $invoice->student?->full_name,
                'student_reg' => $invoice->student?->reg_no,
                'term_name' => $invoice->term?->name,
            ],
            'items' => $invoice->items->map(fn (InvoiceItem $i) => [
                'id' => $i->id,
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_amount' => (float) $i->unit_amount,
                'amount' => (float) $i->amount,
            ]),
            'payments' => $invoice->payments->map(fn ($p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'amount' => (float) $p->amount,
                'method' => $p->method->value,
                'method_label' => $p->method->label(),
                'paid_at' => $p->paid_at?->format('d M Y, H:i'),
                'reference' => $p->reference,
                'received_by' => $p->receivedBy?->name,
            ]),
            'methods' => collect(PaymentMethod::cases())->map(fn ($m) => [
                'value' => $m->value, 'label' => $m->label(),
            ]),
        ]);
    }

    /** Void an invoice (never hard-delete financial records). */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->payments()->exists(), 422, 'An invoice with payments cannot be voided.');

        $invoice->update(['status' => InvoiceStatus::Void]);

        AuditLog::record(auth()->user(), 'invoice.voided', "Voided {$invoice->invoice_number}", $invoice);

        return redirect()->route('invoices.index')->with('success', "Invoice {$invoice->invoice_number} voided.");
    }

    /** Sequential, human-friendly invoice number: INV-2026-000123. */
    protected function nextInvoiceNumber(): string
    {
        $year = now()->year;

        return sprintf(
            'INV-%d-%06d',
            $year,
            Invoice::withTrashed()->whereYear('created_at', $year)->count() + 1
        );
    }
}

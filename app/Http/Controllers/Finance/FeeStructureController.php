<?php

namespace App\Http\Controllers\Finance;

use App\Enums\FeeFrequency;
use App\Enums\FeeType;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** FEE STRUCTURE CONFIGURATION — tuition, transport, feeding, daycare, … */
class FeeStructureController extends Controller
{
    public function index(Request $request): Response
    {
        $fees = FeeStructure::query()
            ->with(['schoolClass:id,name,code', 'academicYear:id,name'])
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->orderBy('type')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (FeeStructure $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'type' => $f->type->value,
                'type_label' => $f->type->label(),
                'class' => $f->schoolClass?->name ?? 'All classes',
                'amount' => (float) $f->amount,
                'frequency' => $f->frequency->value,
                'frequency_label' => $f->frequency->label(),
                'is_active' => $f->is_active,
            ]);

        return Inertia::render('Finance/FeeStructures', [
            'fees' => $fees,
            'filters' => $request->only('type'),
            'types' => collect(FeeType::cases())->map(fn ($t) => [
                'value' => $t->value, 'label' => $t->label(),
            ]),
            'frequencies' => collect(FeeFrequency::cases())->map(fn ($f) => [
                'value' => $f->value, 'label' => $f->label(),
            ]),
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code']),
            'years' => AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_current']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $fee = FeeStructure::create($this->validated($request));

        return back()->with('success', "Fee structure \"{$fee->name}\" created.");
    }

    public function update(Request $request, FeeStructure $feeStructure): RedirectResponse
    {
        $feeStructure->update($this->validated($request, $feeStructure));

        return back()->with('success', 'Fee structure updated.');
    }

    public function destroy(FeeStructure $feeStructure): RedirectResponse
    {
        // Fee definitions referenced by invoices must be kept for history
        if ($feeStructure->invoiceItems()->exists()) {
            $feeStructure->update(['is_active' => false]);

            return back()->with('success', 'Fee is in use on past invoices — it has been deactivated instead of deleted.');
        }

        $feeStructure->delete();

        return back()->with('success', 'Fee structure deleted.');
    }

    /** Shared validation (create + update). */
    protected function validated(Request $request, ?FeeStructure $fee = null): array
    {
        return $request->validate([
            'name' => [$fee ? 'sometimes' : 'required', 'string', 'max:100'],
            'type' => [$fee ? 'sometimes' : 'required', Rule::in(FeeType::values())],
            'class_id' => ['nullable', 'exists:classes,id'],   // null = applies to all classes
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'amount' => [$fee ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'frequency' => [$fee ? 'sometimes' : 'required', Rule::in(FeeFrequency::values())],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}

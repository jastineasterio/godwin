<?php

namespace App\Http\Controllers\HeadOfSchool;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SCHOOL FACILITIES & ASSETS — inventory of school property.
 * Head of School registers, tracks and reports on assets; disposals are
 * recorded (soft-delete + audit) rather than silently erased.
 */
class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $query = Asset::query()
            ->with('recordedBy:id,name')
            ->search($request->query('search'))
            ->when($request->query('category'), fn ($q, $c) => $q->category($c))
            ->when($request->query('condition'), fn ($q, $c) => $q->condition($c))
            ->when($request->query('status'), fn ($q, $s) => $q->status($s))
            ->when($request->query('location'), fn ($q, $l) => $q->where('location', 'like', "%{$l}%"))
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $assets = $query->through(fn (Asset $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'category' => $a->category,
            'asset_code' => $a->asset_code,
            'description' => $a->description,
            'quantity' => (int) $a->quantity,
            'purchase_date' => $a->purchase_date?->toDateString(),
            'purchase_price' => (float) $a->purchase_price,
            'supplier' => $a->supplier,
            'location' => $a->location,
            'condition' => $a->condition,
            'status' => $a->status,
            'image_path' => $a->image_path,
            'recorded_by' => $a->recordedBy?->name,
            'created_at' => $a->created_at?->toDateString(),
        ]);

        $summary = [
            'total_assets' => (int) Asset::count(),
            'total_quantity' => (int) Asset::sum('quantity'),
            'good_condition' => (int) Asset::where('condition', 'good')->sum('quantity'),
            'damaged' => (int) Asset::where('condition', 'damaged')->sum('quantity'),
            'maintenance' => (int) Asset::where('status', 'maintenance')->sum('quantity'),
            'missing' => (int) Asset::where('status', 'missing')->sum('quantity'),
            'recent' => (int) Asset::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        return Inertia::render('Facilities/Index', [
            'assets' => $assets,
            'summary' => $summary,
            'filters' => $request->only('search', 'category', 'condition', 'status', 'location'),
            'categories' => [
                ['value' => 'furniture', 'label' => 'Furniture'],
                ['value' => 'electronics', 'label' => 'Electronics'],
                ['value' => 'teaching', 'label' => 'Teaching Materials'],
                ['value' => 'sports', 'label' => 'Sports Equipment'],
                ['value' => 'kitchen', 'label' => 'Kitchen Equipment'],
                ['value' => 'bedding', 'label' => 'Beds & Mattresses'],
                ['value' => 'toys', 'label' => 'Toys'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'conditions' => [
                ['value' => 'new', 'label' => 'New'],
                ['value' => 'good', 'label' => 'Good'],
                ['value' => 'fair', 'label' => 'Fair'],
                ['value' => 'damaged', 'label' => 'Damaged'],
                ['value' => 'missing', 'label' => 'Missing'],
            ],
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'in_use', 'label' => 'In Use'],
                ['value' => 'maintenance', 'label' => 'Maintenance'],
                ['value' => 'disposed', 'label' => 'Disposed'],
                ['value' => 'missing', 'label' => 'Missing'],
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        return Inertia::render('Facilities/Create', [
            'categories' => [
                ['value' => 'furniture', 'label' => 'Furniture'],
                ['value' => 'electronics', 'label' => 'Electronics'],
                ['value' => 'teaching', 'label' => 'Teaching Materials'],
                ['value' => 'sports', 'label' => 'Sports Equipment'],
                ['value' => 'kitchen', 'label' => 'Kitchen Equipment'],
                ['value' => 'bedding', 'label' => 'Beds & Mattresses'],
                ['value' => 'toys', 'label' => 'Toys'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'conditions' => [
                ['value' => 'new', 'label' => 'New'],
                ['value' => 'good', 'label' => 'Good'],
                ['value' => 'fair', 'label' => 'Fair'],
                ['value' => 'damaged', 'label' => 'Damaged'],
                ['value' => 'missing', 'label' => 'Missing'],
            ],
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'in_use', 'label' => 'In Use'],
                ['value' => 'maintenance', 'label' => 'Maintenance'],
                ['value' => 'disposed', 'label' => 'Disposed'],
                ['value' => 'missing', 'label' => 'Missing'],
            ],
            'users' => User::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:60'],
            'asset_code' => ['nullable', 'string', 'max:60', Rule::unique('assets', 'asset_code')],
            'description' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:1'],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'condition' => ['required', Rule::in(['new', 'good', 'fair', 'damaged', 'missing'])],
            'status' => ['required', Rule::in(['active', 'in_use', 'maintenance', 'disposed', 'missing'])],
            'image_path' => ['nullable', 'string', 'max:255'],
            'recorded_by' => ['nullable', 'exists:users,id'],
        ]);

        $asset = DB::transaction(function () use ($data, $request) {
            $asset = Asset::create([
                'name' => $data['name'],
                'category' => $data['category'],
                'asset_code' => $data['asset_code'] ?? null,
                'description' => $data['description'] ?? null,
                'quantity' => $data['quantity'],
                'purchase_date' => $data['purchase_date'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'supplier' => $data['supplier'] ?? null,
                'location' => $data['location'] ?? null,
                'condition' => $data['condition'],
                'status' => $data['status'],
                'image_path' => $data['image_path'] ?? null,
                'recorded_by' => $data['recorded_by'] ?? $request->user()->id,
            ]);

            AuditLog::record(
                $request->user(),
                'asset.created',
                "Registered asset \"{$asset->name}\"",
                $asset
            );

            return $asset;
        });

        return redirect()->route('facilities.index')
            ->with('success', "Asset \"{$asset->name}\" registered.");
    }

    public function show(Request $request, Asset $asset): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        return Inertia::render('Facilities/Show', [
            'asset' => [
                ...$asset->only([
                    'id', 'name', 'category', 'asset_code', 'description', 'quantity',
                    'purchase_date', 'purchase_price', 'supplier', 'location',
                    'condition', 'status', 'image_path',
                ]),
                'recorded_by' => $asset->recordedBy?->name,
                'created_at' => $asset->created_at?->toDateString(),
            ],
        ]);
    }

    public function edit(Request $request, Asset $asset): Response
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        return Inertia::render('Facilities/Edit', [
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'category' => $asset->category,
                'asset_code' => $asset->asset_code,
                'description' => $asset->description,
                'quantity' => (int) $asset->quantity,
                'purchase_date' => $asset->purchase_date?->toDateString(),
                'purchase_price' => (float) $asset->purchase_price,
                'supplier' => $asset->supplier,
                'location' => $asset->location,
                'condition' => $asset->condition,
                'status' => $asset->status,
                'image_path' => $asset->image_path,
            ],
            'categories' => [
                ['value' => 'furniture', 'label' => 'Furniture'],
                ['value' => 'electronics', 'label' => 'Electronics'],
                ['value' => 'teaching', 'label' => 'Teaching Materials'],
                ['value' => 'sports', 'label' => 'Sports Equipment'],
                ['value' => 'kitchen', 'label' => 'Kitchen Equipment'],
                ['value' => 'bedding', 'label' => 'Beds & Mattresses'],
                ['value' => 'toys', 'label' => 'Toys'],
                ['value' => 'other', 'label' => 'Other'],
            ],
            'conditions' => [
                ['value' => 'new', 'label' => 'New'],
                ['value' => 'good', 'label' => 'Good'],
                ['value' => 'fair', 'label' => 'Fair'],
                ['value' => 'damaged', 'label' => 'Damaged'],
                ['value' => 'missing', 'label' => 'Missing'],
            ],
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'in_use', 'label' => 'In Use'],
                ['value' => 'maintenance', 'label' => 'Maintenance'],
                ['value' => 'disposed', 'label' => 'Disposed'],
                ['value' => 'missing', 'label' => 'Missing'],
            ],
        ]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:60'],
            'asset_code' => ['nullable', 'string', 'max:60', Rule::unique('assets', 'asset_code')->ignore($asset->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:1'],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'condition' => ['required', Rule::in(['new', 'good', 'fair', 'damaged', 'missing'])],
            'status' => ['required', Rule::in(['active', 'in_use', 'maintenance', 'disposed', 'missing'])],
            'image_path' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($asset, $data, $request) {
            $before = $asset->only([
                'name', 'category', 'asset_code', 'quantity', 'purchase_date',
                'purchase_price', 'condition', 'status', 'location',
            ]);

            $asset->update([
                'name' => $data['name'],
                'category' => $data['category'],
                'asset_code' => $data['asset_code'] ?? null,
                'description' => $data['description'] ?? null,
                'quantity' => $data['quantity'],
                'purchase_date' => $data['purchase_date'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'supplier' => $data['supplier'] ?? null,
                'location' => $data['location'] ?? null,
                'condition' => $data['condition'],
                'status' => $data['status'],
                'image_path' => $data['image_path'] ?? null,
            ]);

            AuditLog::record(
                $request->user(),
                'asset.updated',
                "Updated asset \"{$asset->name}\"",
                $asset,
                ['before' => $before, 'after' => $asset->only([
                    'name', 'category', 'asset_code', 'quantity', 'purchase_date',
                    'purchase_price', 'condition', 'status', 'location',
                ])]
            );
        });

        return redirect()->route('facilities.index')
            ->with('success', "Asset \"{$asset->name}\" updated.");
    }

    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless(
            $request->user()->hasAnyRole([UserRole::HeadOfSchool, UserRole::Admin]),
            403
        );

        // Soft-delete: disposal history is preserved (audit + audit trail).
        $name = $asset->name;
        $asset->delete();

        AuditLog::record(
            $request->user(),
            'asset.deleted',
            "Disposed asset \"{$name}\"",
            $asset
        );

        return redirect()->route('facilities.index')
            ->with('success', "Asset \"{$name}\" disposed.");
    }
}

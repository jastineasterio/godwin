<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\SchoolApplication;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public online application ("Apply Now") — the multi-step wizard is a
 * client-side concern; the server receives ONE validated POST.
 */
class ApplicationController extends Controller
{
    /** Render the multi-step registration form. */
    public function create(): Response
    {
        return Inertia::render('Public/Apply', [
            'classes' => SchoolClass::query()
                ->active()
                ->ordered()
                ->get(['id', 'name', 'code', 'level', 'capacity']),
        ]);
    }

    /** Persist a prospective pupil's application. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Step 1 — parent/guardian -----------------------------------
            'parent_name' => ['required', 'string', 'max:150'],
            'parent_email' => ['nullable', 'email', 'max:150'],
            'parent_phone' => ['required', 'string', 'max:20'],
            'relationship' => ['required', 'in:father,mother,guardian,other'],
            'address' => ['nullable', 'string', 'max:500'],

            // Step 2 — child ---------------------------------------------
            'child_first_name' => ['required', 'string', 'max:100'],
            'child_last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female'],
            'dob' => ['nullable', 'date', 'before:today'],
            'class_id' => ['required', 'exists:classes,id'],

            // Step 3 — extra info ----------------------------------------
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $application = SchoolApplication::create([
            ...$data,
            'reference_no' => $this->nextReference(),
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('apply.create')
            ->with('success', "Application received! Your reference number is {$application->reference_no}. We will contact you soon.");
    }

    /** Sequential human-friendly reference: APP-2026-0001. */
    protected function nextReference(): string
    {
        $year = now()->year;
        $count = SchoolApplication::withTrashed()->whereYear('created_at', $year)->count() + 1;

        return sprintf('APP-%d-%04d', $year, $count);
    }
}

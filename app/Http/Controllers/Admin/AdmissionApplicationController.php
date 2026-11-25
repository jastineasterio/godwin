<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\SchoolApplication;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Sms\AdmissionSmsNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ONLINE ADMISSION APPLICATION MANAGEMENT (Admin & Head of School).
 *
 * Staff can list, search, filter, view and review applications submitted
 * through the public "Apply Now" form. Acceptance/rejection trigger
 * Kiswahili SMS notifications (when enabled) directly and synchronously
 * through the active DARSMS provider — no queues, no background jobs.
 *
 * Accepting an application does NOT auto-create a student record — the
 * existing enrollment workflow is preserved.
 */
class AdmissionApplicationController extends Controller
{
    protected AdmissionSmsNotifier $admissionSms;

    public function __construct(AdmissionSmsNotifier $admissionSms)
    {
        $this->admissionSms = $admissionSms;
    }

    /** Paginated list of applications with search + status filter. */
    public function index(Request $request): Response
    {
        $query = SchoolApplication::query()
            ->with('reviewedBy:id,name')
            ->orderBy('submitted_at', 'desc')
            ->orderBy('created_at', 'desc');
        $search = trim((string) $request->string('search'));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('parent_name', 'like', "%{$search}%")
                    ->orWhere('child_first_name', 'like', "%{$search}%")
                    ->orWhere('child_last_name', 'like', "%{$search}%");
            });
        }
        $status = trim((string) $request->string('status'));

        if ($status !== '' && in_array($status, ApplicationStatus::values(), true)) {
            $query->where('status', $status);
        }

        $applications = $query->paginate(12)->withQueryString();

        return Inertia::render('Admin/Admissions/Index', [
            'applications' => $applications->through(fn (SchoolApplication $a) => $this->applicationArray($a)),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => collect(ApplicationStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'badge' => $s->badgeClasses(),
            ]),
        ]);
    }

    /** Full application detail (read-only review notes visible to staff). */
    public function show(SchoolApplication $application): Response
    {
        $application->load('reviewedBy:id,name', 'schoolClass:id,name,code,level');

        return Inertia::render('Admin/Admissions/Show', [
            'application' => [
                'id' => $application->id,
                'reference_no' => $application->reference_no,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'badge_classes' => $application->status->badgeClasses(),
                'parent_name' => $application->parent_name,
                'parent_email' => $application->parent_email,
                'parent_phone' => $application->parent_phone,
                'relationship' => $application->relationship->value,
                'address' => $application->address,
                'child_first_name' => $application->child_first_name,
                'child_last_name' => $application->child_last_name,
                'child_name' => $application->child_name,
                'gender' => $application->gender->value,
                'gender_label' => $application->gender->label(),
                'dob' => $application->dob?->toDateString(),
                'class' => $application->schoolClass?->name,
                'class_code' => $application->schoolClass?->code,
                'class_level' => $application->schoolClass?->level?->value,
                'message' => $application->message,
                'submitted_at' => $application->submitted_at?->toDateTimeString(),
                'reviewed_by' => $application->reviewedBy?->name,
                'reviewed_by_id' => $application->reviewed_by,
                'review_notes' => $application->review_notes,
                'created_at' => $application->created_at->toDateTimeString(),
            ],
            'classes' => SchoolClass::active()->ordered()->get(['id', 'name', 'code']),
        ]);
    }

    /** Mark an application as under review with optional internal notes. */
    public function review(Request $request, SchoolApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($application, $validated, $request) {
            $this->transitionStatus($application, ApplicationStatus::UnderReview, $request->user(), $validated['review_notes'] ?? null);
        });

        return back()->with('success', 'Application marked as under review.');
    }

    /** Accept an application and (when enabled) send an acceptance SMS directly. */
    public function accept(Request $request, SchoolApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $accepted = DB::transaction(function () use ($application, $validated, $request) {
            return $this->transitionStatus($application, ApplicationStatus::Accepted, $request->user(), $validated['review_notes'] ?? null);
        });

        // Send the acceptance SMS directly (synchronously) after the
        // acceptance transaction has committed and only when the transition
        // actually happened. SMS delivery never blocks or rolls back the
        // approval; provider/network failures are caught by the notifier and
        // logged. No queue job is dispatched.
        if ($accepted) {
            $this->admissionSms->notify($application, 'accepted');
        }

        return back()->with('success', 'Application accepted.');
    }

    /** Reject an application with optional internal notes (no SMS). */
    public function reject(Request $request, SchoolApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($application, $validated, $request) {
            $this->transitionStatus($application, ApplicationStatus::Rejected, $request->user(), $validated['review_notes'] ?? null);
        });

        return back()->with('success', 'Application rejected.');
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    /** Validate and persist a server-side status transition. Returns true if the transition happened. */
    protected function transitionStatus(
        SchoolApplication $application,
        ApplicationStatus $newStatus,
        ?User $reviewer,
        ?string $reviewNotes
    ): bool {
        $current = $application->status;

        // Terminal states cannot be transitioned further.
        if ($current === ApplicationStatus::Accepted || $current === ApplicationStatus::Rejected) {
            return false;
        }

        // Enrolled is the existing enrollment workflow — staff here do not
        // convert via this controller; preserve it untouched.
        if ($current === ApplicationStatus::Enrolled) {
            return false;
        }

        $allowedFrom = match ($current) {
            ApplicationStatus::Submitted => [
                ApplicationStatus::UnderReview,
                ApplicationStatus::Accepted,
                ApplicationStatus::Rejected,
            ],
            ApplicationStatus::UnderReview => [
                ApplicationStatus::Accepted,
                ApplicationStatus::Rejected,
            ],
            default => [],
        };

        if (! in_array($newStatus, $allowedFrom, true)) {
            return false;
        }

        // Never overwrite review information already recorded.
        if ($application->reviewed_by && $application->review_notes) {
            return false;
        }

        $application->update([
            'status' => $newStatus->value,
            'reviewed_by' => $reviewer?->id,
            'review_notes' => $reviewNotes,
        ]);

        return true;
    }

    /** Shape a single application for the Inertia list payload. */
    protected function applicationArray(SchoolApplication $a): array
    {
        return [
            'id' => $a->id,
            'reference_no' => $a->reference_no,
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'badge_classes' => $a->status->badgeClasses(),
            'parent_name' => $a->parent_name,
            'parent_phone' => $a->parent_phone,
            'child_name' => $a->child_name,
            'gender' => $a->gender->value,
            'gender_label' => $a->gender->label(),
            'dob' => $a->dob?->toDateString(),
            'class' => $a->schoolClass?->name,
            'class_code' => $a->schoolClass?->code,
            'submitted_at' => $a->submitted_at?->toDateTimeString(),
            'reviewed_by' => $a->reviewedBy?->name,
            'review_notes' => $a->review_notes,
        ];
    }
}

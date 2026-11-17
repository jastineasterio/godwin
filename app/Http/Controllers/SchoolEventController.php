<?php

namespace App\Http\Controllers;

use App\Enums\Audience;
use App\Enums\UserRole;
use App\Models\SchoolEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolEventController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = SchoolEvent::query()->with('creator:id,name');

        if ($user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool, UserRole::SeniorPastor])) {
            // Management sees all events (published and drafts)
        } elseif ($user->isStaff() && !$user->isAdmin() && !$user->hasAnyRole([UserRole::HeadOfSchool, UserRole::SeniorPastor])) {
            // Other staff (Teacher, Accountant): published events relevant to them
            $query->published()->whereIn('audience', ['everyone', 'staff', 'teachers']);
        } else {
            // Parent: published events relevant to families
            $query->published()->whereIn('audience', ['everyone', 'parents']);
        }

        $events = $query->orderByDesc('start_date')->paginate(15)->withQueryString()->through(fn (SchoolEvent $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'description' => $e->description,
            'start_date' => $e->start_date?->format('Y-m-d'),
            'end_date' => $e->end_date?->format('Y-m-d'),
            'start_time' => $e->start_time,
            'end_time' => $e->end_time,
            'location' => $e->location,
            'color' => $e->color,
            'audience' => $e->audience->value,
            'audience_label' => $e->audience->label(),
            'is_published' => $e->is_published,
            'creator' => $e->creator?->name ?? '—',
            'created_at' => $e->created_at?->diffForHumans(),
            'is_upcoming' => $e->start_date && $e->start_date->isFuture(),
        ]);

        $canManage = $user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool, UserRole::SeniorPastor]);

        return Inertia::render('Events/Index', [
            'events' => $events,
            'canManage' => $canManage,
            'audiences' => collect(Audience::cases())->map(fn ($a) => [
                'value' => $a->value,
                'label' => $a->label(),
            ])->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeManage($request->user());

        return Inertia::render('Events/Create', [
            'audiences' => collect(Audience::cases())->map(fn ($a) => [
                'value' => $a->value,
                'label' => $a->label(),
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request->user());

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:180'],
            'color' => ['nullable', 'string', 'max:20'],
            'audience' => ['required', Rule::in(Audience::values())],
            'is_published' => ['nullable', 'boolean'],
        ]);

        if ($validated['start_time'] && $validated['end_time'] && $validated['start_time'] >= $validated['end_time']) {
            return back()->withErrors(['end_time' => 'End time must be after start time.'])->withInput();
        }

        $event = SchoolEvent::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'is_published' => (bool) ($validated['is_published'] ?? true),
        ]);

        return redirect()->route('events.index')->with('success', 'Event created successfully.');
    }

    public function edit(Request $request, SchoolEvent $event): Response
    {
        $this->authorizeManage($request->user());

        return Inertia::render('Events/Edit', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'start_date' => $event->start_date?->format('Y-m-d'),
                'end_date' => $event->end_date?->format('Y-m-d'),
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'location' => $event->location,
                'color' => $event->color,
                'audience' => $event->audience->value,
                'is_published' => $event->is_published,
            ],
            'audiences' => collect(Audience::cases())->map(fn ($a) => [
                'value' => $a->value,
                'label' => $a->label(),
            ])->all(),
        ]);
    }

    public function update(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->authorizeManage($request->user());

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:180'],
            'color' => ['nullable', 'string', 'max:20'],
            'audience' => ['required', Rule::in(Audience::values())],
            'is_published' => ['nullable', 'boolean'],
        ]);

        if ($validated['start_time'] && $validated['end_time'] && $validated['start_time'] >= $validated['end_time']) {
            return back()->withErrors(['end_time' => 'End time must be after start time.'])->withInput();
        }

        $event->update([
            ...$validated,
            'is_published' => (bool) ($validated['is_published'] ?? $event->is_published),
        ]);

        return redirect()->route('events.index')->with('success', 'Event updated successfully.');
    }

    public function destroy(Request $request, SchoolEvent $event): RedirectResponse
    {
        $this->authorizeManage($request->user());

        $event->delete();

        return back()->with('success', 'Event removed successfully.');
    }

    /* ---------------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------------- */

    private function authorizeManage(User $user): void
    {
        abort_unless(
            $user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool, UserRole::SeniorPastor]),
            403,
            'You do not have permission to manage events.'
        );
    }
}

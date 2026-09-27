<?php

namespace App\Http\Controllers\Comms;

use App\Enums\AnnouncementType;
use App\Enums\Audience;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\SchoolAnnouncement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ANNOUNCEMENTS & BROADCASTS — news, admissions notices and pastoral
 * guidance. Publishing notifies the selected audience in-app.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $announcements = Announcement::query()
            ->with('author:id,name,role')
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('audience'), fn ($q, $a) => $q->where('audience', $a))
            ->when($request->query('search'), fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('title', 'like', "%{$term}%")
                    ->orWhere('excerpt', 'like', "%{$term}%")
            ))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'audience' => $a->audience->value,
                'audience_label' => $a->audience->label(),
                'excerpt' => $a->excerpt,
                'body' => $a->body,
                'is_published' => $a->is_published,
                'is_pinned' => $a->is_pinned,
                'author' => $a->author?->name,
                'published_at' => $a->published_at?->format('d M Y'),
                'created_at' => $a->created_at->diffForHumans(),
            ]);

        return Inertia::render('Comms/Announcements', [
            'announcements' => $announcements,
            'filters' => $request->only('search', 'type', 'audience'),
            'types' => collect(AnnouncementType::cases())->map(fn ($t) => [
                'value' => $t->value, 'label' => $t->label(),
            ]),
            'audiences' => collect(Audience::cases())->map(fn ($a) => [
                'value' => $a->value, 'label' => $a->label(),
            ]),
            'contactMessages' => ContactMessage::unread()->latest()->take(5)
                ->get(['id', 'name', 'email', 'message', 'created_at'])
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'email' => $m->email,
                    'message' => Str::limit($m->message, 90),
                    'created_at' => $m->created_at->diffForHumans(),
                ]),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Write actions */
    /* ------------------------------------------------------------------ */

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $publishing = (bool) ($data['is_published'] ?? false);

        $announcement = Announcement::create([
            ...$data,
            'author_id' => $request->user()->id,
            'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(5)),
            'published_at' => $publishing ? now() : null,
        ]);

        if ($publishing) {
            $this->notifyAudience($announcement);
        }

        AuditLog::record($request->user(), 'announcement.created', "Saved \"{$announcement->title}\"", $announcement);

        return back()->with('success', "Announcement \"{$announcement->title}\" saved.");
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validated($request);
        $wasPublished = $announcement->is_published;

        $announcement->update($data);

        // First publish (or republish) re-notifies the audience
        if ($announcement->is_published && ! $wasPublished) {
            $announcement->forceFill(['published_at' => now()])->saveQuietly();
            $this->notifyAudience($announcement);
        }

        return back()->with('success', 'Announcement updated.');
    }

    /** Publish / unpublish toggle. */
    public function togglePublish(Announcement $announcement): RedirectResponse
    {
        $publishing = ! $announcement->is_published;

        $announcement->update([
            'is_published' => $publishing,
            'published_at' => $publishing ? now() : $announcement->published_at,
        ]);

        if ($publishing) {
            $this->notifyAudience($announcement);
        }

        return back()->with('success', $publishing ? 'Announcement published.' : 'Announcement unpublished.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'Announcement removed.');
    }

    /* ------------------------------------------------------------------ */

    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(AnnouncementType::values())],
            'audience' => ['required', Rule::in(Audience::values())],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:20000'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);
    }

    /** Send an in-app notification to everyone in the chosen audience. */
    protected function notifyAudience(Announcement $announcement): void
    {
        $recipients = match ($announcement->audience) {
            Audience::Parents => User::role(UserRole::Parent)->active()->get(),
            Audience::Teachers => User::role(UserRole::Teacher)->active()->get(),
            Audience::Staff => User::staff()->active()->get(),
            default => User::active()->get(),
        };

        foreach ($recipients as $user) {
            $user->notify(new SchoolAnnouncement($announcement));
        }
    }
}

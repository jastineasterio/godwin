<?php

namespace App\Http\Controllers\Comms;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DIRECT MESSAGING — parent ↔ teacher conversations.
 *
 * A parent may message the teachers of their own children; a teacher may
 * message the parents of their own students; leadership may message anyone.
 */
class MessageController extends Controller
{
    /** Inbox: every conversation the signed-in user takes part in. */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['participants' => fn ($q) => $q->where('users.id', '!=', $user->id)])
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'other' => $c->participants->first()?->only(['id', 'name', 'role']) ?? null,
                'last_message' => $c->messages->first()?->only(['body', 'created_at']) ?? null,
                'unread' => $c->unreadCountFor($user->id),
                'updated_at' => $c->last_message_at?->diffForHumans() ?? $c->updated_at->diffForHumans(),
            ]);

        return Inertia::render('Comms/Messages', [
            'conversations' => $conversations,
            'active' => $request->query('conversation')
                ? $this->thread($request, (int) $request->query('conversation'))
                : null,
            'contacts' => $this->allowedContacts($user),
        ]);
    }

    /** Start (or re-use) a thread and send the opening message. */
    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $partner = User::findOrFail($data['user_id']);

        abort_unless($this->canMessage($request->user(), $partner), 403, 'You cannot message this user.');

        $conversation = DB::transaction(function () use ($request, $data, $partner) {
            // Re-use an existing direct thread with the same person
            $conversation = Conversation::where('type', 'direct')
                ->whereHas('participants', fn ($q) => $q->where('users.id', $request->user()->id))
                ->whereHas('participants', fn ($q) => $q->where('users.id', $partner->id))
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'type' => 'direct',
                    'subject' => $data['subject'] ?? null,
                ]);

                $conversation->participants()->attach([$request->user()->id, $partner->id]);
            }

            $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $data['body'],
            ]);

            $conversation->update(['last_message_at' => now()]);

            return $conversation;
        });

        $partner->notify(new NewMessage($conversation, $request->user(), $data['body']));

        return redirect()->route('messages.index', ['conversation' => $conversation->id])
            ->with('success', 'Message sent.');
    }

    /** Reply inside a thread the user participates in. */
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->authorizeParticipant($request, $conversation);

        $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        $conversation->participants()->updateExistingPivot($request->user()->id, [
            'last_read_at' => now(),
        ]);

        foreach ($conversation->participants()->where('users.id', '!=', $request->user()->id)->get() as $recipient) {
            $recipient->notify(new NewMessage($conversation, $request->user(), $data['body']));
        }

        return back()->with('success', 'Reply sent.');
    }

    /* ------------------------------------------------------------------ */
    /* Internals */
    /* ------------------------------------------------------------------ */

    /** Serialise one thread (counterpart + last 100 messages). */
    protected function thread(Request $request, int $conversationId): ?array
    {
        $user = $request->user();

        $conversation = Conversation::with([
            'participants' => fn ($q) => $q->where('users.id', '!=', $user->id),
            'messages' => fn ($q) => $q->orderBy('created_at')->take(100),
        ])->find($conversationId);

        if (! $conversation) {
            return null;
        }

        $this->authorizeParticipant($request, $conversation);

        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return [
            'id' => $conversation->id,
            'other' => $conversation->participants->first()?->only(['id', 'name', 'role']),
            'messages' => $conversation->messages->map(fn (Message $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'is_mine' => $m->sender_id === $user->id,
                'time' => $m->created_at->format('d M, H:i'),
            ]),
        ];
    }

    /** The user must be a participant of the conversation. */
    protected function authorizeParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless(
            $conversation->participants()->where('users.id', $request->user()->id)->exists(),
            403,
            'You are not part of this conversation.'
        );
    }

    /**
     * Who the signed-in user may start a conversation with.
     *
     * Parents → teachers of their children;
     * Teachers → parents of their students;
     * Leadership → everyone.
     */
    protected function allowedContacts(User $user)
    {
        if ($user->hasAnyRole([UserRole::Admin, UserRole::HeadOfSchool, UserRole::SeniorPastor])) {
            return User::where('id', '!=', $user->id)->active()->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role->label()]);
        }

        if ($user->isParent()) {
            $studentIds = $user->students()->pluck('students.id');

            $teacherIds = Student::whereIn('id', $studentIds)
                ->with('class:id,teacher_id')
                ->get()
                ->pluck('class.teacher_id')
                ->filter()
                ->unique();

            return User::whereIn('id', $teacherIds)->active()->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role->label()]);
        }

        // Teacher → parents of students in their classes
        $classIds = $user->classesTaught()->pluck('classes.id');

        $parentIds = Student::whereIn('class_id', $classIds)
            ->with('parents:id')
            ->get()
            ->flatMap(fn (Student $s) => $s->parents->pluck('id'))
            ->unique();

        return User::whereIn('id', $parentIds)->where('id', '!=', $user->id)->active()
            ->orderBy('name')->get(['id', 'name', 'role'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role->label()]);
    }

    /** Server-side permission check for starting a thread. */
    protected function canMessage(User $user, User $partner): bool
    {
        return $this->allowedContacts($user)->contains('id', $partner->id);
    }
}

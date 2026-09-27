<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A messaging room (teacher <-> parent direct message, or a group thread).
 * Participants live in `conversation_user` with a last_read_at cursor.
 */
class Conversation extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'subject',
        'type',
        'last_message_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /** All users taking part in this conversation. */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /** Chat messages, newest first (use for conversation previews). */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Most recent message of this thread. */
    public function latestMessage()
    {
        return $this->messages()->latest()->first();
    }

    /** Messages sent after a given user's last_read_at (unread badge count). */
    public function unreadCountFor(int $userId): int
    {
        $lastRead = $this->participants()
            ->where('users.id', $userId)
            ->first()?->pivot?->last_read_at;

        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
            ->count();
    }
}

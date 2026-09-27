<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** In-app notification for a new direct message. */
class NewMessage extends Notification
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public User $sender,
        public string $preview
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'sender' => $this->sender->name,
            'preview' => Str::limit($this->preview, 80),
            'url' => '/messages?conversation='.$this->conversation->id,
        ];
    }
}

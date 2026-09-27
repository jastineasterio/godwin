<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** In-app notification (bell icon) for announcements and broadcasts. */
class SchoolAnnouncement extends Notification
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'excerpt' => $this->announcement->excerpt,
            'type' => $this->announcement->type->value,
            'author' => $this->announcement->author?->name,
            'url' => '/announcements',
        ];
    }

    /** Optional email copy for leadership broadcasts. */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('God-Win School: '.$this->announcement->title)
            ->greeting('Hello '.$notifiable->name)
            ->line($this->announcement->excerpt ?: strip_tags($this->announcement->body))
            ->line('Please open the school portal to read the full announcement.');
    }
}

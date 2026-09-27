<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Message received through the public website "Contact Us" form. */
class ContactMessage extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'is_read',
        'admin_reply',
        'replied_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    /** Unread inbox for the Administrator dashboard. */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }
}

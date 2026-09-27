<?php

namespace App\Enums;

/** Broadcast audience — decides who sees an announcement/event in-app. */
enum Audience: string
{
    case Everyone = 'everyone';
    case Parents = 'parents';
    case Staff = 'staff';
    case Teachers = 'teachers';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone',
            self::Parents => 'Parents',
            self::Staff => 'All Staff',
            self::Teachers => 'Teachers',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

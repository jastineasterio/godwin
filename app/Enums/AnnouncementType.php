<?php

namespace App\Enums;

/** Content type for announcements / news / events on the public site. */
enum AnnouncementType: string
{
    case News = 'news';
    case Event = 'event';
    case Announcement = 'announcement';
    case Admission = 'admission';

    public function label(): string
    {
        return match ($this) {
            self::News => 'News',
            self::Event => 'Event',
            self::Announcement => 'Announcement',
            self::Admission => 'Admissions',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

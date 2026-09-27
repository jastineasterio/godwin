<?php

namespace App\Enums;

/** Weekdays used by the timetable (Monday–Saturday, TZ school week). */
enum TimetableDay: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Monday',
            self::Tuesday => 'Tuesday',
            self::Wednesday => 'Wednesday',
            self::Thursday => 'Thursday',
            self::Friday => 'Friday',
            self::Saturday => 'Saturday',
        };
    }

    /** Short form for calendar chips: Mon, Tue, ... */
    public function short(): string
    {
        return substr($this->label(), 0, 3);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

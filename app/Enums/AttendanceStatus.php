<?php

namespace App\Enums;

/** Daily attendance mark for a student. */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::Excused => 'Excused',
        };
    }

    /** Tailwind colour classes used by the quick mobile attendance toggles. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Present => 'bg-emerald-100 text-emerald-700',
            self::Absent => 'bg-rose-100 text-rose-700',
            self::Late => 'bg-amber-100 text-amber-700',
            self::Excused => 'bg-sky-100 text-sky-700',
        };
    }

    /** Counts towards the attendance-rate percentage. */
    public function countsAsPresent(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

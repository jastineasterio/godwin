<?php

namespace App\Enums;

/** Enrollment lifecycle of a student record. */
enum StudentStatus: string
{
    case Active = 'active';
    case Transferred = 'transferred';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Transferred => 'Transferred',
            self::Graduated => 'Graduated',
            self::Withdrawn => 'Withdrawn',
            self::Inactive => 'Inactive',
        };
    }

    /** Currently enrolled students (default dashboard scope). */
    public function isEnrolled(): bool
    {
        return $this === self::Active;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

/** Academic level of a class (classes.level). */
enum ClassLevel: string
{
    case Daycare = 'daycare';
    case Nursery = 'nursery';
    case KG1 = 'kg1';
    case KG2 = 'kg2';
    case Primary = 'primary';

    public function label(): string
    {
        return match ($this) {
            self::Daycare => 'Daycare',
            self::Nursery => 'Nursery',
            self::KG1 => 'KG1',
            self::KG2 => 'KG2',
            self::Primary => 'Primary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

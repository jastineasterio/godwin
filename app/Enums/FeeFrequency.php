<?php

namespace App\Enums;

/** How often a fee structure is billed (fee_structures.frequency). */
enum FeeFrequency: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Termly = 'termly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One time',
            self::Monthly => 'Monthly',
            self::Termly => 'Termly',
            self::Yearly => 'Yearly',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

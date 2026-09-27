<?php

namespace App\Enums;

/** Fee categories (fee_structures.type). */
enum FeeType: string
{
    case Tuition = 'tuition';
    case Transport = 'transport';
    case Feeding = 'feeding';
    case Daycare = 'daycare';
    case Uniform = 'uniform';
    case Exam = 'exam';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Tuition => 'Tuition',
            self::Transport => 'Transport',
            self::Feeding => 'Feeding',
            self::Daycare => 'Daycare',
            self::Uniform => 'Uniform',
            self::Exam => 'Examination',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

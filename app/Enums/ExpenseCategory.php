<?php

namespace App\Enums;

/** Expense categories (expenses.category). */
enum ExpenseCategory: string
{
    case Salaries = 'salaries';
    case Utilities = 'utilities';
    case Maintenance = 'maintenance';
    case Supplies = 'supplies';
    case Food = 'food';
    case Transport = 'transport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Salaries => 'Salaries & Wages',
            self::Utilities => 'Utilities',
            self::Maintenance => 'Maintenance',
            self::Supplies => 'Supplies',
            self::Food => 'Food & Feeding',
            self::Transport => 'Transport',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

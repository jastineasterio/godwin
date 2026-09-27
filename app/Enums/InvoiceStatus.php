<?php

namespace App\Enums;

/** Invoice payment status (invoices.status). */
enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Partial => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Void => 'Void',
        };
    }

    /** Tailwind badge classes for invoice status chips. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-rose-100 text-rose-700',
            self::Partial => 'bg-amber-100 text-amber-700',
            self::Paid => 'bg-emerald-100 text-emerald-700',
            self::Overdue => 'bg-rose-100 text-rose-700',
            self::Void => 'bg-slate-100 text-slate-600',
        };
    }

    /** Amounts still owed to the school. */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::Unpaid, self::Partial, self::Overdue], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

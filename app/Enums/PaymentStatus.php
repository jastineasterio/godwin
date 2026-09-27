<?php

namespace App\Enums;

/** Payment lifecycle (payments.status). */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
        };
    }

    /** Only completed payments count toward revenue & invoice balances. */
    public function countsTowardRevenue(): bool
    {
        return $this === self::Completed;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

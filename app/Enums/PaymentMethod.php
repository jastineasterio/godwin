<?php

namespace App\Enums;

/** Accepted payment methods (payments.method). */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::MobileMoney => 'Mobile Money',
            self::Card => 'Card',
            self::Cheque => 'Cheque',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

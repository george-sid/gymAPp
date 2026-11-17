<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Iris = 'iris';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Μετρητά',
            self::Card => 'Κάρτα',
            self::Iris => 'IRIS',
            self::BankTransfer => 'Τραπεζική μεταφορά',
        };
    }
}

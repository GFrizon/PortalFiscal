<?php

namespace App\Enums;

enum InvoicePaymentMethod: string
{
    case Anticipated = 'anticipated';
    case Deposit = 'deposit';
    case Boleto = 'boleto';

    public function label(): string
    {
        return match ($this) {
            self::Anticipated => 'Antecipado',
            self::Deposit => 'Deposito',
            self::Boleto => 'Boleto',
        };
    }

    public function requiresInstallments(): bool
    {
        return $this !== self::Anticipated;
    }
}

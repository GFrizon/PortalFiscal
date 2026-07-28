<?php

namespace App\Enums;

enum InvoiceDocumentType: string
{
    case Nf = 'nf';
    case Cte = 'cte';

    public function label(): string
    {
        return match ($this) {
            self::Nf => 'NF',
            self::Cte => 'CTE',
        };
    }

    public function referenceLabel(): string
    {
        return match ($this) {
            self::Nf => 'Ordem de compra',
            self::Cte => 'Nota Fiscal',
        };
    }
}

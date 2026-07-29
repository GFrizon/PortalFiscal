<?php

namespace App\Enums;

enum InvoiceDocumentType: string
{
    case Nf = 'nf';
    case NfWithoutPurchaseOrder = 'nf_no_oc';
    case Cte = 'cte';

    public function label(): string
    {
        return match ($this) {
            self::Nf => 'NF',
            self::NfWithoutPurchaseOrder => 'NF sem OC',
            self::Cte => 'CTE',
        };
    }

    public function referenceLabel(): string
    {
        return match ($this) {
            self::Nf => 'Ordem de compra',
            self::NfWithoutPurchaseOrder => 'Ordem de compra',
            self::Cte => 'Nota Fiscal',
        };
    }
}

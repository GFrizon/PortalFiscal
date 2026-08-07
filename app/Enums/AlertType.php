<?php

namespace App\Enums;

enum AlertType: string
{
    case PurchaseOrderNotFound = 'purchase_order_not_found';
    case PurchaseOrderLookupFailed = 'purchase_order_lookup_failed';
    case PurchaseOrderCancelled = 'purchase_order_cancelled';
    case CnpjMismatch = 'cnpj_mismatch';
    case BusinessUnitNotIdentified = 'business_unit_not_identified';
    case PdfReadError = 'pdf_read_error';
    case InvoiceNumberNotIdentified = 'invoice_number_not_identified';
    case DuplicatePdf = 'duplicate_pdf';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseOrderNotFound => 'Ordem nao encontrada',
            self::PurchaseOrderLookupFailed => 'Erro na consulta da ordem',
            self::PurchaseOrderCancelled => 'Ordem cancelada',
            self::CnpjMismatch => 'CNPJ divergente',
            self::BusinessUnitNotIdentified => 'Unidade nao identificada',
            self::PdfReadError => 'Erro na leitura do PDF',
            self::InvoiceNumberNotIdentified => 'Numero da nota nao identificado',
            self::DuplicatePdf => 'PDF duplicado',
        };
    }

    public function blocksLaunch(): bool
    {
        return match ($this) {
            self::PurchaseOrderNotFound,
            self::PurchaseOrderLookupFailed,
            self::PurchaseOrderCancelled,
            self::CnpjMismatch,
            self::DuplicatePdf => true,
            default => false,
        };
    }
}

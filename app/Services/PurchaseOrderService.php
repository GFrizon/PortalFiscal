<?php

namespace App\Services;

class PurchaseOrderService
{
    public function __construct(
        private readonly OraclePurchaseOrderService $oraclePurchaseOrderService,
        private readonly HttpPurchaseOrderService $httpPurchaseOrderService
    ) {
    }

    public function find(string $purchaseOrderNumber): array
    {
        return match (config('erp.purchase_order_driver')) {
            'oracle' => $this->oraclePurchaseOrderService->find($purchaseOrderNumber),
            'http' => $this->httpPurchaseOrderService->find($purchaseOrderNumber),
            default => $this->simulated($purchaseOrderNumber),
        };
    }

    private function simulated(string $purchaseOrderNumber): array
    {
        $number = strtoupper(trim($purchaseOrderNumber));

        if (str_starts_with($number, 'NAO')) {
            return [
                'exists' => false,
                'status' => null,
                'supplier_cnpj' => null,
                'supplier_name' => null,
                'business_unit_id' => null,
                'amount' => null,
                'raw_response' => ['source' => 'simulated', 'number' => $number],
            ];
        }

        if (str_starts_with($number, 'CAN')) {
            return [
                'exists' => true,
                'status' => 'cancelada',
                'supplier_cnpj' => '12345678000195',
                'supplier_name' => 'Fornecedor Simulado LTDA',
                'business_unit_id' => null,
                'amount' => 0,
                'raw_response' => ['source' => 'simulated', 'number' => $number],
            ];
        }

        return [
            'exists' => true,
            'status' => 'aberta',
            'supplier_cnpj' => '12345678000195',
            'supplier_name' => 'Fornecedor Simulado LTDA',
            'business_unit_id' => null,
            'amount' => 1500.00,
            'raw_response' => ['source' => 'simulated', 'number' => $number],
        ];
    }
}

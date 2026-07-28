<?php

namespace Tests\Feature;

use App\Services\OraclePurchaseOrderService;
use Tests\TestCase;

class LocalErpApiTest extends TestCase
{
    public function test_local_erp_api_blocks_requests_without_token(): void
    {
        config([
            'erp.local_api.token' => 'secret-token',
            'erp.local_api.allowed_ips' => ['127.0.0.1'],
            'erp.local_api.require_https' => false,
        ]);

        $this->postJson(route('api.local.purchase-orders.check'), [
            'purchase_order_number' => '123456',
        ])->assertUnauthorized();
    }

    public function test_local_erp_api_returns_purchase_order_with_valid_token(): void
    {
        config([
            'erp.local_api.token' => 'secret-token',
            'erp.local_api.allowed_ips' => ['127.0.0.1'],
            'erp.local_api.require_https' => false,
        ]);

        $this->mock(OraclePurchaseOrderService::class, function ($mock): void {
            $mock->shouldReceive('find')
                ->once()
                ->with('123456')
                ->andReturn([
                    'exists' => true,
                    'status' => 'aberta',
                    'supplier_cnpj' => '12345678000195',
                    'supplier_name' => 'Fornecedor Oracle',
                    'business_unit_id' => null,
                    'amount' => null,
                    'raw_response' => [
                        'source' => 'oracle',
                        'number' => '123456',
                        'company_code' => '001',
                        'erp_status' => 'aberta',
                    ],
                ]);
        });

        $this->withToken('secret-token')
            ->postJson(route('api.local.purchase-orders.check'), [
                'purchase_order_number' => '123456',
            ])
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('supplier_cnpj', '12345678000195')
            ->assertJsonPath('raw_response.source', 'oracle');
    }
}

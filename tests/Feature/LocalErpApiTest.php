<?php

namespace Tests\Feature;

use App\Services\HttpPurchaseOrderService;
use App\Services\OraclePurchaseOrderService;
use Illuminate\Support\Facades\Http;
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
                        'supplier_code' => 'FOR001',
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
            ->assertJsonPath('raw_response.supplier_code', 'FOR001')
            ->assertJsonPath('raw_response.source', 'oracle');
    }

    public function test_http_purchase_order_service_keeps_supplier_code_aliases(): void
    {
        config([
            'erp.http.base_url' => 'http://192.168.0.3:8088',
            'erp.http.token' => 'secret-token',
            'erp.http.timeout' => 8,
            'erp.http.verify_tls' => false,
        ]);

        Http::fake([
            'http://192.168.0.3:8088/api/local/purchase-orders/check' => Http::response([
                'exists' => true,
                'status' => 'aprovado',
                'supplier_cnpj' => '91259168000171',
                'supplier_name' => 'DISMAQUINAS ASSISTENCIA EM MAQUINAS LTDA',
                'amount' => 1697.50,
                'raw_response' => [
                    'source' => 'odbc',
                    'number' => '00103722',
                    'codigo_fornecedor' => '904270',
                    'erp_status' => 'aprovado',
                ],
            ]),
        ]);

        $result = app(HttpPurchaseOrderService::class)->find('00103722');

        $this->assertTrue($result['exists']);
        $this->assertSame('91259168000171', $result['supplier_cnpj']);
        $this->assertSame('904270', $result['raw_response']['supplier_code']);
    }
}

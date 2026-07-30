<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpPurchaseOrderService
{
    public function find(string $purchaseOrderNumber): array
    {
        $number = strtoupper(trim($purchaseOrderNumber));
        $baseUrl = (string) config('erp.http.base_url');
        $token = (string) config('erp.http.token');

        if ($number === '' || $baseUrl === '' || $token === '') {
            return $this->notFound($number, 'http_not_configured');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('erp.http.timeout', 8))
                ->withOptions(['verify' => (bool) config('erp.http.verify_tls', true)])
                ->post($baseUrl.'/api/local/purchase-orders/check', [
                    'purchase_order_number' => $number,
                ]);

            if (! $response->successful()) {
                Log::warning('API local do ERP retornou erro.', [
                    'purchase_order_number' => $number,
                    'status' => $response->status(),
                ]);

                return $this->notFound($number, 'http_error');
            }

            return $this->normalizePayload($response->json(), $number);
        } catch (Throwable $exception) {
            Log::error('Falha ao consultar API local do ERP.', [
                'purchase_order_number' => $number,
                'message' => $exception->getMessage(),
            ]);

            return $this->notFound($number, 'http_exception');
        }
    }

    private function normalizePayload(mixed $payload, string $number): array
    {
        $data = is_array($payload) ? $payload : [];

        return [
            'exists' => (bool) ($data['exists'] ?? false),
            'status' => $this->normalizeStatus((string) ($data['status'] ?? '')),
            'supplier_cnpj' => $this->normalizeCnpj((string) ($data['supplier_cnpj'] ?? '')),
            'supplier_name' => $this->cleanNullableString($data['supplier_name'] ?? null),
            'business_unit_id' => null,
            'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
            'raw_response' => [
                'source' => 'http',
                'number' => $number,
                'remote_source' => $data['raw_response']['source'] ?? null,
                'company_code' => $data['raw_response']['company_code'] ?? null,
                'supplier_code' => $this->cleanNullableString(
                    $data['raw_response']['supplier_code']
                        ?? $data['raw_response']['codigo_fornecedor']
                        ?? $data['raw_response']['cod_fornecedor']
                        ?? $data['raw_response']['fornecedor_codigo']
                        ?? $data['raw_response']['cd_fornecedor']
                        ?? null
                ),
                'erp_status' => $data['raw_response']['erp_status'] ?? null,
            ],
        ];
    }

    private function normalizeCnpj(string $cnpj): ?string
    {
        $normalized = preg_replace('/\D/', '', $cnpj) ?: '';

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeStatus(string $status): ?string
    {
        $normalized = mb_strtolower(trim($status));

        return match (true) {
            $normalized === '' => null,
            in_array($normalized, ['l', 'liq', 'liquidado', 'liquidada'], true) => 'liquidada',
            in_array($normalized, ['a', 'aberto', 'aberta'], true) => 'aberta',
            str_contains($normalized, 'cancel') || in_array($normalized, ['c', 'can', '9'], true) => 'cancelada',
            in_array($normalized, ['p', 'pendente', 'pendencia'], true) => 'pendente',
            default => $normalized,
        };
    }

    private function cleanNullableString(mixed $value): ?string
    {
        $clean = trim((string) $value);

        return $clean !== '' ? $clean : null;
    }

    private function notFound(string $number, string $source): array
    {
        return [
            'exists' => false,
            'status' => null,
            'supplier_cnpj' => null,
            'supplier_name' => null,
            'business_unit_id' => null,
            'amount' => null,
            'raw_response' => [
                'source' => $source,
                'number' => $number,
            ],
        ];
    }
}

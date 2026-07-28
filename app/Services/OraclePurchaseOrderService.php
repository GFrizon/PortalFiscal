<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OraclePurchaseOrderService
{
    public function find(string $purchaseOrderNumber): array
    {
        $number = strtoupper(trim($purchaseOrderNumber));

        if ($number === '') {
            return $this->notFound($number, 'empty_number');
        }

        if (! function_exists('oci_connect')) {
            Log::error('Extensao OCI8 nao instalada no servidor local.');

            return $this->notFound($number, 'oci8_missing');
        }

        $connection = null;
        $statement = null;

        try {
            $connection = $this->connect();
            $statement = oci_parse($connection, $this->query());

            if (! $statement) {
                throw new RuntimeException('Nao foi possivel preparar a consulta Oracle.');
            }

            oci_bind_by_name($statement, ':purchase_order_number', $number);
            $normalizedNumber = ltrim($number, '0') ?: '0';
            oci_bind_by_name($statement, ':normalized_purchase_order_number', $normalizedNumber);

            if (! oci_execute($statement, OCI_NO_AUTO_COMMIT)) {
                throw new RuntimeException($this->lastOracleError($statement));
            }

            $row = oci_fetch_assoc($statement);

            if (! $row) {
                return $this->notFound($number, 'oracle');
            }

            $status = $this->normalizeStatus((string) ($row['CONTROL_DESCRIPTION'] ?? $row['STATUS'] ?? ''));

            return [
                'exists' => true,
                'status' => $status,
                'supplier_cnpj' => $this->normalizeCnpj((string) ($row['SUPPLIER_CNPJ'] ?? '')),
                'supplier_name' => $this->cleanNullableString($row['SUPPLIER_NAME'] ?? null),
                'business_unit_id' => null,
                'amount' => $this->normalizeAmount($row['AMOUNT'] ?? null),
                'raw_response' => [
                    'source' => 'oracle',
                    'number' => $number,
                    'purchase_order_number' => $this->cleanNullableString($row['PURCHASE_ORDER_NUMBER'] ?? null),
                    'supplier_code' => $this->cleanNullableString($row['SUPPLIER_CODE'] ?? null),
                    'control_code' => $this->cleanNullableString($row['CONTROL_CODE'] ?? null),
                    'control_description' => $this->cleanNullableString($row['CONTROL_DESCRIPTION'] ?? null),
                    'erp_status' => $this->cleanNullableString($row['STATUS'] ?? null),
                ],
            ];
        } catch (Throwable $exception) {
            Log::error('Falha segura ao consultar ordem de compra no Oracle.', [
                'purchase_order_number' => $number,
                'message' => $exception->getMessage(),
            ]);

            return $this->notFound($number, 'oracle_error');
        } finally {
            if ($statement) {
                oci_free_statement($statement);
            }

            if ($connection) {
                oci_close($connection);
            }
        }
    }

    private function connect(): mixed
    {
        $connection = @oci_connect(
            (string) config('erp.oracle.username'),
            (string) config('erp.oracle.password'),
            (string) config('erp.oracle.connection_string'),
            (string) config('erp.oracle.charset')
        );

        if (! $connection) {
            throw new RuntimeException($this->lastOracleError());
        }

        return $connection;
    }

    private function query(): string
    {
        $orderTable = $this->identifier('erp.oracle.order_table');
        $orderNumberColumn = $this->identifier('erp.oracle.order_number_column');
        $orderStatusColumn = $this->identifier('erp.oracle.order_status_column');
        $orderSupplierColumn = $this->identifier('erp.oracle.order_supplier_column');
        $orderControlColumn = $this->identifier('erp.oracle.order_control_column');
        $controlTable = $this->identifier('erp.oracle.control_table');
        $controlCodeColumn = $this->identifier('erp.oracle.control_code_column');
        $controlDescriptionColumn = $this->identifier('erp.oracle.control_description_column');
        $supplierTable = $this->identifier('erp.oracle.supplier_table');
        $supplierCompanyCodeColumn = $this->identifier('erp.oracle.supplier_company_code_column');
        $supplierCnpjColumn = $this->identifier('erp.oracle.supplier_cnpj_column');
        $supplierNameColumn = $this->identifier('erp.oracle.supplier_name_column');
        $amountColumn = config('erp.oracle.amount_column')
            ? 'O.'.$this->identifier('erp.oracle.amount_column')
            : 'NULL';

        return <<<SQL
            SELECT *
            FROM (
                SELECT
                    O.{$orderNumberColumn} AS PURCHASE_ORDER_NUMBER,
                    O.{$orderStatusColumn} AS STATUS,
                    O.{$orderSupplierColumn} AS SUPPLIER_CODE,
                    O.{$orderControlColumn} AS CONTROL_CODE,
                    C.{$controlDescriptionColumn} AS CONTROL_DESCRIPTION,
                    E.{$supplierCnpjColumn} AS SUPPLIER_CNPJ,
                    E.{$supplierNameColumn} AS SUPPLIER_NAME,
                    {$amountColumn} AS AMOUNT
                FROM {$orderTable} O
                LEFT JOIN {$supplierTable} E
                    ON E.{$supplierCompanyCodeColumn} = O.{$orderSupplierColumn}
                LEFT JOIN {$controlTable} C
                    ON C.{$controlCodeColumn} = O.{$orderControlColumn}
                WHERE TRIM(UPPER(TO_CHAR(O.{$orderNumberColumn}))) = :purchase_order_number
                    OR LTRIM(TRIM(UPPER(TO_CHAR(O.{$orderNumberColumn}))), '0') = :normalized_purchase_order_number
            )
            WHERE ROWNUM = 1
            SQL;
    }

    private function identifier(string $configKey): string
    {
        $identifier = strtoupper((string) config($configKey));

        if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $identifier)) {
            throw new RuntimeException("Identificador Oracle invalido em {$configKey}.");
        }

        return $identifier;
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

    private function normalizeCnpj(string $cnpj): ?string
    {
        $normalized = preg_replace('/\D/', '', $cnpj) ?: '';

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeAmount(mixed $amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $amount);
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

    private function lastOracleError(mixed $resource = null): string
    {
        $error = $resource ? oci_error($resource) : oci_error();

        return is_array($error) ? (string) ($error['message'] ?? 'Erro Oracle desconhecido.') : 'Erro Oracle desconhecido.';
    }
}

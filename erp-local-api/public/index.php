<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

loadEnv($basePath.'/.env');

try {
    routeRequest($basePath);
} catch (Throwable $exception) {
    $debug = envValue('APP_DEBUG', 'false') === 'true';

    jsonResponse([
        'error' => 'internal_error',
        'message' => $debug ? $exception->getMessage() : 'Erro interno.',
    ], 500);
}

function routeRequest(string $basePath): void
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET' && $path === '/health') {
        jsonResponse([
            'ok' => true,
            'driver' => envValue('ERP_DRIVER', 'simulated'),
            'time' => date(DATE_ATOM),
        ]);
    }

    if ($method !== 'POST' || $path !== '/api/local/purchase-orders/check') {
        jsonResponse(['error' => 'not_found'], 404);
    }

    authorizeRequest();

    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);

    if (! is_array($payload)) {
        jsonResponse(['error' => 'invalid_json'], 422);
    }

    $number = strtoupper(trim((string) ($payload['purchase_order_number'] ?? '')));

    if ($number === '' || strlen($number) > 80) {
        jsonResponse(['error' => 'invalid_purchase_order_number'], 422);
    }

    jsonResponse(findPurchaseOrder($number, $basePath));
}

function authorizeRequest(): void
{
    if (envValue('API_REQUIRE_HTTPS', 'false') === 'true' && ! isHttps()) {
        jsonResponse(['error' => 'https_required'], 403);
    }

    $allowedIps = array_filter(array_map('trim', explode(',', envValue('API_ALLOWED_IPS', '127.0.0.1,::1'))));
    $clientIp = clientIp();

    if ($allowedIps !== [] && ! in_array($clientIp, $allowedIps, true)) {
        jsonResponse(['error' => 'ip_not_allowed', 'ip' => $clientIp], 403);
    }

    $configuredToken = envValue('API_TOKEN', '');
    $requestToken = bearerToken();

    if ($configuredToken === '' || $requestToken === '' || ! hash_equals($configuredToken, $requestToken)) {
        jsonResponse(['error' => 'invalid_token'], 401);
    }
}

function findPurchaseOrder(string $number, string $basePath): array
{
    return match (strtolower(envValue('ERP_DRIVER', 'simulated'))) {
        'csv' => findFromCsv($number, $basePath),
        'odbc' => findFromOdbc($number),
        'oracle' => findFromOracle($number),
        default => findSimulated($number),
    };
}

function findSimulated(string $number): array
{
    if ($number !== '103635') {
        return notFound($number, 'simulated');
    }

    return normalizeRow([
        'purchase_order_number' => $number,
        'status' => 'aberta',
        'supplier_cnpj' => '91967067000155',
        'supplier_name' => 'BAKOF PLASTICOS LTDA',
        'amount' => 254.54,
    ], $number, 'simulated');
}

function findFromCsv(string $number, string $basePath): array
{
    $path = envValue('CSV_PATH', 'storage/purchase-orders.csv');
    $fullPath = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path)
        ? $path
        : $basePath.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    if (! is_file($fullPath)) {
        return notFound($number, 'csv_missing');
    }

    $handle = fopen($fullPath, 'rb');

    if (! $handle) {
        return notFound($number, 'csv_unreadable');
    }

    $headers = fgetcsv($handle) ?: [];

    while (($values = fgetcsv($handle)) !== false) {
        $row = array_combine($headers, $values);

        if (! is_array($row)) {
            continue;
        }

        if (normalizeNumber((string) ($row['purchase_order_number'] ?? '')) === normalizeNumber($number)) {
            fclose($handle);

            return normalizeRow($row, $number, 'csv');
        }
    }

    fclose($handle);

    return notFound($number, 'csv');
}

function findFromOdbc(string $number): array
{
    if (! function_exists('odbc_connect')) {
        return notFound($number, 'odbc_missing');
    }

    $connection = @odbc_connect(envValue('ODBC_DSN', ''), envValue('ODBC_USER', ''), envValue('ODBC_PASSWORD', ''));

    if (! $connection) {
        return notFound($number, 'odbc_connection_error');
    }

    $statement = @odbc_prepare($connection, envValue('ODBC_QUERY', ''));

    if (! $statement || ! @odbc_execute($statement, [$number])) {
        @odbc_close($connection);

        return notFound($number, 'odbc_query_error');
    }

    $row = odbc_fetch_array($statement) ?: null;
    @odbc_close($connection);

    return is_array($row) ? normalizeRow($row, $number, 'odbc') : notFound($number, 'odbc');
}

function findFromOracle(string $number): array
{
    if (! function_exists('oci_connect')) {
        return notFound($number, 'oci8_missing');
    }

    $connection = @oci_connect(
        envValue('ORACLE_UID', ''),
        envValue('ORACLE_PWD', ''),
        envValue('ORACLE_DBQ', ''),
        envValue('ORACLE_CHARSET', 'AL32UTF8')
    );

    if (! $connection) {
        return notFound($number, 'oracle_connection_error');
    }

    $statement = @oci_parse($connection, envValue('ORACLE_QUERY', ''));

    if (! $statement) {
        @oci_close($connection);

        return notFound($number, 'oracle_query_error');
    }

    oci_bind_by_name($statement, ':number', $number);

    if (! @oci_execute($statement, OCI_NO_AUTO_COMMIT)) {
        @oci_free_statement($statement);
        @oci_close($connection);

        return notFound($number, 'oracle_execute_error');
    }

    $row = oci_fetch_assoc($statement) ?: null;
    oci_free_statement($statement);
    oci_close($connection);

    return is_array($row) ? normalizeRow($row, $number, 'oracle') : notFound($number, 'oracle');
}

function normalizeRow(array $row, string $number, string $source): array
{
    $lower = [];

    foreach ($row as $key => $value) {
        $lower[strtolower((string) $key)] = $value;
    }

    return [
        'exists' => true,
        'status' => normalizeStatus((string) ($lower['status'] ?? '')),
        'supplier_cnpj' => normalizeCnpj((string) ($lower['supplier_cnpj'] ?? '')),
        'supplier_name' => cleanNullableString($lower['supplier_name'] ?? null),
        'business_unit_id' => null,
        'amount' => normalizeAmount($lower['amount'] ?? null),
        'raw_response' => [
            'source' => $source,
            'number' => $number,
            'purchase_order_number' => cleanNullableString($lower['purchase_order_number'] ?? null),
            'erp_status' => cleanNullableString($lower['status'] ?? null),
        ],
    ];
}

function notFound(string $number, string $source): array
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

function loadEnv(string $path): void
{
    if (! is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
    }
}

function envValue(string $key, string $default = ''): string
{
    return (string) ($_ENV[$key] ?? getenv($key) ?: $default);
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function bearerToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (stripos($header, 'Bearer ') !== 0) {
        return '';
    }

    return trim(substr($header, 7));
}

function clientIp(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
}

function isHttps(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function normalizeNumber(string $value): string
{
    $clean = strtoupper(trim($value));
    $withoutZeros = ltrim($clean, '0');

    return $withoutZeros !== '' ? $withoutZeros : $clean;
}

function normalizeCnpj(string $cnpj): ?string
{
    $normalized = preg_replace('/\D/', '', $cnpj) ?: '';

    return $normalized !== '' ? $normalized : null;
}

function normalizeAmount(mixed $amount): ?float
{
    if ($amount === null || $amount === '') {
        return null;
    }

    return (float) str_replace(',', '.', (string) $amount);
}

function normalizeStatus(string $status): ?string
{
    $normalized = strtolower(trim($status));

    return match (true) {
        $normalized === '' => null,
        in_array($normalized, ['l', 'liq', 'liquidado', 'liquidada'], true) => 'liquidada',
        in_array($normalized, ['a', 'aberto', 'aberta'], true) => 'aberta',
        in_array($normalized, ['c', 'can', 'cancelado', 'cancelada', '9'], true) => 'cancelada',
        in_array($normalized, ['p', 'pendente', 'pendencia'], true) => 'pendente',
        default => $normalized,
    };
}

function cleanNullableString(mixed $value): ?string
{
    $clean = trim((string) $value);

    return $clean !== '' ? $clean : null;
}

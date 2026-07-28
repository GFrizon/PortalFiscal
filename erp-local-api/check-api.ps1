param(
    [string] $BaseUrl = "http://127.0.0.1:8088",
    [string] $Token = "",
    [string] $Number = "103635"
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path

if (-not $Token) {
    $EnvPath = Join-Path $Root ".env"

    if (Test-Path $EnvPath) {
        $TokenLine = Get-Content $EnvPath | Where-Object { $_ -match "^API_TOKEN=" } | Select-Object -First 1
        $Token = ($TokenLine -replace "^API_TOKEN=", "").Trim('"').Trim("'")
    }
}

if (-not $Token) {
    throw "Informe -Token ou configure API_TOKEN no .env."
}

$Health = Invoke-RestMethod -Method Get -Uri "$BaseUrl/health"
$Payload = @{ purchase_order_number = $Number } | ConvertTo-Json
$Order = Invoke-RestMethod `
    -Method Post `
    -Uri "$BaseUrl/api/local/purchase-orders/check" `
    -Headers @{ Authorization = "Bearer $Token" } `
    -ContentType "application/json" `
    -Body $Payload

[pscustomobject]@{
    HealthOk = $Health.ok
    Driver = $Health.driver
    Number = $Number
    Exists = $Order.exists
    Status = $Order.status
    SupplierCnpj = $Order.supplier_cnpj
    SupplierName = $Order.supplier_name
}

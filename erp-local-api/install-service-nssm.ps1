param(
    [string] $ServiceName = "PortalFiscalErpApi",
    [string] $PhpPath = "php",
    [string] $Port = "8088",
    [string] $NssmPath = "nssm.exe"
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$PublicPath = Join-Path $Root "public"

if (-not (Test-Path (Join-Path $Root ".env"))) {
    Copy-Item (Join-Path $Root ".env.example") (Join-Path $Root ".env")
}

& $NssmPath install $ServiceName $PhpPath "-S 0.0.0.0:$Port -t `"$PublicPath`""
& $NssmPath set $ServiceName AppDirectory $Root
& $NssmPath set $ServiceName DisplayName "Portal Fiscal ERP API"
& $NssmPath set $ServiceName Description "API local para consulta de ordens de compra do CIGAM/ERP."
& $NssmPath set $ServiceName Start SERVICE_AUTO_START
& $NssmPath start $ServiceName

Write-Host "Servico instalado e iniciado: $ServiceName"
Write-Host "Healthcheck: http://127.0.0.1:$Port/health"

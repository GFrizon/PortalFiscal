@echo off
setlocal
cd /d "%~dp0"

if not exist ".env" copy ".env.example" ".env" >nul

for /f "tokens=1,* delims==" %%A in ('findstr /b "API_TOKEN=" .env') do set "API_TOKEN=%%B"

if "%API_TOKEN%"=="" (
  echo API_TOKEN nao encontrado no .env.
  exit /b 1
)

echo Testando healthcheck...
curl -s http://127.0.0.1:8088/health
echo.

echo Testando ordem 103635...
curl -s -X POST http://127.0.0.1:8088/api/local/purchase-orders/check -H "Authorization: Bearer %API_TOKEN%" -H "Content-Type: application/json" -d "{\"purchase_order_number\":\"103635\"}"
echo.

echo Teste finalizado.

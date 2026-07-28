# API local do CIGAM

API pequena em PHP puro para rodar no servidor interno da rede e responder ao Portal Fiscal no cPanel.

Ela nao precisa de Composer. No inicio voce pode rodar com `php -S`; depois pode transformar em servico no Windows com NSSM ou publicar no IIS.

## Contrato

Endpoint:

```http
POST /api/local/purchase-orders/check
Authorization: Bearer SEU_TOKEN
Content-Type: application/json
```

Body:

```json
{
  "purchase_order_number": "103635"
}
```

Resposta:

```json
{
  "exists": true,
  "status": "aberta",
  "supplier_cnpj": "91967067000155",
  "supplier_name": "FORNECEDOR LTDA",
  "business_unit_id": null,
  "amount": 254.54,
  "raw_response": {
    "source": "odbc",
    "number": "103635"
  }
}
```

## Instalar no Windows Server

1. Copie a pasta `erp-local-api` para o servidor, exemplo:

```text
C:\PortalFiscal\erp-local-api
```

2. Copie `.env.example` para `.env`.

3. Gere um token grande e coloque em `API_TOKEN`.

4. Para testar sem CIGAM, deixe:

```env
ERP_DRIVER=simulated
API_ALLOWED_IPS=127.0.0.1,::1
API_REQUIRE_HTTPS=false
```

5. Rode:

```bat
cd C:\PortalFiscal\erp-local-api
start-api.bat
```

6. Teste no proprio servidor:

```bat
curl -X POST http://127.0.0.1:8088/api/local/purchase-orders/check -H "Authorization: Bearer SEU_TOKEN" -H "Content-Type: application/json" -d "{\"purchase_order_number\":\"103635\"}"
```

## Ligar no portal do cPanel

No `.env` do portal:

```env
ERP_PURCHASE_ORDER_DRIVER=http
ERP_API_BASE_URL=http://IP_DO_SERVIDOR_INTERNO:8088
ERP_API_TOKEN=SEU_TOKEN
ERP_API_VERIFY_TLS=false
```

Quando expor com HTTPS real:

```env
ERP_API_BASE_URL=https://api.bktec.com.br
ERP_API_VERIFY_TLS=true
```

## Driver ODBC

No Windows, crie um DSN ODBC que enxergue o CIGAM e configure:

```env
ERP_DRIVER=odbc
ODBC_DSN=CIGAM
ODBC_USER=usuario
ODBC_PASSWORD=senha
ODBC_QUERY="SELECT TOP 1 numero AS purchase_order_number, status AS status, fornecedor_cnpj AS supplier_cnpj, fornecedor_nome AS supplier_name, total AS amount FROM ordens_compra WHERE numero = ?"
```

A consulta precisa retornar estes aliases:

```text
purchase_order_number
status
supplier_cnpj
supplier_name
amount
```

`amount` pode ser nulo.

## Driver CSV

Para validar antes de conectar no CIGAM:

```env
ERP_DRIVER=csv
CSV_PATH=storage/purchase-orders.csv
```

Formato do CSV:

```csv
purchase_order_number,status,supplier_cnpj,supplier_name,amount
103635,aberta,91967067000155,BAKOF PLASTICOS LTDA,254.54
```

## Seguranca

- Use token grande.
- Restrinja `API_ALLOWED_IPS` ao IP do cPanel quando publicar.
- Libere no firewall somente a porta necessaria.
- Use HTTPS antes de deixar acessivel pela internet.
- Nunca coloque usuario/senha do CIGAM no cPanel.

# API local do ERP Oracle

Esta API deve rodar em um servidor dentro da rede local da BAKOF, com acesso ao Oracle do ERP.
O portal no cPanel deve consultar esta API por HTTPS e token Bearer.

## Servidor local Oracle

Configure no `.env` do servidor local:

```env
ERP_PURCHASE_ORDER_DRIVER=oracle

ERP_LOCAL_API_TOKEN=gere_um_token_longo_com_64_ou_mais_caracteres
ERP_LOCAL_API_ALLOWED_IPS=127.0.0.1,IP_PUBLICO_DO_CPANEL
ERP_LOCAL_API_REQUIRE_HTTPS=true

ORACLE_UID=bakof
ORACLE_PWD=sua_senha
ORACLE_DBQ=192.168.0.6:1521/orcl
ORACLE_CHARSET=AL32UTF8
ORACLE_ORDER_TABLE=COORDEM
ORACLE_ORDER_NUMBER_COLUMN=CD_ORDEM_COMPRA
ORACLE_ORDER_STATUS_COLUMN=SITUACAO
ORACLE_ORDER_SUPPLIER_COLUMN=CD_FORNECEDOR
ORACLE_ORDER_CONTROL_COLUMN=CAMPO89
ORACLE_CONTROL_TABLE=COCONTRO
ORACLE_CONTROL_CODE_COLUMN=CONTROLE
ORACLE_CONTROL_DESCRIPTION_COLUMN=DESCRICAO
ORACLE_SUPPLIER_TABLE=GEEMPRES
ORACLE_SUPPLIER_COMPANY_CODE_COLUMN=CD_EMPRESA
ORACLE_SUPPLIER_CNPJ_COLUMN=CNPJ_CPF
ORACLE_SUPPLIER_NAME_COLUMN=NOME_COMPLETO
ORACLE_ORDER_AMOUNT_COLUMN=TOTAL_ORDEM
```

## Portal no cPanel

Configure no `.env` do portal publicado:

```env
ERP_PURCHASE_ORDER_DRIVER=http
ERP_API_BASE_URL=https://api-local-da-bakof.exemplo.com
ERP_API_TOKEN=mesmo_token_do_servidor_local
ERP_API_TIMEOUT=8
ERP_API_VERIFY_TLS=true
```

## Endpoint

```http
POST /api/local/purchase-orders/check
Authorization: Bearer TOKEN_FORTE
Content-Type: application/json
```

Body:

```json
{
  "purchase_order_number": "123456"
}
```

Resposta esperada:

```json
{
  "exists": true,
  "status": "aberta",
  "supplier_cnpj": "12345678000195",
  "supplier_name": "Fornecedor",
  "business_unit_id": null,
  "amount": null,
  "raw_response": {
    "source": "oracle",
    "number": "123456",
    "company_code": "001",
    "erp_status": "aberta"
  }
}
```

## Segurança obrigatoria

- Use HTTPS real quando o cPanel chamar a API local.
- Libere no firewall somente o IP do cPanel para a porta da API.
- Use token com 64 ou mais caracteres aleatorios.
- Nunca exponha `ORACLE_UID`, `ORACLE_PWD` ou `ORACLE_DBQ` no cPanel.
- Mantenha `ERP_LOCAL_API_ALLOWED_IPS` restrito.
- Se usar proxy reverso, repasse corretamente o IP real do cliente.
- Nao publique esta API aberta na internet sem firewall e HTTPS.

## Teste local

```bash
curl -X POST http://127.0.0.1:8000/api/local/purchase-orders/check \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"purchase_order_number\":\"123456\"}"
```

## Requisitos no servidor local

- PHP 8.2 ou superior.
- Extensao `oci8` habilitada.
- Oracle Instant Client instalado.
- Acesso de rede ao Oracle `192.168.0.6:1521/orcl`.

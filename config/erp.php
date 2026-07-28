<?php

return [
    'purchase_order_driver' => env('ERP_PURCHASE_ORDER_DRIVER', 'simulated'),

    'local_api' => [
        'token' => env('ERP_LOCAL_API_TOKEN'),
        'allowed_ips' => array_filter(array_map('trim', explode(',', (string) env('ERP_LOCAL_API_ALLOWED_IPS', '127.0.0.1,::1')))),
        'require_https' => (bool) env('ERP_LOCAL_API_REQUIRE_HTTPS', false),
    ],

    'http' => [
        'base_url' => rtrim((string) env('ERP_API_BASE_URL', ''), '/'),
        'token' => env('ERP_API_TOKEN'),
        'timeout' => (int) env('ERP_API_TIMEOUT', 8),
        'verify_tls' => (bool) env('ERP_API_VERIFY_TLS', true),
    ],

    'oracle' => [
        'username' => env('ORACLE_UID'),
        'password' => env('ORACLE_PWD'),
        'connection_string' => env('ORACLE_DBQ'),
        'charset' => env('ORACLE_CHARSET', 'AL32UTF8'),
        'order_table' => env('ORACLE_ORDER_TABLE', 'COORDEM'),
        'order_number_column' => env('ORACLE_ORDER_NUMBER_COLUMN', 'CD_ORDEM_COMPRA'),
        'order_status_column' => env('ORACLE_ORDER_STATUS_COLUMN', 'SITUACAO'),
        'order_supplier_column' => env('ORACLE_ORDER_SUPPLIER_COLUMN', 'CD_FORNECEDOR'),
        'order_control_column' => env('ORACLE_ORDER_CONTROL_COLUMN', 'CAMPO89'),
        'control_table' => env('ORACLE_CONTROL_TABLE', 'COCONTRO'),
        'control_code_column' => env('ORACLE_CONTROL_CODE_COLUMN', 'CONTROLE'),
        'control_description_column' => env('ORACLE_CONTROL_DESCRIPTION_COLUMN', 'DESCRICAO'),
        'supplier_table' => env('ORACLE_SUPPLIER_TABLE', 'GEEMPRES'),
        'supplier_company_code_column' => env('ORACLE_SUPPLIER_COMPANY_CODE_COLUMN', 'CD_EMPRESA'),
        'supplier_cnpj_column' => env('ORACLE_SUPPLIER_CNPJ_COLUMN', 'CNPJ_CPF'),
        'supplier_name_column' => env('ORACLE_SUPPLIER_NAME_COLUMN', 'NOME_COMPLETO'),
        'amount_column' => env('ORACLE_ORDER_AMOUNT_COLUMN', 'TOTAL_ORDEM'),
    ],
];

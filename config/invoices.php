<?php

return [
    'pdf' => [
        'max_upload_kb' => (int) env('INVOICE_PDF_MAX_UPLOAD_KB', 10240),

        'optimization' => [
            'enabled' => (bool) env('INVOICE_PDF_OPTIMIZATION_ENABLED', false),
            'binary' => env('INVOICE_PDF_OPTIMIZATION_BINARY', 'gs'),
            'quality' => env('INVOICE_PDF_OPTIMIZATION_QUALITY', '/ebook'),
            'timeout' => (int) env('INVOICE_PDF_OPTIMIZATION_TIMEOUT', 60),
            'min_savings_percent' => (float) env('INVOICE_PDF_OPTIMIZATION_MIN_SAVINGS_PERCENT', 8),
        ],
    ],
];

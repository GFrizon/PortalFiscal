<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Pendencia registrada</title>
</head>
<body style="margin:0; padding:0; background:#f4f7fa; color:#162033; font-family:Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fa; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px; width:100%; background:#ffffff; border:1px solid #d8e2ee; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="padding:22px 24px; background:#10213a; color:#ffffff;">
                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em;">Portal Fiscal</div>
                            <h1 style="margin:6px 0 0; font-size:22px; line-height:1.25;">Pendencia registrada na nota</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 14px; font-size:15px; line-height:1.5;">
                                Ola, {{ $invoice->submitter?->name ?? 'usuario' }}.
                            </p>
                            <p style="margin:0 0 18px; font-size:15px; line-height:1.5;">
                                O fiscal {{ $fiscal->name }} registrou uma pendencia na nota abaixo.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:0 0 18px;">
                                <tr>
                                    <td style="padding:10px 12px; border:1px solid #d8e2ee; color:#627187; font-size:12px; font-weight:700; text-transform:uppercase;">Protocolo</td>
                                    <td style="padding:10px 12px; border:1px solid #d8e2ee; font-weight:700;">{{ $invoice->protocol }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px; border:1px solid #d8e2ee; color:#627187; font-size:12px; font-weight:700; text-transform:uppercase;">Nota</td>
                                    <td style="padding:10px 12px; border:1px solid #d8e2ee; font-weight:700;">{{ $invoice->invoice_number ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px; border:1px solid #d8e2ee; color:#627187; font-size:12px; font-weight:700; text-transform:uppercase;">OC/CTE</td>
                                    <td style="padding:10px 12px; border:1px solid #d8e2ee; font-weight:700;">{{ $invoice->purchase_order_number ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px; border:1px solid #d8e2ee; color:#627187; font-size:12px; font-weight:700; text-transform:uppercase;">Unidade</td>
                                    <td style="padding:10px 12px; border:1px solid #d8e2ee; font-weight:700;">{{ $invoice->businessUnit?->name ?? 'Nao identificada' }}</td>
                                </tr>
                            </table>

                            <div style="padding:14px 16px; border:1px solid #f1c36d; border-radius:8px; background:#fff8e8;">
                                <div style="margin-bottom:6px; color:#8a5a00; font-size:12px; font-weight:800; text-transform:uppercase;">Motivo da pendencia</div>
                                <div style="font-size:15px; line-height:1.5; white-space:pre-line;">{{ $notes }}</div>
                            </div>

                            <p style="margin:18px 0 0; color:#627187; font-size:13px; line-height:1.5;">
                                Acesse o Portal Fiscal para revisar e reenviar as informacoes necessarias.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

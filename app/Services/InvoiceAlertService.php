<?php

namespace App\Services;

use App\Enums\AlertLevel;
use App\Enums\AlertType;
use App\Models\Invoice;
use App\Models\InvoiceAlert;
use App\Models\User;

class InvoiceAlertService
{
    public function create(Invoice $invoice, AlertType $type, string $message, AlertLevel $level = AlertLevel::Warning): InvoiceAlert
    {
        return $invoice->alerts()->create([
            'type' => $type,
            'message' => $message,
            'level' => $level,
        ]);
    }

    public function resolve(InvoiceAlert $alert, User $user): InvoiceAlert
    {
        $alert->update([
            'resolved' => true,
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);

        return $alert->refresh();
    }
}

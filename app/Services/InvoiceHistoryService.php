<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\User;
use Illuminate\Http\Request;

class InvoiceHistoryService
{
    public function record(
        Invoice $invoice,
        ?User $user,
        string $action,
        ?InvoiceStatus $previousStatus = null,
        ?InvoiceStatus $newStatus = null,
        ?string $note = null,
        ?Request $request = null
    ): InvoiceHistory {
        return $invoice->histories()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'ip_address' => $request?->ip(),
        ]);
    }
}

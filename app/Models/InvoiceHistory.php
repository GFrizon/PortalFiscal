<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceHistory extends Model
{
    protected $fillable = [
        'invoice_id',
        'user_id',
        'action',
        'previous_status',
        'new_status',
        'note',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'previous_status' => InvoiceStatus::class,
            'new_status' => InvoiceStatus::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

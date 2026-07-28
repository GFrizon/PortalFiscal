<?php

namespace App\Models;

use App\Enums\AlertLevel;
use App\Enums\AlertType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAlert extends Model
{
    protected $fillable = [
        'invoice_id',
        'type',
        'message',
        'level',
        'resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'level' => AlertLevel::class,
            'resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}

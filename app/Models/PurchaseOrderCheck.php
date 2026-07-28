<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderCheck extends Model
{
    protected $appends = [
        'order_exists',
    ];

    protected $fillable = [
        'invoice_id',
        'purchase_order_number',
        'exists',
        'status',
        'supplier_cnpj',
        'supplier_name',
        'business_unit_id',
        'amount',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'exists' => 'boolean',
            'amount' => 'decimal:2',
            'raw_response' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getOrderExistsAttribute(): bool
    {
        return (bool) $this->getAttribute('exists');
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }
}

<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'protocol',
        'submitted_by',
        'business_unit_id',
        'purchase_order_number',
        'invoice_number',
        'issuer_cnpj',
        'issuer_legal_name',
        'recipient_cnpj',
        'recipient_legal_name',
        'due_date',
        'arrival_date',
        'sent_at',
        'user_notes',
        'fiscal_notes',
        'pdf_path',
        'original_pdf_name',
        'original_file_size',
        'file_size',
        'pdf_sha256',
        'pdf_optimized',
        'pdf_processed_at',
        'status',
        'fiscal_user_id',
        'launched_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'arrival_date' => 'date',
            'sent_at' => 'datetime',
            'launched_at' => 'datetime',
            'pdf_optimized' => 'boolean',
            'pdf_processed_at' => 'datetime',
            'status' => InvoiceStatus::class,
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function fiscalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fiscal_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(InvoiceHistory::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(InvoiceAlert::class);
    }

    public function purchaseOrderCheck(): HasOne
    {
        return $this->hasOne(PurchaseOrderCheck::class);
    }
}

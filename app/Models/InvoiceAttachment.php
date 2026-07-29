<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAttachment extends Model
{
    protected $fillable = [
        'invoice_id',
        'uploaded_by',
        'original_name',
        'path',
        'mime_type',
        'file_size',
        'notes',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function formattedSize(): string
    {
        $bytes = (int) $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($index = 0; $bytes >= 1024 && $index < count($units) - 1; $index++) {
            $bytes /= 1024;
        }

        return number_format($bytes, $index === 0 ? 0 : 2, ',', '.').' '.$units[$index];
    }
}

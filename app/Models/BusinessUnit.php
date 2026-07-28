<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\BusinessUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessUnit extends Model
{
    /** @use HasFactory<BusinessUnitFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'cnpj',
        'internal_code',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function purchaseOrderChecks(): HasMany
    {
        return $this->hasMany(PurchaseOrderCheck::class);
    }
}

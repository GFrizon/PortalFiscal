<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceProtocolSequence extends Model
{
    protected $fillable = [
        'year',
        'last_number',
    ];
}

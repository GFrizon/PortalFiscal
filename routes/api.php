<?php

use App\Http\Controllers\Api\LocalPurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['local_erp_api', 'throttle:30,1'])
    ->post('local/purchase-orders/check', [LocalPurchaseOrderController::class, 'check'])
    ->name('api.local.purchase-orders.check');

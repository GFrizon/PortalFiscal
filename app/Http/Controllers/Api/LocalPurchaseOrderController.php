<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OraclePurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalPurchaseOrderController extends Controller
{
    public function check(Request $request, OraclePurchaseOrderService $purchaseOrderService): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_number' => ['required', 'string', 'max:80'],
        ]);

        $purchaseOrder = $purchaseOrderService->find((string) $validated['purchase_order_number']);

        return response()->json($purchaseOrder);
    }
}

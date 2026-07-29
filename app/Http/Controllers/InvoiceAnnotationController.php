<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceAnnotationController extends Controller
{
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('review', $invoice);

        $validated = $request->validate([
            'strokes' => ['array', 'max:2000'],
            'strokes.*.page' => ['required', 'integer', 'min:1', 'max:500'],
            'strokes.*.tool' => ['nullable', 'in:pen,highlight,rectangle,ellipse,arrow'],
            'strokes.*.color' => ['required', 'string', 'max:20'],
            'strokes.*.width' => ['required', 'numeric', 'min:1', 'max:24'],
            'strokes.*.points' => ['required', 'array', 'min:1', 'max:3000'],
            'strokes.*.points.*.x' => ['required', 'numeric', 'min:0', 'max:1'],
            'strokes.*.points.*.y' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        $annotation = $invoice->annotation()->updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'user_id' => $request->user()->id,
                'data' => [
                    'strokes' => $validated['strokes'] ?? [],
                ],
            ]
        );

        return response()->json([
            'ok' => true,
            'updated_at' => $annotation->updated_at?->toIso8601String(),
        ]);
    }
}

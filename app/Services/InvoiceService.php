<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceProtocolSequence;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function nextProtocol(): string
    {
        return DB::transaction(function (): string {
            $year = (int) now()->format('Y');

            InvoiceProtocolSequence::query()->insertOrIgnore([
                'year' => $year,
                'last_number' => $this->currentMaxProtocolNumber($year),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = InvoiceProtocolSequence::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return sprintf('NF-%s-%06d', $year, $sequence->last_number);
        });
    }

    private function currentMaxProtocolNumber(int $year): int
    {
        return Invoice::query()
            ->where('protocol', 'like', 'NF-'.$year.'-%')
            ->pluck('protocol')
            ->map(function (string $protocol): int {
                if (preg_match('/^NF-\d{4}-(\d+)$/', $protocol, $match)) {
                    return (int) $match[1];
                }

                return 0;
            })
            ->max() ?? 0;
    }
}

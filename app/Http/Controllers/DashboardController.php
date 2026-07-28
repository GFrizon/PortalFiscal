<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $query = Invoice::query();

        if (auth()->user()->isRegularUser()) {
            $query->where('submitted_by', auth()->id());
        }

        $statusCounts = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $monthlyCount = (clone $query)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $totalCount = (clone $query)->count();
        $pendingCount = (int) ($statusCounts[InvoiceStatus::Pending->value] ?? 0);
        $launchedCount = (int) ($statusCounts[InvoiceStatus::Launched->value] ?? 0);
        $launchedRate = $totalCount > 0 ? (int) round(($launchedCount / $totalCount) * 100) : 0;

        $byUnit = (clone $query)
            ->with('businessUnit:id,name')
            ->selectRaw('business_unit_id, count(*) as total')
            ->groupBy('business_unit_id')
            ->get();

        $recentInvoices = (clone $query)
            ->with(['businessUnit:id,name', 'submitter:id,name'])
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard.index', [
            'statuses' => [
                InvoiceStatus::AwaitingReview,
                InvoiceStatus::Pending,
                InvoiceStatus::Launched,
                InvoiceStatus::Cancelled,
            ],
            'statusCounts' => $statusCounts,
            'monthlyCount' => $monthlyCount,
            'totalCount' => $totalCount,
            'pendingCount' => $pendingCount,
            'launchedRate' => $launchedRate,
            'byUnit' => $byUnit,
            'recentInvoices' => $recentInvoices,
        ]);
    }
}

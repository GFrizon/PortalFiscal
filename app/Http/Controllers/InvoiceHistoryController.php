<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Invoice::query()
            ->with([
                'businessUnit:id,name',
                'submitter:id,name',
                'histories' => fn ($historyQuery) => $historyQuery
                    ->with('user:id,name')
                    ->latest()
                    ->limit(1),
            ])
            ->withCount('histories')
            ->withMax('histories', 'created_at')
            ->orderByDesc('histories_max_created_at')
            ->latest('id');

        if ($request->user()->isRegularUser()) {
            $query->where('submitted_by', $request->user()->id);
        }

        if ($request->filled('protocol')) {
            $query->where('protocol', 'like', '%'.$request->string('protocol')->toString().'%');
        }

        if ($request->filled('purchase_order_number')) {
            $query->where('purchase_order_number', 'like', '%'.$request->string('purchase_order_number')->toString().'%');
        }

        if ($request->filled('action')) {
            $query->whereHas('histories', function ($historyQuery) use ($request): void {
                $historyQuery->where('action', 'like', '%'.$request->string('action')->toString().'%');
            });
        }

        if ($request->filled('user_id') && ! $request->user()->isRegularUser()) {
            $query->whereHas('histories', function ($historyQuery) use ($request): void {
                $historyQuery->where('user_id', $request->integer('user_id'));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereHas('histories', function ($historyQuery) use ($request): void {
                $historyQuery->whereDate('created_at', '>=', $request->date('date_from'));
            });
        }

        if ($request->filled('date_to')) {
            $query->whereHas('histories', function ($historyQuery) use ($request): void {
                $historyQuery->whereDate('created_at', '<=', $request->date('date_to'));
            });
        }

        return view('histories.index', [
            'invoices' => $query->paginate(20)->withQueryString(),
            'users' => $request->user()->isRegularUser()
                ? collect()
                : User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['protocol', 'purchase_order_number', 'action', 'user_id', 'date_from', 'date_to']),
        ]);
    }

    public function show(Invoice $invoice, Request $request): View
    {
        $this->authorize('view', $invoice);

        return view('histories.show', [
            'invoice' => $invoice->load([
                'businessUnit:id,name',
                'submitter:id,name',
                'histories.user:id,name',
            ]),
        ]);
    }
}

@extends('layouts.app')

@section('title', 'Historico - BAKOF')
@section('page_title', 'Historico')
@section('page_subtitle', 'Pesquisa de notas com timeline de auditoria')

@section('content')
    <div class="section-toolbar mb-3">
        <div>
            <div class="eyebrow">Auditoria</div>
            <div class="section-title">Historico por nota</div>
        </div>
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Notas
        </a>
    </div>

    <div class="panel filter-panel mb-3">
        <form method="GET" action="{{ route('histories.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="protocol">Protocolo</label>
                <input id="protocol" class="form-control" name="protocol" value="{{ $filters['protocol'] ?? '' }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="purchase_order_number">Ordem de compra</label>
                <input id="purchase_order_number" class="form-control" name="purchase_order_number" value="{{ $filters['purchase_order_number'] ?? '' }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="action">Acao</label>
                <input id="action" class="form-control" name="action" value="{{ $filters['action'] ?? '' }}">
            </div>
            @if(! auth()->user()->isRegularUser())
                <div class="col-12 col-md-3">
                    <label class="form-label" for="user_id">Usuario</label>
                    <select id="user_id" class="form-select" name="user_id">
                        <option value="">Todos os usuarios</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-3">
                <label class="form-label" for="date_from">Data inicial</label>
                <input id="date_from" class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="date_to">Data final</label>
                <input id="date_to" class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-3">
                <div class="filter-actions">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        Pesquisar
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('histories.index') }}">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        Limpar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="panel panel-table">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Protocolo</th>
                    <th>Nota</th>
                    <th>Referencia</th>
                    <th>Unidade</th>
                    <th>Status</th>
                    <th>Ultima acao</th>
                    <th>Eventos</th>
                    <th class="text-end">Acoes</th>
                </tr>
                </thead>
                <tbody>
                @forelse($invoices as $invoice)
                    @php
                        $latestHistory = $invoice->histories->first();
                    @endphp
                    <tr>
                        <td><span class="protocol-code">{{ $invoice->protocol }}</span></td>
                        <td>{{ $invoice->invoice_number ?? '-' }}</td>
                        <td>{{ $invoice->purchase_order_number ?? '-' }}</td>
                        <td>
                            <div class="table-entity">
                                <span class="entity-icon"><i class="bi {{ $invoice->businessUnit ? 'bi-building' : 'bi-building-x' }}" aria-hidden="true"></i></span>
                                <span>{{ $invoice->businessUnit?->name ?? 'Nao identificada' }}</span>
                            </div>
                        </td>
                        <td><span class="badge {{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></td>
                        <td>
                            @if($latestHistory)
                                <div class="fw-semibold">{{ $latestHistory->action }}</div>
                                <div class="small text-secondary">{{ $latestHistory->created_at?->format('d/m/Y H:i') }} - {{ $latestHistory->user?->name ?? 'Sistema' }}</div>
                            @else
                                -
                            @endif
                        </td>
                        <td><span class="count-pill">{{ $invoice->histories_count }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('histories.show', $invoice) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver historico de {{ $invoice->protocol }}">
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty-state">Nenhuma nota encontrada.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-pagination">{{ $invoices->links() }}</div>
    </div>
@endsection

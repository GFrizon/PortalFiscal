@extends('layouts.app')

@section('title', 'Notas fiscais - BAKOF')
@section('page_title', 'Notas fiscais')
@section('page_subtitle', auth()->user()->isRegularUser() ? 'Minhas notas fiscais anexadas' : 'Notas fiscais recebidas')

@section('content')
    @php
        $selectedUnitId = (string) ($filters['business_unit_id'] ?? '');
        $selectedUnitName = 'Todas as unidades';

        if ($selectedUnitId === 'none') {
            $selectedUnitName = 'Nao identificada';
        } elseif ($selectedUnitId !== '') {
            $selectedUnitName = $businessUnits->firstWhere('id', (int) $selectedUnitId)?->name ?? 'Unidade selecionada';
        }

        $sortLink = function (string $column) use ($filters, $sort, $direction) {
            $nextDirection = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';

            return route('invoices.index', array_filter([
                'business_unit_id' => $filters['business_unit_id'] ?? null,
                'protocol' => $filters['protocol'] ?? null,
                'purchase_order_number' => $filters['purchase_order_number'] ?? null,
                'supplier' => $filters['supplier'] ?? null,
                'status' => $filters['status'] ?? null,
                'sort' => $column,
                'direction' => $nextDirection,
            ], fn ($value) => filled($value)));
        };

        $sortIcon = fn (string $column) => $sort === $column
            ? ($direction === 'asc' ? 'bi-chevron-up' : 'bi-chevron-down')
            : 'bi-chevron-expand';
    @endphp

    <div class="invoices-index-page">
    <div class="section-toolbar mb-3">
        <div>
            <div class="eyebrow">Pasta selecionada</div>
            <div class="section-title">{{ $selectedUnitName }}</div>
        </div>
        <div class="toolbar-actions">
            <a href="{{ route('histories.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                Historico
            </a>
            <a href="{{ route('invoices.create') }}" class="btn btn-primary">
                <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                Anexar
            </a>
        </div>
    </div>

    <div class="panel mb-3">
        <div class="panel-header">
            <div>
                <div class="eyebrow">Separacao automatica</div>
                <h2 class="panel-title">Pastas por unidade</h2>
            </div>
            @if($selectedUnitId !== '')
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('invoices.index') }}">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    Ver todas
                </a>
            @endif
        </div>

        <div class="folder-grid">
            @forelse($unitSummary as $item)
                @php
                    $folderValue = $item->business_unit_id ? (string) $item->business_unit_id : 'none';
                    $isActive = $selectedUnitId === $folderValue;
                    $folderRoute = route('invoices.index', array_filter([
                        'business_unit_id' => $folderValue,
                        'status' => $filters['status'] ?? null,
                        'protocol' => $filters['protocol'] ?? null,
                        'purchase_order_number' => $filters['purchase_order_number'] ?? null,
                        'supplier' => $filters['supplier'] ?? null,
                        'sort' => $filters['sort'] ?? null,
                        'direction' => $filters['direction'] ?? null,
                    ], fn ($value) => filled($value)));
                @endphp

                <div>
                    <a class="folder-link {{ $isActive ? 'active' : '' }}" href="{{ $folderRoute }}">
                        <span class="folder-icon">
                            <i class="bi {{ $item->business_unit_id ? 'bi-folder2' : 'bi-folder-x' }}" aria-hidden="true"></i>
                        </span>
                        <span class="folder-copy">
                            <span class="folder-name">{{ $item->businessUnit?->name ?? 'Nao identificada' }}</span>
                            <span class="folder-count">{{ $item->total }} nota{{ $item->total == 1 ? '' : 's' }}</span>
                        </span>
                    </a>
                </div>
            @empty
                <div class="empty-state compact">Nenhuma nota separada por unidade ainda.</div>
            @endforelse
        </div>
    </div>

    <div class="panel filter-panel mb-3">
        <form method="GET" action="{{ route('invoices.index') }}" class="row g-2 align-items-end">
            @if($selectedUnitId !== '')
                <input type="hidden" name="business_unit_id" value="{{ $selectedUnitId }}">
            @endif

            <div class="col-12 col-lg-2">
                <label class="form-label" for="protocol">Protocolo</label>
                <input id="protocol" class="form-control" name="protocol" value="{{ $filters['protocol'] ?? '' }}">
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label" for="purchase_order_number">Ordem de compra</label>
                <input id="purchase_order_number" class="form-control" name="purchase_order_number" value="{{ $filters['purchase_order_number'] ?? '' }}">
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label" for="supplier">Fornecedor</label>
                <input id="supplier" class="form-control" name="supplier" value="{{ $filters['supplier'] ?? '' }}">
            </div>
            <div class="col-12 col-lg-2">
                <label class="form-label" for="status">Status</label>
                <select id="status" class="form-select" name="status">
                    <option value="">Todos os status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-3">
                <div class="filter-actions">
                    <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                        Filtrar
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ $selectedUnitId !== '' ? route('invoices.index', ['business_unit_id' => $selectedUnitId]) : route('invoices.index') }}">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        Limpar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="panel panel-table invoice-list-panel">
        <div class="table-responsive invoice-list-scroll">
            <table class="table data-table align-middle mb-0">
                <thead>
                <tr>
                    <th><a class="sort-link" href="{{ $sortLink('protocol') }}">Protocolo <i class="bi {{ $sortIcon('protocol') }}" aria-hidden="true"></i></a></th>
                    <th><a class="sort-link" href="{{ $sortLink('type') }}">Tipo <i class="bi {{ $sortIcon('type') }}" aria-hidden="true"></i></a></th>
                    <th><a class="sort-link" href="{{ $sortLink('invoice') }}">Nota <i class="bi {{ $sortIcon('invoice') }}" aria-hidden="true"></i></a></th>
                    <th><a class="sort-link" href="{{ $sortLink('reference') }}">Ordem de compra <i class="bi {{ $sortIcon('reference') }}" aria-hidden="true"></i></a></th>
                    <th><a class="sort-link" href="{{ $sortLink('supplier') }}">Fornecedor <i class="bi {{ $sortIcon('supplier') }}" aria-hidden="true"></i></a></th>
                    <th><a class="sort-link" href="{{ $sortLink('unit') }}">Unidade <i class="bi {{ $sortIcon('unit') }}" aria-hidden="true"></i></a></th>
                    <th><a class="sort-link" href="{{ $sortLink('user') }}">Usuario <i class="bi {{ $sortIcon('user') }}" aria-hidden="true"></i></a></th>
                    <th><a class="sort-link" href="{{ $sortLink('arrival') }}">Chegada <i class="bi {{ $sortIcon('arrival') }}" aria-hidden="true"></i></a></th>
                    <th><a class="sort-link" href="{{ $sortLink('due') }}">Vencimento <i class="bi {{ $sortIcon('due') }}" aria-hidden="true"></i></a></th>
                    <th><a class="sort-link" href="{{ $sortLink('status') }}">Status <i class="bi {{ $sortIcon('status') }}" aria-hidden="true"></i></a></th>
                    <th class="text-end">Acoes</th>
                </tr>
                </thead>
                <tbody>
                @forelse($invoices as $invoice)
                    @php($documentType = $invoice->documentType())
                    <tr class="{{ $invoice->is_urgent ? 'invoice-row-urgent' : '' }}">
                        <td>
                            <div class="stacked-cell">
                                <span class="protocol-code">{{ $invoice->protocol }}</span>
                                @if($invoice->is_urgent)
                                    <span class="urgent-badge"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Urgente</span>
                                @endif
                            </div>
                        </td>
                        <td>{{ $documentType->label() }}</td>
                        <td>{{ $invoice->invoice_number ?? '-' }}</td>
                        <td>{{ $invoice->purchase_order_number ?? '-' }}</td>
                        <td>{{ $invoice->purchaseOrderCheck?->supplier_name ?? '-' }}</td>
                        <td>
                            <div class="table-entity">
                                <span class="entity-icon"><i class="bi {{ $invoice->businessUnit ? 'bi-building' : 'bi-building-x' }}" aria-hidden="true"></i></span>
                                <span>{{ $invoice->businessUnit?->name ?? 'Nao identificada' }}</span>
                            </div>
                        </td>
                        <td>{{ $invoice->submitter?->name }}</td>
                        <td>{{ $invoice->arrival_date?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $invoice->due_date?->format('d/m/Y') ?? '-' }}</td>
                        <td><span class="badge {{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary" aria-label="Abrir {{ $invoice->protocol }}">
                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="empty-state">Nenhuma nota nesta pasta.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-pagination">{{ $invoices->links() }}</div>
    </div>
    </div>
@endsection

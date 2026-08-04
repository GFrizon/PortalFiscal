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
                'purchase_order_number' => $filters['purchase_order_number'] ?? null,
                'supplier' => $filters['supplier'] ?? null,
                'status' => $filters['status'] ?? null,
                'sort' => $column,
                'direction' => $nextDirection,
            ], fn ($value) => filled($value)));
        };

        $sortIcon = fn (string $column) => $sort === $column
            ? ($direction === 'asc' ? 'bi-arrow-up-short' : 'bi-arrow-down-short')
            : 'bi-chevron-expand';
        $sortClass = fn (string $column) => $sort === $column
            ? 'sort-link is-sorted is-' . $direction
            : 'sort-link';
        $sortAria = fn (string $column) => $sort === $column
            ? ($direction === 'asc' ? 'ascending' : 'descending')
            : 'none';
        $sortTitle = fn (string $column, string $label) => $sort === $column
            ? $label . ' ordenado em ordem ' . ($direction === 'asc' ? 'crescente' : 'decrescente')
            : 'Ordenar por ' . $label;
    @endphp

    <div class="invoices-index-page">
    <div class="section-toolbar mb-3">
        <div>
            <div class="eyebrow">Pasta selecionada</div>
            <div class="section-title">{{ $selectedUnitName }}</div>
        </div>
        <form method="GET" action="{{ route('invoices.index') }}" class="invoice-toolbar-filter" aria-label="Filtros da lista de notas">
            @if($selectedUnitId !== '')
                <input type="hidden" name="business_unit_id" value="{{ $selectedUnitId }}">
            @endif

            <input class="form-control" name="purchase_order_number" value="{{ $filters['purchase_order_number'] ?? '' }}" placeholder="OC/CTE" aria-label="Filtrar por OC ou CTE">
            <input class="form-control" name="supplier" value="{{ $filters['supplier'] ?? '' }}" placeholder="Fornecedor" aria-label="Filtrar por fornecedor">
            <select class="form-select" name="status" aria-label="Filtrar por status">
                <option value="">Fila aberta</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status === \App\Enums\InvoiceStatus::Launched ? 'Lancadas' : $status->label() }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-primary btn-sm" type="submit">
                <i class="bi bi-funnel" aria-hidden="true"></i>
                Filtrar
            </button>
            <a class="btn btn-outline-secondary btn-sm" href="{{ $selectedUnitId !== '' ? route('invoices.index', ['business_unit_id' => $selectedUnitId]) : route('invoices.index') }}">
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            </a>
        </form>
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

    <div class="panel invoice-folder-panel mb-3">
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

    <div class="panel panel-table invoice-list-panel">
        <div class="invoice-list-heading">
            <div>
                <span class="eyebrow">Fila de conferencia</span>
                <strong>Notas da pasta {{ $selectedUnitName }}</strong>
            </div>
            <span>{{ $invoices->firstItem() ?? 0 }}-{{ $invoices->lastItem() ?? 0 }} de {{ $invoices->total() }}</span>
        </div>
        <div class="table-responsive invoice-list-scroll">
            <table class="table data-table align-middle mb-0">
                <thead>
                <tr>
                    <th class="col-priority" aria-label="Prioridade"></th>
                    <th class="col-type" aria-sort="{{ $sortAria('type') }}"><a class="{{ $sortClass('type') }}" href="{{ $sortLink('type') }}" title="{{ $sortTitle('type', 'Tipo') }}">Tipo <i class="bi {{ $sortIcon('type') }}" aria-hidden="true"></i></a></th>
                    <th class="col-invoice" aria-sort="{{ $sortAria('invoice') }}"><a class="{{ $sortClass('invoice') }}" href="{{ $sortLink('invoice') }}" title="{{ $sortTitle('invoice', 'Nota') }}">Nota <i class="bi {{ $sortIcon('invoice') }}" aria-hidden="true"></i></a></th>
                    <th aria-sort="{{ $sortAria('reference') }}"><a class="{{ $sortClass('reference') }}" href="{{ $sortLink('reference') }}" title="{{ $sortTitle('reference', 'OC/CTE') }}">OC/CTE <i class="bi {{ $sortIcon('reference') }}" aria-hidden="true"></i></a></th>
                    <th class="col-supplier" aria-sort="{{ $sortAria('supplier') }}"><a class="{{ $sortClass('supplier') }}" href="{{ $sortLink('supplier') }}" title="{{ $sortTitle('supplier', 'Fornecedor') }}">Fornecedor <i class="bi {{ $sortIcon('supplier') }}" aria-hidden="true"></i></a></th>
                    <th class="col-unit" aria-sort="{{ $sortAria('unit') }}"><a class="{{ $sortClass('unit') }}" href="{{ $sortLink('unit') }}" title="{{ $sortTitle('unit', 'Unidade') }}">Unidade <i class="bi {{ $sortIcon('unit') }}" aria-hidden="true"></i></a></th>
                    <th class="col-user" aria-sort="{{ $sortAria('user') }}"><a class="{{ $sortClass('user') }}" href="{{ $sortLink('user') }}" title="{{ $sortTitle('user', 'Usuario') }}">Usuario <i class="bi {{ $sortIcon('user') }}" aria-hidden="true"></i></a></th>
                    <th class="col-created" aria-sort="{{ $sortAria('created') }}"><a class="{{ $sortClass('created') }}" href="{{ $sortLink('created') }}" title="{{ $sortTitle('created', 'Inclusao') }}">Inclusao <i class="bi {{ $sortIcon('created') }}" aria-hidden="true"></i></a></th>
                    <th class="col-arrival" aria-sort="{{ $sortAria('arrival') }}"><a class="{{ $sortClass('arrival') }}" href="{{ $sortLink('arrival') }}" title="{{ $sortTitle('arrival', 'Chegada') }}">Chegada <i class="bi {{ $sortIcon('arrival') }}" aria-hidden="true"></i></a></th>
                    <th aria-sort="{{ $sortAria('due') }}"><a class="{{ $sortClass('due') }}" href="{{ $sortLink('due') }}" title="{{ $sortTitle('due', 'Vencimento') }}">Vencimento <i class="bi {{ $sortIcon('due') }}" aria-hidden="true"></i></a></th>
                    <th aria-sort="{{ $sortAria('status') }}"><a class="{{ $sortClass('status') }}" href="{{ $sortLink('status') }}" title="{{ $sortTitle('status', 'Status') }}">Status <i class="bi {{ $sortIcon('status') }}" aria-hidden="true"></i></a></th>
                    <th class="text-end">Acoes</th>
                </tr>
                </thead>
                <tbody>
                @forelse($invoices as $invoice)
                    @php($documentType = $invoice->documentType())
                    <tr @class([
                        'invoice-row-urgent' => $invoice->is_urgent,
                        'invoice-row-launched' => $invoice->status === \App\Enums\InvoiceStatus::Launched,
                    ])>
                        <td class="col-priority {{ $invoice->is_urgent || $invoice->status === \App\Enums\InvoiceStatus::Launched ? '' : 'priority-empty' }}" data-label="Prioridade">
                            @if($invoice->status === \App\Enums\InvoiceStatus::Launched)
                                <span class="launched-indicator" title="Lancada" aria-label="Lancada">
                                    <i class="bi bi-archive" aria-hidden="true"></i>
                                    <span class="visually-hidden">Lancada</span>
                                </span>
                            @elseif($invoice->is_urgent)
                                <span class="urgent-indicator" title="Urgente" aria-label="Urgente">
                                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                    <span class="visually-hidden">Urgente</span>
                                </span>
                            @endif
                        </td>
                        <td class="col-type" data-label="Tipo"><span class="document-type-pill">{{ $documentType->label() }}</span></td>
                        <td class="col-invoice" data-label="Nota"><span class="invoice-number-pill">{{ $invoice->invoice_number ?? '-' }}</span></td>
                        <td data-label="OC/CTE"><span class="reference-code">{{ $invoice->purchase_order_number ?? '-' }}</span></td>
                        <td class="col-supplier" data-label="Fornecedor">
                            <div class="supplier-cell">
                                <strong>{{ $invoice->purchaseOrderCheck?->supplier_name ?? '-' }}</strong>
                                <span>{{ $invoice->protocol }}</span>
                            </div>
                        </td>
                        <td class="col-unit" data-label="Unidade">
                            <div class="table-entity">
                                <span class="entity-icon"><i class="bi {{ $invoice->businessUnit ? 'bi-building' : 'bi-building-x' }}" aria-hidden="true"></i></span>
                                <span>{{ $invoice->businessUnit?->name ?? 'Nao identificada' }}</span>
                            </div>
                        </td>
                        <td class="col-user" data-label="Usuario"><span class="muted-cell">{{ $invoice->submitter?->name }}</span></td>
                        <td class="col-created" data-label="Inclusao">{{ $invoice->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="col-arrival" data-label="Chegada">{{ $invoice->arrival_date?->format('d/m/Y') ?? '-' }}</td>
                        <td data-label="Vencimento">{{ $invoice->due_date?->format('d/m/Y') ?? '-' }}</td>
                        <td data-label="Status">
                            <div class="status-cell">
                                <span class="badge {{ $invoice->status->badgeClass() }}" title="{{ $invoice->status->label() }}">{{ $invoice->status->shortLabel() }}</span>
                            </div>
                        </td>
                        <td class="text-end row-actions" data-label="Acoes">
                            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary btn-open-invoice" aria-label="Abrir {{ $invoice->protocol }}">
                                Abrir
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="empty-state">Nenhuma nota nesta pasta.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-pagination invoice-pagination">
            <span>Paginas</span>
            {{ $invoices->links() }}
        </div>
    </div>
    </div>
@endsection

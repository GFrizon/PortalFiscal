@extends('layouts.app')

@section('title', 'Dashboard - BAKOF')
@section('page_title', 'Dashboard')
@section('page_subtitle', 'Resumo operacional das notas fiscais')

@section('content')
    @php
        $statusIcons = [
            'awaiting_review' => 'bi-hourglass-split',
            'in_review' => 'bi-search',
            'pending' => 'bi-exclamation-triangle',
            'launched' => 'bi-check2-circle',
            'cancelled' => 'bi-x-circle',
        ];
    @endphp

    <div class="dashboard-hero mb-3">
        <div class="dashboard-hero-main">
            <div>
                <div class="eyebrow">Operacao fiscal</div>
                <h2>Visao operacional</h2>
                <p>Volume, pendencias e lancamentos em acompanhamento.</p>
            </div>
            <div class="hero-kpis" aria-label="Resumo de notas fiscais">
                <div>
                    <span>Total</span>
                    <strong>{{ $totalCount }}</strong>
                </div>
                <div>
                    <span>Pendencias</span>
                    <strong>{{ $pendingCount }}</strong>
                </div>
                <div>
                    <span>Lancadas</span>
                    <strong>{{ $launchedRate }}%</strong>
                </div>
            </div>
        </div>
        <div class="dashboard-actions">
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                Ver notas
            </a>
            <a href="{{ route('invoices.create') }}" class="btn btn-primary">
                <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                Anexar
            </a>
        </div>
    </div>

    <div class="metrics-grid mb-3">
        @foreach($statuses as $status)
            <div>
                <div class="metric-card metric-{{ $status->value }}">
                    <div class="metric-icon">
                        <i class="bi {{ $statusIcons[$status->value] ?? 'bi-receipt' }}" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="metric-label">{{ $status->label() }}</div>
                        <div class="metric-value">{{ $statusCounts[$status->value] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        @endforeach
        <div>
            <div class="metric-card metric-month">
                <div class="metric-icon">
                    <i class="bi bi-calendar2-week" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="metric-label">Enviadas no mes</div>
                    <div class="metric-value">{{ $monthlyCount }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel panel-table">
            <div class="panel-header panel-header-spaced">
                <div>
                    <div class="eyebrow">Unidades</div>
                    <h2 class="panel-title">Notas por unidade</h2>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table data-table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Unidade</th>
                        <th class="text-end">Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($byUnit as $item)
                        <tr>
                            <td>
                                <div class="table-entity">
                                    <span class="entity-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
                                    <span>{{ $item->businessUnit?->name ?? 'Nao identificada' }}</span>
                                </div>
                            </td>
                            <td class="text-end"><span class="count-pill">{{ $item->total }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="empty-state">Nenhuma nota cadastrada ainda.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel panel-table">
            <div class="panel-header panel-header-spaced">
                <div>
                    <div class="eyebrow">Entrada recente</div>
                    <h2 class="panel-title">Ultimas notas</h2>
                </div>
                <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    Todas
                </a>
            </div>
            <div class="table-responsive">
                <table class="table data-table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Protocolo</th>
                        <th>Status</th>
                        <th class="text-end">Abrir</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recentInvoices as $invoice)
                        <tr>
                            <td>
                                <div class="stacked-cell">
                                    <span class="protocol-code">{{ $invoice->protocol }}</span>
                                    <small>{{ $invoice->businessUnit?->name ?? 'Nao identificada' }}</small>
                                </div>
                            </td>
                            <td><span class="badge {{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary" aria-label="Abrir {{ $invoice->protocol }}">
                                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="empty-state">Nenhuma nota recente.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

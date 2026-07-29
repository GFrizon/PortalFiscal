@extends('layouts.app')

@section('title', 'Historico '.$invoice->protocol.' - BAKOF')
@section('page_title', 'Historico '.$invoice->protocol)
@section('page_subtitle', 'Timeline completa da nota fiscal')

@section('content')
    @php($documentType = $invoice->documentType())

    <div class="section-toolbar mb-3">
        <div>
            <div class="eyebrow">Auditoria da nota</div>
            <div class="section-title">{{ $invoice->protocol }}</div>
        </div>
        <div class="toolbar-actions">
            <a href="{{ route('histories.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Voltar
            </a>
            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-outline-primary">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                Nota
            </a>
        </div>
    </div>

    <div class="panel mb-3">
        <div class="panel-header">
            <div>
                <div class="eyebrow">Resumo</div>
                <h2 class="panel-title">Dados da nota</h2>
            </div>
            <span class="badge {{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span>
        </div>
        <div class="summary-card-grid">
            <div>
                <span>Nota</span>
                <strong>{{ $invoice->invoice_number ?? '-' }}</strong>
            </div>
            <div>
                <span>{{ $documentType->referenceLabel() }}</span>
                <strong>{{ $invoice->purchase_order_number ?? '-' }}</strong>
            </div>
            <div>
                <span>Unidade</span>
                <strong>{{ $invoice->businessUnit?->name ?? 'Nao identificada' }}</strong>
            </div>
            <div>
                <span>Enviado por</span>
                <strong>{{ $invoice->submitter?->name ?? '-' }}</strong>
            </div>
            <div>
                <span>Chegada</span>
                <strong>{{ $invoice->arrival_date?->format('d/m/Y') ?? '-' }}</strong>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="eyebrow">{{ $invoice->histories->count() }} evento{{ $invoice->histories->count() === 1 ? '' : 's' }}</div>
                <h2 class="panel-title">Timeline</h2>
            </div>
        </div>

        <div class="timeline">
            @forelse($invoice->histories->sortByDesc('created_at') as $history)
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="fw-semibold">{{ $history->action }}</div>
                    <div class="small text-secondary">{{ $history->created_at?->format('d/m/Y H:i') }} - {{ $history->user?->name ?? 'Sistema' }}</div>
                    @if($history->previous_status || $history->new_status)
                        <div class="small history-status">
                            {{ $history->previous_status?->label() ?? '-' }}
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            {{ $history->new_status?->label() ?? '-' }}
                        </div>
                    @endif
                    @if($history->note)
                        <div class="small">{{ $history->note }}</div>
                    @endif
                </div>
            @empty
                <div class="empty-state compact">Nenhum historico registrado para esta nota.</div>
            @endforelse
        </div>
    </div>
@endsection

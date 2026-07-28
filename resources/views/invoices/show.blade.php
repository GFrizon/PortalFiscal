@extends('layouts.app')

@section('title', $invoice->protocol.' - BAKOF')
@section('page_title', $invoice->protocol)
@section('page_subtitle', 'Detalhes da nota fiscal')

@section('content')
    <div class="invoice-detail-page">
    <div class="section-toolbar mb-3">
        <div>
            <div class="eyebrow">Navegacao</div>
            <div class="section-title">Detalhes e conferencia</div>
        </div>
        <div class="toolbar-actions">
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Voltar
            </a>
            <a href="{{ route('invoices.create') }}" class="btn btn-outline-primary">
                <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                Anexar
            </a>
            @can('delete', $invoice)
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" class="d-inline" data-confirm="Excluir esta nota e remover o PDF anexado?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                        Excluir
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="invoice-workspace">
        <div class="col-12 col-xl-7">
            <div class="panel pdf-panel mb-3">
                <div class="panel-header">
                    <div>
                        <div class="eyebrow">Documento</div>
                        <h2 class="panel-title">PDF da nota fiscal</h2>
                    </div>
                    <div class="toolbar-actions">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('invoices.pdf.show', $invoice) }}" target="_blank" aria-label="Abrir PDF em nova aba">
                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('invoices.pdf.download', $invoice) }}" aria-label="Baixar PDF">
                            <i class="bi bi-download" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
                <iframe src="{{ route('invoices.pdf.show', $invoice) }}" class="pdf-frame"></iframe>
            </div>
        </div>

        <div class="col-12 col-xl-5 review-column">
            <div class="panel mb-3">
                <div class="panel-header">
                    <div>
                        <div class="eyebrow">Protocolo {{ $invoice->protocol }}</div>
                        <h2 class="panel-title">Dados da nota</h2>
                    </div>
                </div>
                <div class="invoice-summary-strip mb-3">
                    <div>
                        <span>Status</span>
                        <strong><span class="badge {{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></strong>
                    </div>
                    <div>
                        <span>Nota</span>
                        <strong>{{ $invoice->invoice_number ?? '-' }}</strong>
                    </div>
                    <div>
                        <span>Ordem</span>
                        <strong>{{ $invoice->purchase_order_number ?? '-' }}</strong>
                    </div>
                </div>

                <dl class="details-grid mb-0">
                    <dt class="col-sm-5">Unidade</dt>
                    <dd class="col-sm-7">{{ $invoice->businessUnit?->name ?? 'Nao identificada' }}</dd>
                    <dt class="col-sm-5">Enviado por</dt>
                    <dd class="col-sm-7">{{ $invoice->submitter?->name }}</dd>
                    <dt class="col-sm-5">Chegada</dt>
                    <dd class="col-sm-7">{{ $invoice->arrival_date?->format('d/m/Y') ?? '-' }}</dd>
                    <dt class="col-sm-5">Vencimento</dt>
                    <dd class="col-sm-7">{{ $invoice->due_date?->format('d/m/Y') ?? '-' }}</dd>
                    <dt class="col-sm-5">Emitente</dt>
                    <dd class="col-sm-7">{{ $invoice->issuer_cnpj ?? '-' }}</dd>
                    <dt class="col-sm-5">Destinatario</dt>
                    <dd class="col-sm-7">{{ $invoice->recipient_cnpj ?? '-' }}</dd>
                    <dt class="col-sm-5">Fiscal responsavel</dt>
                    <dd class="col-sm-7">{{ $invoice->fiscalUser?->name ?? '-' }}</dd>
                    <dt class="col-sm-5">Lancamento</dt>
                    <dd class="col-sm-7">{{ $invoice->launched_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                </dl>

                <div class="note-box mt-3">
                    <div class="note-title">
                        <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                        Observacoes de {{ $invoice->submitter?->name ?? 'Usuario' }}
                    </div>
                    @if(filled($invoice->user_notes))
                        <div class="text-body">{{ $invoice->user_notes }}</div>
                    @else
                        <div class="text-secondary">Nenhuma observacao informada.</div>
                    @endif
                </div>

                @if(filled($invoice->fiscal_notes))
                    <div class="note-box mt-3">
                        <div class="note-title">
                            <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                            Observacoes do Fiscal
                        </div>
                        <div class="text-body">{{ $invoice->fiscal_notes }}</div>
                    </div>
                @endif

                @if($invoice->purchaseOrderCheck)
                    <div class="po-summary mt-3 mb-0">
                        <div class="po-summary-header">
                            <span>
                                <i class="bi bi-database-check" aria-hidden="true"></i>
                                Ordem de compra no CIGAM
                            </span>
                            <span class="badge {{ $invoice->purchaseOrderCheck->order_exists ? 'text-bg-primary' : 'text-bg-warning' }}">
                                {{ $invoice->purchaseOrderCheck->order_exists ? ($invoice->purchaseOrderCheck->status ?? 'Encontrada') : 'Nao encontrada' }}
                            </span>
                        </div>
                        <div class="po-summary-grid">
                            <div>
                                <span>Situacao</span>
                                <strong>{{ $invoice->purchaseOrderCheck->order_exists ? ($invoice->purchaseOrderCheck->status ?? '-') : 'Nao encontrada' }}</strong>
                            </div>
                            <div>
                                <span>Numero no ERP</span>
                                <strong>{{ data_get($invoice->purchaseOrderCheck->raw_response, 'purchase_order_number', $invoice->purchase_order_number) }}</strong>
                            </div>
                            <div>
                                <span>Fornecedor</span>
                                <strong>{{ $invoice->purchaseOrderCheck->supplier_name ?? '-' }}</strong>
                            </div>
                            <div>
                                <span>CNPJ fornecedor</span>
                                <strong>{{ $invoice->purchaseOrderCheck->supplier_cnpj ?? '-' }}</strong>
                            </div>
                            <div>
                                <span>Valor</span>
                                <strong>{{ $invoice->purchaseOrderCheck->amount !== null ? 'R$ '.number_format((float) $invoice->purchaseOrderCheck->amount, 2, ',', '.') : '-' }}</strong>
                            </div>
                            <div>
                                <span>Codigo fornecedor</span>
                                <strong>{{ data_get($invoice->purchaseOrderCheck->raw_response, 'supplier_code', '-') }}</strong>
                            </div>
                            <div>
                                <span>Sistema origem</span>
                                <strong>CIGAM</strong>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="panel">
                <ul class="nav nav-pills review-tabs" id="invoiceReviewTabs" role="tablist">
                    @can('review', $invoice)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="review-tab" data-bs-toggle="tab" data-bs-target="#review-tab-pane" type="button" role="tab" aria-controls="review-tab-pane" aria-selected="true">Conferencia</button>
                        </li>
                    @endcan
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @cannot('review', $invoice) active @endcannot" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts-tab-pane" type="button" role="tab" aria-controls="alerts-tab-pane" aria-selected="@cannot('review', $invoice) true @else false @endcannot">Alertas</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-tab-pane" type="button" role="tab" aria-controls="history-tab-pane" aria-selected="false">Historico</button>
                    </li>
                </ul>

                <div class="tab-content review-tab-content" id="invoiceReviewTabsContent">
                    @can('review', $invoice)
                        <div class="tab-pane fade show active" id="review-tab-pane" role="tabpanel" aria-labelledby="review-tab" tabindex="0">
                            @if($invoice->alerts->isNotEmpty())
                                <div class="inline-alerts mb-3">
                                    @foreach($invoice->alerts as $alert)
                                        <div class="inline-alert {{ $alert->level->value === 'critical' ? 'critical' : 'warning' }}">
                                            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                                            <div>
                                                <strong>{{ $alert->type->label() }}{{ $alert->resolved ? ' - Resolvido' : '' }}</strong>
                                                <span>{{ $alert->message }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <form method="POST" action="{{ route('invoices.unit.update', $invoice) }}" class="mb-3">
                                @csrf
                                @method('PATCH')
                                <label class="form-label" for="business_unit_id">Unidade de negocio</label>
                                <div class="input-group">
                                    <select class="form-select" id="business_unit_id" name="business_unit_id" required>
                                        @foreach($businessUnits as $unit)
                                            <option value="{{ $unit->id }}" @selected($invoice->business_unit_id === $unit->id)>{{ $unit->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-outline-primary" type="submit">Atualizar</button>
                                </div>
                            </form>

                            @if($invoice->status === \App\Enums\InvoiceStatus::Launched)
                                <div class="alert alert-success mb-0">
                                    Nota lancada por {{ $invoice->fiscalUser?->name ?? 'Fiscal' }} em {{ $invoice->launched_at?->format('d/m/Y H:i') }}.
                                </div>
                            @elseif($invoice->status === \App\Enums\InvoiceStatus::Cancelled)
                                <div class="alert alert-danger mb-0">
                                    Nota cancelada. {{ filled($invoice->fiscal_notes) ? $invoice->fiscal_notes : '' }}
                                </div>
                            @else
                                <form method="POST" action="{{ route('invoices.mark-as-launched', $invoice) }}">
                                    @csrf
                                    <label class="form-label" for="fiscal_notes">Observacoes do Fiscal</label>
                                    <textarea class="form-control mb-3" id="fiscal_notes" name="fiscal_notes" rows="3">{{ old('fiscal_notes', $invoice->fiscal_notes) }}</textarea>
                                    <div class="fiscal-action-grid">
                                        <button class="btn btn-warning" type="submit" formaction="{{ route('invoices.mark-as-pending', $invoice) }}">
                                            <i class="bi bi-exclamation-diamond" aria-hidden="true"></i>
                                            Pendencia
                                        </button>
                                        <button class="btn btn-success" type="submit" data-confirm="Marcar esta nota como lancada?">
                                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                            Lancar
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endcan

                    <div class="tab-pane fade @cannot('review', $invoice) show active @endcannot" id="alerts-tab-pane" role="tabpanel" aria-labelledby="alerts-tab" tabindex="0">
                        @forelse($invoice->alerts as $alert)
                            <div class="alert alert-card {{ $alert->level->value === 'critical' ? 'alert-danger' : 'alert-warning' }} mb-2">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                <div class="w-100">
                                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start">
                                        <div><strong>{{ $alert->type->label() }}:</strong> {{ $alert->message }}</div>
                                        @if($alert->resolved)
                                            <span class="badge text-bg-success">Resolvido</span>
                                        @else
                                            <span class="badge text-bg-secondary">Aberto</span>
                                        @endif
                                    </div>
                                    @can('review', $invoice)
                                        @if(! $alert->resolved)
                                            <form method="POST" action="{{ route('invoices.alerts.resolve', [$invoice, $alert]) }}" class="mt-2">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success" type="submit">
                                                    <i class="bi bi-check2" aria-hidden="true"></i>
                                                    Resolver alerta
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <p class="empty-state compact">Nenhum alerta registrado.</p>
                        @endforelse
                    </div>

                    <div class="tab-pane fade" id="history-tab-pane" role="tabpanel" aria-labelledby="history-tab" tabindex="0">
                        <div class="timeline">
                            @forelse($invoice->histories->sortByDesc('created_at') as $history)
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="fw-semibold">{{ $history->action }}</div>
                                    <div class="small text-secondary">{{ $history->created_at?->format('d/m/Y H:i') }} - {{ $history->user?->name ?? 'Sistema' }}</div>
                                    @if($history->note)
                                        <div class="small">{{ $history->note }}</div>
                                    @endif
                                </div>
                            @empty
                                <div class="empty-state compact">Nenhum historico registrado.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection

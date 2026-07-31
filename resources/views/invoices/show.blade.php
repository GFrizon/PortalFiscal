@extends('layouts.app')

@section('title', $invoice->protocol.' - BAKOF')
@section('page_title', $invoice->protocol)
@section('page_subtitle', 'Detalhes da nota fiscal')
@section('hide_topbar', true)

@section('content')
    @php($documentType = $invoice->documentType())
    @php($paymentMethod = $invoice->paymentMethod())
    @php($annotationData = $invoice->annotation?->data ?? ['strokes' => []])
    @php($canReviewInvoice = auth()->user()?->can('review', $invoice) ?? false)
    @php($isPendingForSubmitter = $invoice->status === \App\Enums\InvoiceStatus::Pending && ! $canReviewInvoice)
    @php($purchaseOrderCheck = $invoice->purchaseOrderCheck)
    @php($supplierName = $purchaseOrderCheck?->supplier_name ?? '-')
    @php($invoiceAmount = $purchaseOrderCheck?->amount !== null ? 'R$ '.number_format((float) $purchaseOrderCheck->amount, 2, ',', '.') : '-')
    @php($installments = collect($invoice->payment_installments ?? [])->sortBy('due_date')->values())
    @php($firstInstallment = $installments->first())
    @php($otherInstallments = $installments->slice(1)->values())
    @php($dueSummary = $paymentMethod->requiresInstallments()
        ? (filled($firstInstallment['due_date'] ?? null) ? \Illuminate\Support\Carbon::parse($firstInstallment['due_date'])->format('d/m/Y') : '-')
        : $paymentMethod->label())
    @php($supplierCode = $purchaseOrderCheck
        ? (data_get($purchaseOrderCheck->raw_response, 'supplier_code')
            ?? data_get($purchaseOrderCheck->raw_response, 'codigo_fornecedor')
            ?? data_get($purchaseOrderCheck->raw_response, 'cod_fornecedor')
            ?? data_get($purchaseOrderCheck->raw_response, 'fornecedor_codigo')
            ?? data_get($purchaseOrderCheck->raw_response, 'cd_fornecedor'))
        : null)

    <div class="invoice-detail-page {{ $isPendingForSubmitter ? 'has-pending-callout' : '' }}">
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
                @can('update', $invoice)
                    <a href="{{ route('invoices.edit', $invoice) }}" class="btn {{ $isPendingForSubmitter ? 'btn-warning' : 'btn-outline-primary' }}">
                        <i class="bi {{ $isPendingForSubmitter ? 'bi-reply' : 'bi-pencil-square' }}" aria-hidden="true"></i>
                        {{ $isPendingForSubmitter ? 'Responder pendencia' : 'Editar' }}
                    </a>
                @endcan
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

        @if($isPendingForSubmitter)
            <div class="pending-response-callout mb-3">
                <div>
                    <span class="eyebrow">Pendencia registrada</span>
                    <strong>{{ filled($invoice->fiscal_notes) ? $invoice->fiscal_notes : 'Revise os dados solicitados pelo fiscal.' }}</strong>
                    <small>Para resolver, use o botao amarelo acima, ajuste as informacoes e salve. A nota volta automaticamente para a conferencia e o fiscal recebe um aviso por e-mail.</small>
                </div>
            </div>
        @endif

        <div class="invoice-workspace invoice-workspace-balanced">
            <div class="invoice-pdf-column">
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
                    <div
                        class="pdf-annotation-viewer"
                        data-pdf-annotator
                        data-pdf-url="{{ route('invoices.pdf.show', $invoice) }}"
                        data-save-url="{{ route('invoices.annotations.update', $invoice) }}"
                        data-can-annotate="@can('review', $invoice) true @else false @endcan"
                        data-annotations='@json($annotationData)'
                    >
                        @can('review', $invoice)
                            <div class="pdf-annotation-toolbar" aria-label="Ferramentas de anotacao">
                                <button class="btn btn-sm btn-primary active" type="button" data-annotation-tool="pen" aria-pressed="true">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                    Caneta
                                </button>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-annotation-tool="highlight" aria-pressed="false">
                                    <i class="bi bi-highlighter" aria-hidden="true"></i>
                                    Marca texto
                                </button>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-annotation-tool="eraser" aria-pressed="false">
                                    <i class="bi bi-eraser" aria-hidden="true"></i>
                                    Borracha
                                </button>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-annotation-tool="rectangle" aria-pressed="false">
                                    <i class="bi bi-bounding-box" aria-hidden="true"></i>
                                    Retangulo
                                </button>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-annotation-tool="ellipse" aria-pressed="false">
                                    <i class="bi bi-circle" aria-hidden="true"></i>
                                    Circulo
                                </button>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-annotation-tool="arrow" aria-pressed="false">
                                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                                    Seta
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-annotation-undo>
                                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                    Desfazer
                                </button>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-annotation-clear>
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                    Limpar
                                </button>
                                <button class="btn btn-sm btn-success" type="button" data-annotation-save>
                                    <i class="bi bi-cloud-check" aria-hidden="true"></i>
                                    Salvar rabiscos
                                </button>
                                <span class="annotation-status" data-annotation-status></span>
                            </div>
                        @else
                            <div class="annotation-status mb-2">PDF com anotacoes da conferencia, quando existirem.</div>
                        @endcan
                        <div class="pdf-pages" data-pdf-pages>
                            <div class="pdf-loading-state">
                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                                Carregando PDF...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="review-column invoice-info-column">
                <div class="panel invoice-overview-panel mb-3">
                    <div class="panel-header">
                        <div>
                            <div class="eyebrow">Protocolo {{ $invoice->protocol }}</div>
                            <h2 class="panel-title">Conferencia de documentos</h2>
                        </div>
                    </div>

                    @if($documentType === \App\Enums\InvoiceDocumentType::Nf && $purchaseOrderCheck)
                        <section class="document-compare-card mb-3" aria-label="Comparacao entre nota fiscal e ordem de compra">
                            <div class="compare-document compare-document-nf">
                                <div class="compare-document-header">
                                    <div>
                                        <span class="compare-kicker"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Nota fiscal</span>
                                        <strong>{{ $invoice->invoice_number ?? '-' }}</strong>
                                    </div>
                                    <span class="badge {{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span>
                                </div>
                                <dl class="compare-list">
                                    <div>
                                        <dt>{{ $documentType->referenceLabel() }}</dt>
                                        <dd>{{ $invoice->purchase_order_number ?? '-' }}</dd>
                                    </div>
                                    <div class="compare-row-emphasis">
                                        <dt>Fornecedor</dt>
                                        <dd>{{ $supplierName }}</dd>
                                    </div>
                                    <div class="compare-row-emphasis">
                                        <dt>Valor</dt>
                                        <dd>{{ $invoiceAmount }}</dd>
                                    </div>
                                    <div>
                                        <dt>Vencimento</dt>
                                        <dd>{{ $dueSummary }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="compare-divider" aria-hidden="true">
                                <span></span>
                                <i class="bi bi-arrow-left-right"></i>
                                <span></span>
                            </div>

                            <div class="compare-document compare-document-cigam">
                                <div class="compare-document-header">
                                    <div>
                                        <span class="compare-kicker"><i class="bi bi-database-check" aria-hidden="true"></i> Ordem de compra no CIGAM</span>
                                        <strong>{{ data_get($purchaseOrderCheck->raw_response, 'purchase_order_number', $invoice->purchase_order_number) }}</strong>
                                    </div>
                                    <span class="badge {{ $purchaseOrderCheck->order_exists ? 'text-bg-primary' : 'text-bg-warning' }}">
                                        {{ $purchaseOrderCheck->order_exists ? ($purchaseOrderCheck->status ?? 'Encontrada') : 'Nao encontrada' }}
                                    </span>
                                </div>
                                <dl class="compare-list">
                                    <div>
                                        <dt>Codigo fornecedor</dt>
                                        <dd>{{ filled($supplierCode) ? $supplierCode : '-' }}</dd>
                                    </div>
                                    <div class="compare-row-emphasis">
                                        <dt>Fornecedor</dt>
                                        <dd>{{ $purchaseOrderCheck->supplier_name ?? '-' }}</dd>
                                    </div>
                                    <div class="compare-row-emphasis">
                                        <dt>Valor</dt>
                                        <dd>{{ $purchaseOrderCheck->amount !== null ? 'R$ '.number_format((float) $purchaseOrderCheck->amount, 2, ',', '.') : '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt>CNPJ</dt>
                                        <dd>{{ $purchaseOrderCheck->supplier_cnpj ?? '-' }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </section>
                    @else
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
                                <span>{{ $documentType->referenceLabel() }}</span>
                                <strong>{{ $invoice->purchase_order_number ?? '-' }}</strong>
                            </div>
                            <div>
                                <span>Fornecedor</span>
                                <strong>{{ $supplierName }}</strong>
                            </div>
                            <div>
                                <span>Valor</span>
                                <strong>{{ $invoiceAmount }}</strong>
                            </div>
                            <div>
                                <span>Vencimento</span>
                                <strong>{{ $dueSummary }}</strong>
                            </div>
                        </div>
                    @endif

                        <div class="invoice-details-and-attachments">
                        <section class="invoice-info-module">
                            <div class="module-heading">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                Informacoes
                            </div>
                            <dl class="details-grid mb-0">
                            <dt>Unidade</dt>
                            <dd>{{ $invoice->businessUnit?->name ?? 'Nao identificada' }}</dd>
                            <dt>Tipo</dt>
                            <dd>{{ $documentType->label() }}</dd>
                            <dt>Enviado por</dt>
                            <dd>{{ $invoice->submitter?->name }}</dd>
                            <dt>Chegada</dt>
                            <dd>{{ $invoice->arrival_date?->format('d/m/Y') ?? '-' }}</dd>
                            <dt>Vencimento</dt>
                            <dd>{{ $paymentMethod->label() }}</dd>
                            <dt>Emitente</dt>
                            <dd>{{ $invoice->issuer_cnpj ?? '-' }}</dd>
                            <dt>Destinatario</dt>
                            <dd>{{ $invoice->recipient_cnpj ?? '-' }}</dd>
                            <dt>Fiscal responsavel</dt>
                            <dd>{{ $invoice->fiscalUser?->name ?? '-' }}</dd>
                            <dt>Lancamento</dt>
                            <dd>{{ $invoice->launched_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                            </dl>
                            @if(filled($invoice->invoice_access_key))
                                <button
                                    type="button"
                                    class="access-key-copy"
                                    data-copy-text="{{ $invoice->invoice_access_key }}"
                                    aria-label="Copiar chave de acesso da NF"
                                >
                                    <span>
                                        <small>Chave de acesso</small>
                                        <strong>{{ $invoice->invoice_access_key }}</strong>
                                    </span>
                                    <i class="bi bi-clipboard" aria-hidden="true"></i>
                                </button>
                            @endif

                            <div class="info-support-stack">
                                @if($paymentMethod->requiresInstallments())
                                <section class="payment-installments-summary" aria-label="Parcelas de vencimento">
                                    @if($firstInstallment)
                                        <div class="installment-next">
                                            <span>Proxima parcela</span>
                                            <strong>
                                                {{ filled($firstInstallment['due_date'] ?? null) ? \Illuminate\Support\Carbon::parse($firstInstallment['due_date'])->format('d/m/Y') : '-' }}
                                                -
                                                {{ isset($firstInstallment['amount']) ? 'R$ '.number_format((float) $firstInstallment['amount'], 2, ',', '.') : '-' }}
                                            </strong>
                                        </div>
                                    @endif

                                    @if($otherInstallments->isNotEmpty())
                                        <div class="installment-more">
                                            <span>Demais parcelas</span>
                                            <div class="installment-chip-row">
                                                @foreach($otherInstallments as $installment)
                                                    <span class="installment-chip">
                                                        #{{ $installment['number'] ?? $loop->iteration + 1 }}
                                                        {{ filled($installment['due_date'] ?? null) ? \Illuminate\Support\Carbon::parse($installment['due_date'])->format('d/m/Y') : '-' }}
                                                        -
                                                        {{ isset($installment['amount']) ? 'R$ '.number_format((float) $installment['amount'], 2, ',', '.') : '-' }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </section>
                                @endif

                                <div class="note-box submitter-note-box compact-note">
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
                                <div class="note-box compact-note">
                                    <div class="note-title">
                                        <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                                        Observacoes do Fiscal
                                    </div>
                                    <div class="text-body">{{ $invoice->fiscal_notes }}</div>
                                </div>
                                @endif
                            </div>
                        </section>

                        <div class="attachment-compact">
                            <div class="attachment-compact-header">
                                <div>
                                    <span>Documentos complementares</span>
                                    <small>{{ $invoice->attachments->count() }} anexo{{ $invoice->attachments->count() === 1 ? '' : 's' }}</small>
                                </div>
                            </div>

                            @can('review', $invoice)
                                <form method="POST" action="{{ route('invoices.attachments.store', $invoice) }}" enctype="multipart/form-data" class="attachment-form compact mb-2">
                                    @csrf
                                    <div class="attachment-upload-row compact">
                                        <input class="form-control" id="attachment" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" required aria-label="Documento complementar">
                                        <input class="form-control" id="attachment_notes" name="notes" value="{{ old('notes') }}" maxlength="2000" placeholder="Observacao">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="bi bi-paperclip" aria-hidden="true"></i>
                                            Anexar
                                        </button>
                                    </div>
                                </form>
                            @endcan

                            <div class="attachment-list compact">
                                @forelse($invoice->attachments->sortByDesc('created_at') as $attachment)
                                    <div class="attachment-item compact">
                                        <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i>
                                        <div class="attachment-copy">
                                            <div class="attachment-main-line">
                                                <a href="{{ route('invoices.attachments.show', [$invoice, $attachment]) }}" class="attachment-name" target="_blank" rel="noopener">
                                                    {{ $attachment->original_name }}
                                                </a>
                                                <div class="attachment-actions">
                                                    <a href="{{ route('invoices.attachments.show', [$invoice, $attachment]) }}" class="btn btn-sm btn-outline-primary btn-icon-only" target="_blank" rel="noopener" aria-label="Visualizar {{ $attachment->original_name }}">
                                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                                    </a>
                                                    <a href="{{ route('invoices.attachments.download', [$invoice, $attachment]) }}" class="btn btn-sm btn-outline-secondary btn-icon-only" aria-label="Baixar {{ $attachment->original_name }}">
                                                        <i class="bi bi-download" aria-hidden="true"></i>
                                                    </a>
                                                    @can('review', $invoice)
                                                        <form method="POST" action="{{ route('invoices.attachments.destroy', [$invoice, $attachment]) }}" class="d-inline" data-confirm="Excluir este documento complementar?">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-sm btn-outline-danger btn-icon-only" type="submit" aria-label="Excluir {{ $attachment->original_name }}">
                                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </div>
                                            <div class="attachment-meta">
                                                {{ $attachment->formattedSize() }} - {{ $attachment->created_at?->format('d/m/Y H:i') }} - {{ $attachment->uploader?->name ?? 'Fiscal' }}
                                            </div>
                                            @if(filled($attachment->notes))
                                                <div class="attachment-notes">{{ $attachment->notes }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <span class="visually-hidden">Nenhum documento complementar anexado.</span>
                                @endforelse
                            </div>

                            <div class="invoice-alerts-module">
                                <div class="attachment-compact-header">
                                    <div>
                                        <span>Alertas</span>
                                        <small>{{ $invoice->alerts->where('resolved', false)->count() }} aberto{{ $invoice->alerts->where('resolved', false)->count() === 1 ? '' : 's' }}</small>
                                    </div>
                                </div>

                                <div class="invoice-alerts-list">
                                    @forelse($invoice->alerts as $alert)
                                        <div class="alert alert-card {{ $alert->resolved ? 'alert-resolved' : ($alert->level->value === 'critical' ? 'alert-danger' : 'alert-warning') }} mb-2">
                                            <i class="bi {{ $alert->resolved ? 'bi-check2-circle' : 'bi-exclamation-triangle' }}" aria-hidden="true"></i>
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
                                        <p class="empty-state compact mb-0">Nenhum alerta registrado.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="invoice-secondary-row">
                        <section class="invoice-review-panel review-inline-module">
                            <ul class="nav nav-pills review-tabs" id="invoiceReviewTabs" role="tablist">
                                @can('review', $invoice)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="review-tab" data-bs-toggle="tab" data-bs-target="#review-tab-pane" type="button" role="tab" aria-controls="review-tab-pane" aria-selected="true">Conferencia</button>
                                    </li>
                                @endcan
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link @cannot('review', $invoice) active @endcannot" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-tab-pane" type="button" role="tab" aria-controls="history-tab-pane" aria-selected="@cannot('review', $invoice) true @else false @endcannot">Historico</button>
                                </li>
                            </ul>

                            <div class="tab-content review-tab-content" id="invoiceReviewTabsContent">
                                @can('review', $invoice)
                                    <div class="tab-pane fade show active" id="review-tab-pane" role="tabpanel" aria-labelledby="review-tab" tabindex="0">
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
                                                <textarea class="form-control mb-3" id="fiscal_notes" name="fiscal_notes" rows="2">{{ old('fiscal_notes', $invoice->fiscal_notes) }}</textarea>
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

                                <div class="tab-pane fade @cannot('review', $invoice) show active @endcannot" id="history-tab-pane" role="tabpanel" aria-labelledby="history-tab" tabindex="0">
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
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('vendor_scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
@endsection

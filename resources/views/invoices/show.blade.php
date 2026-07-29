@extends('layouts.app')

@section('title', $invoice->protocol.' - BAKOF')
@section('page_title', $invoice->protocol)
@section('page_subtitle', 'Detalhes da nota fiscal')

@section('content')
    @php($documentType = $invoice->documentType())
    @php($paymentMethod = $invoice->paymentMethod())
    @php($annotationData = $invoice->annotation?->data ?? ['strokes' => []])

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
                            <span>{{ $documentType->referenceLabel() }}</span>
                            <strong>{{ $invoice->purchase_order_number ?? '-' }}</strong>
                        </div>
                    </div>

                    @if(filled($invoice->invoice_access_key))
                        <button
                            type="button"
                            class="access-key-copy mb-3"
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

                    <div class="invoice-details-and-attachments">
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
                                            <a href="{{ route('invoices.attachments.download', [$invoice, $attachment]) }}" class="attachment-name">
                                                {{ $attachment->original_name }}
                                            </a>
                                            <div class="attachment-meta">
                                                {{ $attachment->formattedSize() }} - {{ $attachment->created_at?->format('d/m/Y H:i') }} - {{ $attachment->uploader?->name ?? 'Fiscal' }}
                                            </div>
                                            @if(filled($attachment->notes))
                                                <div class="attachment-notes">{{ $attachment->notes }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="empty-state compact mb-0">Nenhum documento complementar anexado.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @if($paymentMethod->requiresInstallments())
                        <div class="payment-installments-summary mt-3">
                            @foreach($invoice->payment_installments ?? [] as $installment)
                                <div>
                                    <span>Parcela {{ $installment['number'] ?? $loop->iteration }}</span>
                                    <strong>
                                        {{ filled($installment['due_date'] ?? null) ? \Illuminate\Support\Carbon::parse($installment['due_date'])->format('d/m/Y') : '-' }}
                                        -
                                        {{ isset($installment['amount']) ? 'R$ '.number_format((float) $installment['amount'], 2, ',', '.') : '-' }}
                                    </strong>
                                </div>
                            @endforeach
                        </div>
                    @endif

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
                </div>

                @if($documentType === \App\Enums\InvoiceDocumentType::Nf && $invoice->purchaseOrderCheck)
                    <div class="panel mb-3">
                        <div class="po-summary mb-0">
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
                    </div>
                @endif

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

@section('vendor_scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
@endsection

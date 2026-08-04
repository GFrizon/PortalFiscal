@php
    $isEditing = $isEditing ?? false;
    $invoiceInstallments = $isEditing ? ($invoice->payment_installments ?? []) : [];
    $oldPaymentMethod = old('payment_method', $isEditing ? $invoice->paymentMethod()->value : 'anticipated');
    $oldInstallments = old('payment_installments', $invoiceInstallments);
    $oldInstallmentCount = min(12, max(1, (int) old('payment_installments_count', max(1, count($oldInstallments)))));
    $isDraftInvoice = $isEditing && $invoice->status === \App\Enums\InvoiceStatus::Draft;
    $primaryActionLabel = $isDraftInvoice ? 'Enviar para conferencia' : ($isEditing ? 'Salvar alteracoes' : 'Enviar para conferencia');
@endphp

@extends('layouts.app')

@section('title', ($isEditing ? 'Editar nota' : 'Anexar nota').' - BAKOF')
@section('page_title', $isEditing ? 'Editar nota fiscal' : 'Anexar nota fiscal')
@section('page_subtitle', $isEditing ? 'Atualize os dados de acompanhamento' : 'Envie o PDF e informe os dados de acompanhamento')

@section('content')
    <div class="invoice-create-page">
    <div class="section-toolbar mb-3">
        <div>
            <div class="eyebrow">Importacao</div>
            <div class="section-title">{{ $isEditing ? $invoice->protocol : 'Nova nota fiscal' }}</div>
        </div>
        <a href="{{ $isEditing ? route('invoices.show', $invoice) : route('invoices.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            {{ $isEditing ? 'Voltar para detalhes' : 'Voltar para notas' }}
        </a>
    </div>

    <div class="invoice-create-grid">
    <form
        method="POST"
        action="{{ $isEditing ? route('invoices.update', $invoice) : route('invoices.store') }}"
        enctype="multipart/form-data"
        class="panel form-panel upload-form"
        data-submit-loading-message="{{ $isEditing ? 'Atualizando dados da nota...' : 'Consultando CIGAM e salvando nota...' }}"
        data-submit-success-message="{{ $isEditing ? 'Alteracoes salvas. Abrindo a nota...' : 'Nota enviada com sucesso. Abrindo a lista...' }}"
        data-submit-error-message="Nao foi possivel salvar a nota. Confira sua conexao e tente novamente."
        @unless($isEditing) data-pdf-preview-form @endunless
    >
        @csrf
        @if($isEditing)
            @method('PUT')
        @endif
        <div class="panel-header">
            <div>
                <div class="eyebrow">Arquivo e acompanhamento</div>
                <h2 class="panel-title">Dados de entrada</h2>
            </div>
            <span class="soft-chip">
                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                Storage privado
            </span>
        </div>
        <div class="row g-3">
            @if($isEditing)
                <div class="col-12">
                    <div class="upload-dropzone static">
                        <span class="upload-icon">
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                        </span>
                        <span class="upload-copy">
                            <span class="upload-title">{{ $invoice->original_pdf_name }}</span>
                            <span class="upload-text">PDF principal mantido. Esta tela altera somente os dados de acompanhamento.</span>
                        </span>
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('invoices.pdf.show', $invoice) }}" target="_blank">
                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                            Abrir PDF
                        </a>
                    </div>
                </div>
            @else
                <div class="col-12">
                    <label class="upload-dropzone" for="pdf">
                        <span class="upload-icon">
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                        </span>
                        <span class="upload-copy">
                            <span class="upload-title">Selecionar PDF da nota fiscal</span>
                            <span class="upload-text">Somente arquivos PDF, ate 10 MB. O documento sera armazenado em area privada.</span>
                        </span>
                        <span class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-upload" aria-hidden="true"></i>
                            Escolher arquivo
                        </span>
                    </label>
                    <input class="form-control visually-hidden" id="pdf" type="file" name="pdf" accept="application/pdf,.pdf" required data-file-input data-file-target="#selectedFileName">
                    <div class="selected-file-name" id="selectedFileName">Nenhum arquivo selecionado.</div>
                </div>
            @endif
            <div class="col-12">
                <div class="form-section-label">Acompanhamento</div>
            </div>
            <div class="col-12">
                <label class="urgent-toggle">
                    <input class="form-check-input" type="checkbox" name="is_urgent" value="1" @checked(old('is_urgent', $isEditing ? $invoice->is_urgent : false))>
                    <span>
                        <strong>Marcar como urgente</strong>
                        <small>Prioriza esta nota na fila de conferencia.</small>
                    </span>
                </label>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="document_type">Tipo de documento</label>
                <select class="form-select" id="document_type" name="document_type" required data-document-type-select>
                    <option value="nf" @selected(old('document_type', $isEditing ? $invoice->documentType()->value : 'nf') === 'nf')>NFe</option>
                    <option value="nf_no_oc" @selected(old('document_type', $isEditing ? $invoice->documentType()->value : null) === 'nf_no_oc')>NFe sem OC</option>
                    <option value="cte" @selected(old('document_type', $isEditing ? $invoice->documentType()->value : null) === 'cte')>CTE</option>
                </select>
            </div>
            <div class="col-12 col-md-3" data-reference-field>
                <label class="form-label" for="purchase_order_number" data-reference-label>Ordem de compra</label>
                <input class="form-control" id="purchase_order_number" name="purchase_order_number" value="{{ old('purchase_order_number', $isEditing ? $invoice->purchase_order_number : '') }}" inputmode="numeric" pattern="[0-9]*" required data-digits-only>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="arrival_date">Data de chegada</label>
                <input class="form-control" id="arrival_date" type="date" name="arrival_date" value="{{ old('arrival_date', $isEditing ? $invoice->arrival_date?->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="payment_method">Vencimento</label>
                <select class="form-select" id="payment_method" name="payment_method" required data-payment-method>
                    <option value="anticipated" @selected($oldPaymentMethod === 'anticipated')>Antecipado</option>
                    <option value="deposit" @selected($oldPaymentMethod === 'deposit')>Deposito</option>
                    <option value="boleto" @selected($oldPaymentMethod === 'boleto')>Boleto</option>
                </select>
            </div>
            <div class="col-12 payment-installments-area" data-payment-installments-area>
                <div class="installment-toolbar">
                    <div>
                        <label class="form-label" for="payment_installments_count">Parcelas</label>
                        <select class="form-select" id="payment_installments_count" name="payment_installments_count" data-installments-count>
                            @for($count = 1; $count <= 12; $count++)
                                <option value="{{ $count }}" @selected($oldInstallmentCount === $count)>{{ $count }}x</option>
                            @endfor
                        </select>
                    </div>
                </div>
                <div class="installments-grid" data-installments-grid>
                    @for($index = 0; $index < max(1, $oldInstallmentCount); $index++)
                        <div class="installment-row" data-installment-row>
                            <div class="installment-number">#{{ $index + 1 }}</div>
                            <div>
                                <label class="form-label" for="payment_installments_{{ $index }}_due_date">Vencimento</label>
                                <input class="form-control" id="payment_installments_{{ $index }}_due_date" type="date" name="payment_installments[{{ $index }}][due_date]" value="{{ $oldInstallments[$index]['due_date'] ?? '' }}" data-installment-due-date>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="user_notes">Observacoes</label>
                <textarea class="form-control" id="user_notes" name="user_notes" rows="4">{{ old('user_notes', $isEditing ? $invoice->user_notes : '') }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" name="submit_intent" value="submit" data-submit-loading-message="{{ $isDraftInvoice ? 'Enviando rascunho para conferencia...' : ($isEditing ? 'Atualizando dados da nota...' : 'Consultando CIGAM e enviando nota...') }}" data-submit-success-message="{{ $isDraftInvoice ? 'Rascunho enviado. Abrindo a nota...' : ($isEditing ? 'Alteracoes salvas. Abrindo a nota...' : 'Nota enviada com sucesso. Abrindo a lista...') }}">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                {{ $primaryActionLabel }}
            </button>
            @if(! $isEditing || $isDraftInvoice)
                <button class="btn btn-outline-primary" type="submit" name="submit_intent" value="draft" data-submit-loading-message="Salvando rascunho..." data-submit-success-message="Rascunho salvo. Abrindo a nota...">
                    <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                    Salvar rascunho
                </button>
            @endif
            <a href="{{ $isEditing ? route('invoices.show', $invoice) : route('invoices.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                Cancelar
            </a>
            <div class="submit-loading-state submit-loading-state-inline" role="status" aria-live="polite" hidden>
                <span class="submit-loading-spinner" aria-hidden="true"></span>
                <span data-submit-loading-text>{{ $isEditing ? 'Atualizando dados da nota...' : 'Consultando CIGAM e salvando nota...' }}</span>
            </div>
        </div>
    </form>

    @unless($isEditing)
    <aside class="panel upload-preview-panel" data-inline-pdf-preview hidden>
        <div class="panel-header">
            <div>
                <div class="eyebrow">Conferencia</div>
                <h2 class="panel-title">Preview antes de salvar</h2>
            </div>
            <span class="soft-chip">
                <i class="bi bi-eye" aria-hidden="true"></i>
                Confira o PDF
            </span>
        </div>
        <div class="preview-layout preview-layout-inline">
            <div class="preview-frame-shell">
                <iframe class="preview-frame" title="Preview do PDF selecionado" data-pdf-preview-frame></iframe>
            </div>
            <div class="preview-summary">
                <div class="summary-card-grid">
                    <div>
                        <span>Arquivo</span>
                        <strong data-preview-file-name>-</strong>
                    </div>
                    <div>
                        <span>Tamanho</span>
                        <strong data-preview-file-size>-</strong>
                    </div>
                    <div>
                        <span data-preview-reference-label>Ordem</span>
                        <strong data-preview-purchase-order>-</strong>
                    </div>
                    <div>
                        <span>Chegada</span>
                        <strong data-preview-arrival-date>-</strong>
                    </div>
                    <div>
                        <span>Vencimento</span>
                        <strong data-preview-payment>-</strong>
                    </div>
                </div>
                <div class="note-box mt-3">
                    <div class="note-title">
                        <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                        Observacoes
                    </div>
                    <div class="text-body" data-preview-notes>-</div>
                </div>
            </div>
        </div>
    </aside>
    @endunless
    </div>
    </div>
@endsection

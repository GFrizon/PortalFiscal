@extends('layouts.app')

@section('title', 'Anexar nota - BAKOF')
@section('page_title', 'Anexar nota fiscal')
@section('page_subtitle', 'Envie o PDF e informe os dados de acompanhamento')

@section('content')
    @php
        $oldPaymentMethod = old('payment_method', 'anticipated');
        $oldInstallmentCount = min(12, max(1, (int) old('payment_installments_count', max(1, count(old('payment_installments', []))))));
        $oldInstallments = old('payment_installments', []);
    @endphp

    <div class="invoice-create-page">
    <div class="section-toolbar mb-3">
        <div>
            <div class="eyebrow">Importacao</div>
            <div class="section-title">Nova nota fiscal</div>
        </div>
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Voltar para notas
        </a>
    </div>

    <div class="invoice-create-grid">
    <form method="POST" action="{{ route('invoices.store') }}" enctype="multipart/form-data" class="panel form-panel upload-form" data-pdf-preview-form>
        @csrf
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
            <div class="col-12">
                <div class="form-section-label">Acompanhamento</div>
            </div>
            <div class="col-12">
                <label class="urgent-toggle">
                    <input class="form-check-input" type="checkbox" name="is_urgent" value="1" @checked(old('is_urgent'))>
                    <span>
                        <strong>Marcar como urgente</strong>
                        <small>Prioriza esta nota na fila de conferencia.</small>
                    </span>
                </label>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="document_type">Tipo de documento</label>
                <select class="form-select" id="document_type" name="document_type" required data-document-type-select>
                    <option value="nf" @selected(old('document_type', 'nf') === 'nf')>NF</option>
                    <option value="nf_no_oc" @selected(old('document_type') === 'nf_no_oc')>Nota Fiscal sem ordem de compra</option>
                    <option value="cte" @selected(old('document_type') === 'cte')>CTE</option>
                </select>
            </div>
            <div class="col-12 col-md-3" data-reference-field>
                <label class="form-label" for="purchase_order_number" data-reference-label>Ordem de compra</label>
                <input class="form-control" id="purchase_order_number" name="purchase_order_number" value="{{ old('purchase_order_number') }}" inputmode="numeric" pattern="[0-9]*" required data-digits-only>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="arrival_date">Data de chegada</label>
                <input class="form-control" id="arrival_date" type="date" name="arrival_date" value="{{ old('arrival_date', now()->format('Y-m-d')) }}" required>
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
                            <div>
                                <label class="form-label" for="payment_installments_{{ $index }}_amount">Valor</label>
                                <input class="form-control" id="payment_installments_{{ $index }}_amount" name="payment_installments[{{ $index }}][amount]" value="{{ $oldInstallments[$index]['amount'] ?? '' }}" inputmode="decimal" data-installment-amount>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="user_notes">Observacoes</label>
                <textarea class="form-control" id="user_notes" name="user_notes" rows="4">{{ old('user_notes') }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                Salvar nota
            </button>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                Cancelar
            </a>
        </div>
    </form>

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
    </div>
    </div>
@endsection

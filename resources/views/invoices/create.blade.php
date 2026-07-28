@extends('layouts.app')

@section('title', 'Anexar nota - BAKOF')
@section('page_title', 'Anexar nota fiscal')
@section('page_subtitle', 'Envie o PDF e informe os dados de acompanhamento')

@section('content')
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
            <div class="col-12 col-md-4">
                <label class="form-label" for="purchase_order_number">Ordem de compra</label>
                <input class="form-control" id="purchase_order_number" name="purchase_order_number" value="{{ old('purchase_order_number') }}">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="arrival_date">Data de chegada</label>
                <input class="form-control" id="arrival_date" type="date" name="arrival_date" value="{{ old('arrival_date', now()->format('Y-m-d')) }}" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="due_date">Data de vencimento</label>
                <input class="form-control" id="due_date" type="date" name="due_date" value="{{ old('due_date') }}">
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

    <div class="modal fade" id="pdfPreviewModal" tabindex="-1" aria-labelledby="pdfPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content preview-modal">
                <div class="modal-header">
                    <div>
                        <div class="eyebrow">Confirmacao de envio</div>
                        <h2 class="modal-title fs-6" id="pdfPreviewModalLabel">Conferir nota antes de anexar</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="preview-layout">
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
                                    <span>Ordem</span>
                                    <strong data-preview-purchase-order>-</strong>
                                </div>
                                <div>
                                    <span>Chegada</span>
                                    <strong data-preview-arrival-date>-</strong>
                                </div>
                                <div>
                                    <span>Vencimento</span>
                                    <strong data-preview-due-date>-</strong>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                        Revisar
                    </button>
                    <button type="button" class="btn btn-primary" data-confirm-pdf-upload>
                        <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                        Confirmar envio
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

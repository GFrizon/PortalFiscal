@extends('layouts.app')

@section('title', 'Configuracoes - BAKOF')
@section('page_title', 'Configuracoes')
@section('page_subtitle', 'Parametros atuais do sistema')

@section('content')
    <div class="settings-layout">
    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="eyebrow">Ambiente</div>
                <h2 class="panel-title">Aplicacao</h2>
            </div>
        </div>
        <div class="settings-grid">
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-window-sidebar" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">Nome da aplicacao</div>
                        <div class="fw-semibold">{{ config('app.name') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-server" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">Ambiente</div>
                        <div class="fw-semibold">{{ app()->environment() }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">Timezone</div>
                        <div class="fw-semibold">{{ config('app.timezone') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-hdd" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">Disco de arquivos</div>
                        <div class="fw-semibold">{{ config('filesystems.default') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="eyebrow">Acessos</div>
                <h2 class="panel-title">Grupos de usuarios</h2>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.user-groups.index') }}">
                <i class="bi bi-diagram-3" aria-hidden="true"></i>
                Gerenciar
            </a>
        </div>
        <div class="settings-item">
            <i class="bi bi-people" aria-hidden="true"></i>
            <div>
                <div class="eyebrow">Visibilidade de notas</div>
                <div class="fw-semibold">Usuarios do mesmo grupo enxergam as notas anexadas entre si.</div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="eyebrow">Armazenamento</div>
                <h2 class="panel-title">PDFs e volume</h2>
            </div>
            <span class="soft-chip">
                <i class="bi bi-hdd" aria-hidden="true"></i>
                {{ $storageDisplay['disk'] }}
            </span>
        </div>
        <div class="settings-grid">
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">PDFs cadastrados</div>
                        <div class="fw-semibold">{{ $storage['invoice_count'] }} notas</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-database" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">Uso real no storage</div>
                        <div class="fw-semibold">{{ $storageDisplay['disk'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-file-zip" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">PDFs otimizados</div>
                        <div class="fw-semibold">{{ $storage['optimized_count'] }} arquivos</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-graph-down-arrow" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">Economia registrada</div>
                        <div class="fw-semibold">{{ $storageDisplay['database_saved'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">Arquivos temporarios</div>
                        <div class="fw-semibold">{{ $storageDisplay['tmp'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">Media por PDF</div>
                        <div class="fw-semibold">{{ $storageDisplay['average'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-sliders" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">Otimizacao automatica</div>
                        <div class="fw-semibold">{{ $storage['optimization_enabled'] ? 'Ativa' : 'Inativa' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="settings-item">
                    <i class="bi bi-terminal" aria-hidden="true"></i>
                    <div>
                        <div class="eyebrow">Binario de compactacao</div>
                        <div class="fw-semibold">{{ $storage['optimization_binary'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <div class="eyebrow">Rotinas de manutencao</div>
                <h2 class="panel-title">Comandos para cPanel</h2>
            </div>
        </div>
        <div class="d-grid gap-2">
            <code>php artisan invoices:storage-report</code>
            <code>php artisan invoices:optimize-pdfs --force --limit=25 --min-size-kb=300</code>
            <code>php artisan invoices:cleanup-storage --days=1</code>
            <code>php artisan invoices:cleanup-storage --orphans --dry-run</code>
        </div>
    </div>
    </div>
@endsection

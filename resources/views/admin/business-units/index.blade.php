@extends('layouts.app')

@section('title', 'Unidades - BAKOF')
@section('page_title', 'Unidades de negocio')
@section('page_subtitle', 'CNPJs usados para identificar destinatarios das notas')

@section('content')
    <div class="section-toolbar mb-3">
        <div>
            <div class="eyebrow">Cadastro base</div>
            <div class="section-title">Unidades cadastradas</div>
        </div>
        <a href="{{ route('admin.business-units.create') }}" class="btn btn-primary">
            <i class="bi bi-building-add" aria-hidden="true"></i>
            Nova
        </a>
    </div>

    <div class="panel panel-table">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Unidade</th>
                    <th>Razao social</th>
                    <th>CNPJ</th>
                    <th>Codigo</th>
                    <th>Situacao</th>
                    <th class="text-end">Acoes</th>
                </tr>
                </thead>
                <tbody>
                @forelse($businessUnits as $businessUnit)
                    <tr>
                        <td>
                            <div class="table-entity">
                                <span class="entity-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
                                <span>{{ $businessUnit->name }}</span>
                            </div>
                        </td>
                        <td>{{ $businessUnit->legal_name }}</td>
                        <td>{{ $businessUnit->cnpj }}</td>
                        <td>{{ $businessUnit->internal_code }}</td>
                        <td><span class="badge {{ $businessUnit->status->value === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $businessUnit->status->label() }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('admin.business-units.edit', $businessUnit) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $businessUnit->name }}">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.business-units.destroy', $businessUnit) }}" class="d-inline" data-confirm="Inativar esta unidade?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Inativar {{ $businessUnit->name }}">
                                    <i class="bi bi-slash-circle" aria-hidden="true"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-state">Nenhuma unidade cadastrada.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-pagination">{{ $businessUnits->links() }}</div>
    </div>
@endsection

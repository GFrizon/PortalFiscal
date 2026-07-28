@extends('layouts.app')

@section('title', 'Editar unidade - BAKOF')
@section('page_title', 'Editar unidade de negocio')

@section('content')
    <form method="POST" action="{{ route('admin.business-units.update', $businessUnit) }}" class="panel form-panel">
        @csrf
        @method('PUT')
        <div class="panel-header">
            <div>
                <div class="eyebrow">Cadastro base</div>
                <h2 class="panel-title">{{ $businessUnit->name }}</h2>
            </div>
            <span class="badge {{ $businessUnit->status->value === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $businessUnit->status->label() }}</span>
        </div>
        @include('admin.business-units.form')
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                Salvar alteracoes
            </button>
            <a href="{{ route('admin.business-units.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                Cancelar
            </a>
        </div>
    </form>
@endsection

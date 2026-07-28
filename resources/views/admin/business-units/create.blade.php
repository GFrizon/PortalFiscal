@extends('layouts.app')

@section('title', 'Nova unidade - BAKOF')
@section('page_title', 'Nova unidade de negocio')

@section('content')
    <form method="POST" action="{{ route('admin.business-units.store') }}" class="panel form-panel">
        @csrf
        <div class="panel-header">
            <div>
                <div class="eyebrow">Cadastro base</div>
                <h2 class="panel-title">Dados da unidade</h2>
            </div>
        </div>
        @include('admin.business-units.form', ['businessUnit' => null])
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                Salvar
            </button>
            <a href="{{ route('admin.business-units.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                Cancelar
            </a>
        </div>
    </form>
@endsection

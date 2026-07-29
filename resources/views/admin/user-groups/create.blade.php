@extends('layouts.app')

@section('title', 'Novo grupo - BAKOF')
@section('page_title', 'Novo grupo')

@section('content')
    <form method="POST" action="{{ route('admin.user-groups.store') }}" class="panel form-panel">
        @csrf
        <div class="panel-header">
            <div>
                <div class="eyebrow">Visibilidade</div>
                <h2 class="panel-title">Dados do grupo</h2>
            </div>
        </div>
        @include('admin.user-groups.form')
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                Salvar
            </button>
            <a href="{{ route('admin.user-groups.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                Cancelar
            </a>
        </div>
    </form>
@endsection

@extends('layouts.app')

@section('title', 'Novo usuario - BAKOF')
@section('page_title', 'Novo usuario')

@section('content')
    <form method="POST" action="{{ route('admin.users.store') }}" class="panel form-panel">
        @csrf
        <div class="panel-header">
            <div>
                <div class="eyebrow">Cadastro de acesso</div>
                <h2 class="panel-title">Dados do usuario</h2>
            </div>
        </div>
        @include('admin.users.form', ['user' => null])
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                Salvar
            </button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                Cancelar
            </a>
        </div>
    </form>
@endsection

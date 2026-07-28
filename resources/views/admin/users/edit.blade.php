@extends('layouts.app')

@section('title', 'Editar usuario - BAKOF')
@section('page_title', 'Editar usuario')

@section('content')
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="panel form-panel">
        @csrf
        @method('PUT')
        <div class="panel-header">
            <div>
                <div class="eyebrow">Cadastro de acesso</div>
                <h2 class="panel-title">{{ $user->name }}</h2>
            </div>
            <span class="badge {{ $user->isActive() ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->status->label() }}</span>
        </div>
        @include('admin.users.form')
        <div class="form-check form-switch mt-3">
            <input type="hidden" name="force_password_change" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="force_password_change" value="1" id="force_password_change" @checked(old('force_password_change', $user->force_password_change))>
            <label class="form-check-label" for="force_password_change">Obrigar troca de senha</label>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                Salvar alteracoes
            </button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                Cancelar
            </a>
        </div>
    </form>
@endsection

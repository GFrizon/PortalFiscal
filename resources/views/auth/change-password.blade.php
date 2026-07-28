@extends('layouts.app')

@section('title', 'Alterar senha - BAKOF')
@section('page_title', 'Alterar senha')
@section('page_subtitle', 'Defina uma nova senha para continuar usando o portal')

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="panel form-panel">
        @csrf

        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="current_password">Senha atual</label>
                <input class="form-control" id="current_password" type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="password">Nova senha</label>
                <input class="form-control" id="password" type="password" name="password" required autocomplete="new-password">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="password_confirmation">Confirmar nova senha</label>
                <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                Atualizar senha
            </button>
        </div>
    </form>
@endsection

@extends('layouts.app')

@section('title', 'Editar grupo - BAKOF')
@section('page_title', 'Editar grupo')

@section('content')
    <form method="POST" action="{{ route('admin.user-groups.update', $group) }}" class="panel form-panel">
        @csrf
        @method('PUT')
        <div class="panel-header">
            <div>
                <div class="eyebrow">Visibilidade</div>
                <h2 class="panel-title">{{ $group->name }}</h2>
            </div>
        </div>
        @include('admin.user-groups.form')
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                Salvar alteracoes
            </button>
            <a href="{{ route('admin.user-groups.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                Cancelar
            </a>
        </div>
    </form>
@endsection

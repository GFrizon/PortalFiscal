@extends('layouts.app')

@section('title', 'Usuarios - BAKOF')
@section('page_title', 'Usuarios')
@section('page_subtitle', 'Cadastro e bloqueio de acessos')

@section('content')
    <div class="section-toolbar mb-3">
        <div>
            <div class="eyebrow">Acessos</div>
            <div class="section-title">Usuarios cadastrados</div>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
            Novo
        </a>
    </div>

    <div class="panel panel-table">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Tipo</th>
                    <th>Grupo</th>
                    <th>Situacao</th>
                    <th>Criado em</th>
                    <th class="text-end">Acoes</th>
                </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="table-entity">
                                <span class="entity-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                                <span>{{ $user->name }}</span>
                            </div>
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role->label() }}</td>
                        <td>{{ $user->group?->name ?? '-' }}</td>
                        <td><span class="badge {{ $user->isActive() ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->status->label() }}</span></td>
                        <td>{{ $user->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $user->name }}">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>
                            @can('delete', $user)
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline" data-confirm="Bloquear este usuario?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Bloquear {{ $user->name }}">
                                        <i class="bi bi-lock" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-state">Nenhum usuario cadastrado.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-pagination">{{ $users->links() }}</div>
    </div>
@endsection

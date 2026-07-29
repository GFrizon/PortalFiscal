@extends('layouts.app')

@section('title', 'Grupos de usuarios - BAKOF')
@section('page_title', 'Grupos')
@section('page_subtitle', 'Controle de visibilidade entre usuarios')

@section('content')
    <div class="section-toolbar mb-3">
        <div>
            <div class="eyebrow">Compartilhamento</div>
            <div class="section-title">Grupos de usuarios</div>
        </div>
        <a href="{{ route('admin.user-groups.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Novo grupo
        </a>
    </div>

    <div class="panel panel-table">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                <tr>
                    <th>Grupo</th>
                    <th>Usuarios</th>
                    <th>Criado em</th>
                    <th class="text-end">Acoes</th>
                </tr>
                </thead>
                <tbody>
                @forelse($groups as $group)
                    <tr>
                        <td>
                            <div class="table-entity">
                                <span class="entity-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                                <span>{{ $group->name }}</span>
                            </div>
                        </td>
                        <td>{{ $group->users_count }}</td>
                        <td>{{ $group->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.user-groups.edit', $group) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $group->name }}">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.user-groups.destroy', $group) }}" class="d-inline" data-confirm="Excluir este grupo?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Excluir {{ $group->name }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">Nenhum grupo cadastrado.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-pagination">{{ $groups->links() }}</div>
    </div>
@endsection

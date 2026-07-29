<div class="row g-3">
    <div class="col-12 col-md-6">
        <label class="form-label" for="name">Nome</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $user?->name) }}" required>
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label" for="email">E-mail</label>
        <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $user?->email) }}" required>
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label" for="password">Senha</label>
        <input class="form-control" id="password" type="password" name="password" @if(! $user) required @endif autocomplete="new-password">
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label" for="password_confirmation">Confirmar senha</label>
        <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" @if(! $user) required @endif autocomplete="new-password">
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label" for="role">Tipo de usuario</label>
        <select class="form-select" id="role" name="role" required>
            @foreach($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $user?->role->value) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label" for="status">Situacao</label>
        <select class="form-select" id="status" name="status" required>
            @foreach($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $user?->status->value) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label" for="user_group_id">Grupo</label>
        <select class="form-select" id="user_group_id" name="user_group_id">
            <option value="">Sem grupo</option>
            @foreach($groups as $group)
                <option value="{{ $group->id }}" @selected((string) old('user_group_id', $user?->user_group_id) === (string) $group->id)>{{ $group->name }}</option>
            @endforeach
        </select>
    </div>
</div>

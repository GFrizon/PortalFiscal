<div class="row g-3">
    <div class="col-12 col-md-6">
        <label class="form-label" for="name">Nome da unidade</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $businessUnit?->name) }}" required>
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label" for="legal_name">Razao social</label>
        <input class="form-control" id="legal_name" name="legal_name" value="{{ old('legal_name', $businessUnit?->legal_name) }}" required>
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label" for="cnpj">CNPJ</label>
        <input class="form-control" id="cnpj" name="cnpj" value="{{ old('cnpj', $businessUnit?->cnpj) }}" maxlength="18" required>
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label" for="internal_code">Codigo interno</label>
        <input class="form-control" id="internal_code" name="internal_code" value="{{ old('internal_code', $businessUnit?->internal_code) }}">
    </div>
    <div class="col-12 col-md-4">
        <label class="form-label" for="status">Situacao</label>
        <select class="form-select" id="status" name="status" required>
            @foreach($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $businessUnit?->status->value) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
</div>

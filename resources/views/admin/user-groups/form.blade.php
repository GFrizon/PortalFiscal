<div class="row g-3">
    <div class="col-12 col-md-8">
        <label class="form-label" for="name">Nome do grupo</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $group?->name) }}" required>
    </div>
</div>

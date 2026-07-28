<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redefinir senha - BAKOF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <section class="bg-white rounded shadow-sm border p-4 w-100" style="max-width: 460px">
        <h1 class="h4 mb-2">Redefinir senha</h1>
        <p class="text-secondary mb-4">Crie uma nova senha para acessar o portal.</p>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.store') }}" class="d-grid gap-3">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div>
                <label for="email" class="form-label">E-mail</label>
                <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            </div>
            <div>
                <label for="password" class="form-label">Nova senha</label>
                <input id="password" name="password" type="password" class="form-control" required autocomplete="new-password">
            </div>
            <div>
                <label for="password_confirmation" class="form-label">Confirmar senha</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required autocomplete="new-password">
            </div>
            <button class="btn btn-primary" type="submit">Salvar nova senha</button>
        </form>
    </section>
</main>
</body>
</html>

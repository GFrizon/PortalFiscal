<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar senha - BAKOF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <section class="bg-white rounded shadow-sm border p-4 w-100" style="max-width: 420px">
        <h1 class="h4 mb-2">Recuperar senha</h1>
        <p class="text-secondary mb-4">Informe seu e-mail para receber o link de redefinicao.</p>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="d-grid gap-3">
            @csrf
            <div>
                <label for="email" class="form-label">E-mail</label>
                <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" required autofocus>
            </div>
            <button class="btn btn-primary" type="submit">Enviar link</button>
            <a href="{{ route('login') }}" class="btn btn-link">Voltar ao login</a>
        </form>
    </section>
</main>
</body>
</html>

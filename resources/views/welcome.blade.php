<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal de Notas Fiscais - BAKOF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <section class="bg-white border rounded shadow-sm p-4 text-center" style="max-width: 440px">
        <div class="fw-bold fs-4 mb-1">BAKOF</div>
        <h1 class="h5 mb-3">Portal de Notas Fiscais</h1>
        <p class="text-secondary mb-4">Acesse o sistema para acompanhar e administrar as notas fiscais.</p>

        @auth
            <a class="btn btn-primary" href="{{ route('dashboard') }}">Ir para o dashboard</a>
        @else
            <a class="btn btn-primary" href="{{ route('login') }}">Entrar</a>
        @endauth
    </section>
</main>
</body>
</html>

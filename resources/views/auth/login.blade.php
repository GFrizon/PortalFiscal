<!doctype html>
<html lang="pt-BR">
<head>
    @php
        $assetVersion = file_exists(public_path('css/portal.css')) ? filemtime(public_path('css/portal.css')) : time();
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Portal de Notas Fiscais BAKOF</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}?v=bakoftec-20260727" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}?v=bakoftec-20260727">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=bakoftec-20260727">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=bakoftec-20260727">
    <meta name="theme-color" content="#213f78">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/portal.css') }}?v={{ $assetVersion }}" rel="stylesheet">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-panel">
        <div class="login-brand">
            <img src="{{ asset('images/bakoftec-logo.png') }}" class="login-logo" alt="BAKOFTEC">
            <div>
                <div class="text-secondary">Portal de Notas Fiscais</div>
            </div>
        </div>
        <h1>Acesso ao portal fiscal</h1>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="d-grid gap-3">
            @csrf
            <div>
                <label for="email" class="form-label">E-mail</label>
                <div class="input-icon">
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                    <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
            </div>
            <div>
                <label for="password" class="form-label">Senha</label>
                <div class="input-icon">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <input id="password" name="password" type="password" class="form-control" required autocomplete="current-password">
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                    <label class="form-check-label" for="remember">Manter conectado</label>
                </div>
                <a href="{{ route('password.request') }}" class="small">Recuperar senha</a>
            </div>
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                Entrar
            </button>
        </form>
    </section>
</main>
</body>
</html>

<!doctype html>
<html lang="pt-BR">
<head>
    @php
        $assetVersion = file_exists(public_path('css/portal.css')) ? filemtime(public_path('css/portal.css')) : time();
        $jsVersion = file_exists(public_path('js/portal.js')) ? filemtime(public_path('js/portal.js')) : time();
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Portal de Notas Fiscais BAKOF</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}?v=portal-fiscal-20260729" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}?v=portal-fiscal-20260729" sizes="32x32" type="image/png">
    <link rel="icon" href="{{ asset('favicon-192.png') }}?v=portal-fiscal-20260729" sizes="192x192" type="image/png">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}?v=portal-fiscal-20260729">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=portal-fiscal-20260729">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=portal-fiscal-20260729">
    <meta name="theme-color" content="#1f3f77">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Portal Fiscal">
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

        <form method="POST" action="{{ route('login.store') }}" class="d-grid gap-3" data-submit-loading-message="Validando acesso...">
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
            <div class="submit-loading-state" role="status" aria-live="polite" hidden>
                <span class="submit-loading-spinner" aria-hidden="true"></span>
                <span data-submit-loading-text>Validando acesso...</span>
            </div>
        </form>
    </section>
</main>
<script src="{{ asset('js/portal.js') }}?v={{ $jsVersion }}" defer></script>
</body>
</html>

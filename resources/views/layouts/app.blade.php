<!doctype html>
<html lang="pt-BR">
<head>
    @php
        $cssVersion = file_exists(public_path('css/portal.css')) ? filemtime(public_path('css/portal.css')) : time();
        $jsVersion = file_exists(public_path('js/portal.js')) ? filemtime(public_path('js/portal.js')) : time();
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal de Notas Fiscais - BAKOF')</title>
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
    <link href="{{ asset('css/portal.css') }}?v={{ $cssVersion }}" rel="stylesheet">
</head>
<body>
@php
    $currentUser = auth()->user();
    $initials = collect(explode(' ', trim($currentUser->name ?? 'Usuario')))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->join('');

    $breadcrumbs = [
        ['label' => 'Portal Fiscal', 'url' => route('dashboard')],
        ['label' => trim($__env->yieldContent('page_title', 'Dashboard')), 'url' => null],
    ];
@endphp

<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <a href="{{ route('dashboard') }}" class="brand-mark" aria-label="Ir para o dashboard">
                <img src="{{ asset('images/bakoftec-logo.png') }}" class="brand-logo" alt="BAKOFTEC">
                <span class="brand-mini" aria-hidden="true">B</span>
                <span class="brand-copy">
                    <span class="brand-product">Portal Fiscal</span>
                </span>
            </a>
            <button class="sidebar-collapse-button d-none d-lg-inline-flex" type="button" data-sidebar-toggle aria-label="Recolher menu">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>
        </div>

        <nav id="sidebarMenu" class="sidebar-nav" aria-label="Menu principal">
            <div class="nav-group">
                <div class="nav-group-label">Operacao</div>
                <a class="nav-link-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="bi bi-grid-1x2 nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a class="nav-link-item {{ request()->routeIs('invoices.create') ? 'active' : '' }}" href="{{ route('invoices.create') }}">
                    <i class="bi bi-cloud-arrow-up nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Anexar nota</span>
                </a>
                <a class="nav-link-item {{ request()->routeIs('invoices.index') || request()->routeIs('invoices.show') ? 'active' : '' }}" href="{{ route('invoices.index') }}">
                    <i class="bi bi-folder2-open nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Notas fiscais</span>
                </a>
                <a class="nav-link-item {{ request()->routeIs('histories.*') ? 'active' : '' }}" href="{{ route('histories.index') }}">
                    <i class="bi bi-clock-history nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Historico</span>
                </a>
            </div>

            @if($currentUser?->isAdmin())
                <div class="nav-group">
                <div class="nav-group-label">Administracao</div>
                <a class="nav-link-item {{ request()->routeIs('admin.business-units.*') ? 'active' : '' }}" href="{{ route('admin.business-units.index') }}">
                    <i class="bi bi-buildings nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Unidades</span>
                </a>
                <a class="nav-link-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                    <i class="bi bi-people nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Usuarios</span>
                </a>
                <a class="nav-link-item {{ request()->routeIs('admin.user-groups.*') ? 'active' : '' }}" href="{{ route('admin.user-groups.index') }}">
                    <i class="bi bi-diagram-3 nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Grupos</span>
                </a>
                <a class="nav-link-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}">
                    <i class="bi bi-sliders2 nav-icon" aria-hidden="true"></i>
                    <span class="nav-text">Configuracoes</span>
                </a>
                </div>
            @endif
        </nav>

        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar" aria-hidden="true">{{ $initials }}</div>
                <div class="user-chip-copy">
                    <div class="user-name">{{ $currentUser->name }}</div>
                    <div class="user-role">{{ $currentUser->role->label() }}</div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-sidebar-logout btn-sm w-100" type="submit">
                    <i class="bi bi-box-arrow-left" aria-hidden="true"></i>
                    <span>Sair</span>
                </button>
            </form>
            <button class="btn btn-sidebar-install btn-sm w-100 install-app-button" type="button" data-install-app hidden>
                <i class="bi bi-download" aria-hidden="true"></i>
                <span>Instalar app</span>
            </button>
        </div>
    </aside>

    <div class="mobile-sidebar-backdrop" data-sidebar-close></div>

    <main class="app-main">
        <header class="app-topbar">
            <div class="topbar-left">
                <button class="btn icon-button d-lg-none" type="button" data-mobile-menu aria-label="Abrir menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <div>
                    <nav class="breadcrumb-shell" aria-label="Breadcrumb">
                        @foreach($breadcrumbs as $breadcrumb)
                            @if($breadcrumb['url'])
                                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                            @else
                                <span>{{ $breadcrumb['label'] }}</span>
                            @endif
                        @endforeach
                    </nav>
                    <h1 class="page-title">@yield('page_title', 'Portal de Notas Fiscais')</h1>
                    <p class="page-subtitle">@yield('page_subtitle', 'Recebimento, conferencia e lancamento de notas')</p>
                </div>
            </div>

            <div class="dropdown">
                <button class="profile-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                    <span class="profile-copy d-none d-sm-block">
                        <span class="profile-name">{{ $currentUser->name }}</span>
                        <span class="profile-role">{{ $currentUser->role->label() }}</span>
                    </span>
                    <i class="bi bi-chevron-down profile-caret" aria-hidden="true"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-sm">
                    <div class="dropdown-header">
                        <div class="fw-semibold">{{ $currentUser->name }}</div>
                        <div class="small text-secondary">{{ $currentUser->email }}</div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item install-app-button" type="button" data-install-app hidden>
                        <i class="bi bi-download me-2" aria-hidden="true"></i>
                        Instalar app
                    </button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item" type="submit">Sair</button>
                    </form>
                </div>
            </div>
        </header>

        <section class="app-content">
            @if(session('success'))
                <div class="alert alert-success app-alert" data-feedback="success" role="status">{{ session('success') }}</div>
            @endif

            @if(session('status'))
                <div class="alert alert-success app-alert" data-feedback="success" role="status">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger app-alert" data-feedback="error" role="alert">
                    <strong>Revise os dados informados.</strong>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('vendor_scripts')
<script src="{{ asset('js/portal.js') }}?v={{ $jsVersion }}"></script>
@yield('page_scripts')
</body>
</html>

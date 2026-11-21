<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="turbo-cache-control" content="no-cache">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <script>
        window.APP_LOCALE = @json(app()->getLocale());
        window.APP_MESSAGES = @json(\Illuminate\Support\Arr::dot(['app' => __('app')]));
    </script>
    @vite(['resources/js/app.js', 'resources/js/admin.js'])
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{{ __('app.nav.menu') }}">{{ __('app.nav.menu') }}</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('app.nav.language') }}">
                        @include('layouts.partials.nav-icon', ['name' => 'language'])
                        <span class="small text-uppercase">{{ app()->getLocale() }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item {{ app()->getLocale() === 'en' ? 'active' : '' }}" href="{{ route('locale.switch', 'en') }}" data-turbo="false">English</a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ app()->getLocale() === 'ms' ? 'active' : '' }}" href="{{ route('locale.switch', 'ms') }}" data-turbo="false">Bahasa Melayu</a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item header-account">
                    <a class="nav-link d-flex align-items-center gap-2 flex-nowrap" href="{{ route(auth()->user()->isMember() ? 'member.profile.index' : 'admin.profile.index') }}">
                        @php($avatarUrl = app(\App\Services\Avatar::class)->url(auth()->user()))
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="" class="rounded-circle avatar-thumb">
                        @else
                            <span class="rounded-circle avatar-thumb avatar-initial d-inline-flex align-items-center justify-content-center">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        @endif
                        {{-- Small screens show only the avatar; the full name and badges appear from the medium breakpoint. --}}
                        <span class="d-none d-md-inline header-name text-truncate">{{ auth()->user()->name }}</span>
                        <span class="badge text-bg-secondary d-none d-lg-inline-block">{{ auth()->user()->account_type->label() }}</span>
                        @if (auth()->user()->isStaff())
                            <span class="badge text-bg-light d-none d-lg-inline-block">{{ auth()->user()->role->label() }}</span>
                        @endif
                        @if (auth()->user()->accountPosition)
                            <span class="badge text-bg-info d-none d-lg-inline-block">{{ auth()->user()->accountPosition->name_bm }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <form method="POST" action="{{ route('logout') }}" class="px-2" data-turbo="false">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" aria-label="{{ __('app.nav.logout') }}">
                            @include('layouts.partials.nav-icon', ['name' => 'logout'])
                            <span class="d-none d-md-inline">{{ __('app.nav.logout') }}</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </nav>

    @php($logoUrl = app(\App\Services\Branding::class)->logoUrl())
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand {{ $logoUrl ? 'brand-with-logo' : '' }}">
            <a href="{{ route(auth()->user()->isMember() ? 'member.home' : 'admin.dashboard') }}" class="brand-link">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ __('app.nav.brand') }}" class="brand-logo-img">
                @else
                    <span class="brand-text fw-light">{{ __('app.nav.brand') }}</span>
                @endif
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                    @include('layouts.partials.sidebar-menu')
                </ul>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <h3 class="mb-0">@yield('title')</h3>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                @if (app(\App\Services\TwoFactorService::class)->enabledMethods(auth()->user()) === [])
                    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" role="alert" data-two-factor-warning>
                        <div>
                            <strong>{{ __('app.two_factor.banner_title') }}</strong>
                            <span class="d-block d-sm-inline">{{ __('app.two_factor.banner_text') }}</span>
                        </div>
                        <a class="btn btn-sm btn-warning" href="{{ route(auth()->user()->isMember() ? 'member.profile.index' : 'admin.profile.index') }}">{{ __('app.two_factor.banner_action') }}</a>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <strong>&copy; {{ date('Y') }} {{ __('app.nav.footer') }}</strong>
    </footer>
</div>
</body>
</html>

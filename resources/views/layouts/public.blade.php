<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @vite(['resources/js/app.js', 'resources/js/public.js'])
</head>
<body class="bg-body-tertiary" data-turbo="false">
<header class="bg-body border-bottom py-3">
    <div class="container d-flex align-items-center justify-content-between">
        @if ($logoUrl = app(\App\Services\Branding::class)->logoUrl())
            <img src="{{ $logoUrl }}" alt="Dewan Perniagaan Puncak Alam" style="max-height: 40px">
        @else
            <strong>Dewan Perniagaan Puncak Alam</strong>
        @endif
        @auth
            <a href="{{ route('admin.members.index') }}" class="btn btn-sm btn-outline-primary">Ke Pangkalan Data</a>
        @endauth
    </div>
</header>

<main class="container py-4 py-md-5">
    @yield('content')
</main>

<footer class="container pb-4 text-center text-body-secondary small">
    &copy; {{ date('Y') }} Dewan Perniagaan Puncak Alam
</footer>
</body>
</html>

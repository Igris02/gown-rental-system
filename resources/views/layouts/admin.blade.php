<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/calendar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/menu.css') }}">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>

            <button type="button" class="menu-toggle" data-menu-toggle aria-expanded="false" aria-controls="site-menu" aria-label="Open menu">
                <span class="menu-bars" aria-hidden="true"></span>
                <span data-menu-label>Menu</span>
            </button>

            <nav id="site-menu" aria-label="Main menu">
                <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>Dashboard</a>
                <a href="{{ route('admin.gowns.index') }}" @if (request()->routeIs('admin.gowns.*')) aria-current="page" @endif>Gowns</a>
                <a href="{{ route('admin.reservations.index') }}" @if (request()->routeIs('admin.reservations.*')) aria-current="page" @endif>Reservations</a>
                <a href="{{ route('admin.walk-ins.create') }}" @if (request()->routeIs('admin.walk-ins.*')) aria-current="page" @endif>Walk-in booking</a>
                @if (auth()->user()->role === 'admin')
                    <a href="{{ route('admin.reports.index') }}" @if (request()->routeIs('admin.reports.*')) aria-current="page" @endif>Reports</a>
                    <a href="{{ route('admin.audit-log.index') }}" @if (request()->routeIs('admin.audit-log.*')) aria-current="page" @endif>Audit log</a>
                @endif
                <a href="{{ route('catalog.index') }}">View catalog</a>
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="user-box">
                @csrf
                <span>{{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})</span>
                <button type="submit" class="link-btn">Sign out</button>
            </form>
        </div>
    </header>

    <main class="container">
        @if (session('success'))
            <div class="alert" role="status">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-error" role="alert">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>

    <script src="{{ asset('js/admin.js') }}" defer></script>
    <script src="{{ asset('js/menu.js') }}" defer></script>
    <script src="{{ asset('js/reservation.js') }}" defer></script>
</body>
</html>

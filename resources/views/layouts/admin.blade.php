<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/calendar.css') }}">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>

            <nav>
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('admin.gowns.index') }}">Gowns</a>
                <a href="{{ route('admin.reservations.index') }}">Reservations</a>
                <a href="{{ route('admin.walk-ins.create') }}">Walk-in booking</a>
                @if (auth()->user()->role === 'admin')
                    <a href="{{ route('admin.reports.index') }}">Reports</a>
                    <a href="{{ route('admin.audit-log.index') }}">Audit log</a>
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
    <script src="{{ asset('js/reservation.js') }}" defer></script>
</body>
</html>

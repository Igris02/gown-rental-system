<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/catalog.css') }}">
    <link rel="stylesheet" href="{{ asset('css/calendar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/menu.css') }}">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('catalog.index') }}">{{ config('app.name') }}</a>

            <button type="button" class="menu-toggle" data-menu-toggle aria-expanded="false" aria-controls="site-menu" aria-label="Open menu">
                <span class="menu-bars" aria-hidden="true"></span>
                <span data-menu-label>Menu</span>
            </button>

            <nav id="site-menu" aria-label="Main menu">
                <a href="{{ route('catalog.index') }}" @if (request()->routeIs('catalog.*')) aria-current="page" @endif>Catalog</a>
                @auth
                    <a href="{{ route('reservations.index') }}" @if (request()->routeIs('reservations.*')) aria-current="page" @endif>My reservations</a>
                    {{-- Only shown in the phone menu --}}
                    <a href="{{ route('dashboard') }}" class="mobile-only">Dashboard</a>
                @endauth
            </nav>

            <div class="user-box">
                @auth
                    <a href="{{ route('dashboard') }}">{{ auth()->user()->name }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="link-btn">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Sign in</a>
                    <a href="{{ route('register') }}" class="btn btn-small">Sign up</a>
                @endauth
            </div>
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

    <footer class="footer">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </footer>

    <script src="{{ asset('js/catalog.js') }}" defer></script>
    <script src="{{ asset('js/reservation.js') }}" defer></script>
    <script src="{{ asset('js/menu.js') }}" defer></script>
</body>
</html>

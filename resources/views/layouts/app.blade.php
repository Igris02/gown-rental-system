<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body>
    <main class="card">
        <div class="brand">{{ config('app.name') }}</div>
        @yield('content')
    </main>

    <script src="{{ asset('js/auth.js') }}" defer></script>
</body>
</html>

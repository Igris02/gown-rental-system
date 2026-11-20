@extends('layouts.app')

@section('title', 'Sign In')

@section('content')
    <h1>Sign in</h1>
    <p class="subtitle">Enter your details to continue.</p>

    <form method="POST" action="{{ route('login.submit') }}" data-auth-form>
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="email"
                autofocus
                required
                class="@error('email') is-invalid @enderror"
            >
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password">Password</label>
            <div class="password-wrap">
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    class="@error('password') is-invalid @enderror"
                >
                <button type="button" class="toggle" data-toggle-password="password">Show</button>
            </div>
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <label class="remember">
            <input type="checkbox" name="remember" value="1">
            Remember me
        </label>

        <button type="submit" class="btn">Sign in</button>
    </form>

    <p class="switch">No account yet? <a href="{{ route('register') }}">Sign up</a></p>
@endsection

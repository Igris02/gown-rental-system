@extends('layouts.app')

@section('title', 'Sign Up')

@section('content')
    <h1>Create account</h1>
    <p class="subtitle">Sign up to browse and reserve gowns.</p>

    <form method="POST" action="{{ route('register.submit') }}" data-auth-form>
        @csrf

        <div class="field">
            <label for="name">Full name</label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name') }}"
                autocomplete="name"
                autofocus
                required
                class="@error('name') is-invalid @enderror"
            >
            @error('name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="email"
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
                    autocomplete="new-password"
                    minlength="8"
                    required
                    class="@error('password') is-invalid @enderror"
                >
                <button type="button" class="toggle" data-toggle-password="password">Show</button>
            </div>
            <p class="hint">At least 8 characters.</p>
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <div class="password-wrap">
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required
                >
                <button type="button" class="toggle" data-toggle-password="password_confirmation">Show</button>
            </div>
            <p class="error" data-match-error hidden>Passwords do not match.</p>
        </div>

        <button type="submit" class="btn">Sign up</button>
    </form>

    <p class="switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection

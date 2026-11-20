@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Welcome, {{ auth()->user()->name }}</h1>
    <p class="subtitle">You are signed in.</p>

    <dl class="details">
        <dt>Email</dt>
        <dd>{{ auth()->user()->email }}</dd>
        <dt>Role</dt>
        <dd>{{ ucfirst(auth()->user()->role) }}</dd>
    </dl>

    <p class="switch" style="margin: 0 0 8px; text-align: left;">
        <a href="{{ route('catalog.index') }}">Browse the gown catalog</a>
    </p>

    <p class="switch" style="margin: 0 0 8px; text-align: left;">
        <a href="{{ route('reservations.index') }}">My reservations</a>
    </p>

    @if (in_array(auth()->user()->role, ['admin', 'staff'], true))
        <p class="switch" style="margin: 0 0 8px; text-align: left;">
            <a href="{{ route('admin.gowns.index') }}">Manage gowns</a>
        </p>

        <p class="switch" style="margin: 0 0 8px; text-align: left;">
            <a href="{{ route('admin.reservations.index') }}">Manage reservations</a>
        </p>

        <p class="switch" style="margin: 0 0 16px; text-align: left;">
            <a href="{{ route('admin.walk-ins.create') }}">New walk-in booking</a>
        </p>

        @if (auth()->user()->role === 'admin')
            <p class="switch" style="margin: 0 0 8px; text-align: left;">
                <a href="{{ route('admin.reports.index') }}">Reports</a>
            </p>

            <p class="switch" style="margin: 0 0 16px; text-align: left;">
                <a href="{{ route('admin.audit-log.index') }}">Audit log</a>
            </p>
        @endif
    @endif

    <form method="POST" action="{{ route('logout') }}" style="margin-top: 16px;">
        @csrf
        <button type="submit" class="btn">Sign out</button>
    </form>
@endsection

@extends('layouts.admin')

@section('title', 'New Walk-in Booking')

@section('content')
    <div class="page-head">
        <h1>New walk-in booking</h1>
        <a href="{{ route('admin.walk-ins.create', ['date' => $selected ?: null]) }}">Change gown</a>
    </div>

    <p class="step-note"><strong>Step 2 of 2.</strong> Choose the dates and enter the customer's details.</p>

    <div class="walkin-gown">
        @if ($gown->thumbnail_url)
            <img src="{{ $gown->thumbnail_url }}" alt="{{ $gown->name }}" class="thumb">
        @else
            <span class="thumb thumb-empty">No photo</span>
        @endif

        <div>
            <strong>{{ $gown->name }}</strong> ({{ $gown->gown_code }})<br>
            {{ $gown->design }} &middot; {{ $gown->color }} &middot; Size {{ $gown->size }} &middot; {{ $gown->silhouette }}<br>
            <strong>{{ $gown->formatted_price }}</strong>
        </div>
    </div>

    <div class="walkin">
        @include('partials.reserve-form', ['walkIn' => true])
    </div>
@endsection

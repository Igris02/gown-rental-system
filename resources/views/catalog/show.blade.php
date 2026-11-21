@extends('layouts.public')

@section('title', $gown->name)

@section('content')
    <p class="back"><a href="{{ route('catalog.index') }}">&larr; Back to catalog</a></p>

    <div class="detail">
        <div class="detail-media">
            <div class="detail-image">
                @include('partials.photo-zoom', ['gown' => $gown])
            </div>

            @include('partials.extra-photos', ['gown' => $gown])
        </div>

        <div class="detail-info">
            <p class="card-code">{{ $gown->gown_code }}</p>
            <h1>{{ $gown->name }}</h1>
            <p class="detail-price">{{ $gown->formatted_price }}</p>
            <span class="badge">Available</span>

            <dl class="specs">
                <dt>Design</dt>
                <dd>{{ $gown->design }}</dd>
                <dt>Color</dt>
                <dd>{{ $gown->color }}</dd>
                <dt>Size</dt>
                <dd>{{ $gown->size_label }}</dd>
                <dt>Silhouette</dt>
                <dd>{{ $gown->silhouette }}</dd>
            </dl>

            @if ($gown->description)
                <p class="description">{!! nl2br(e($gown->description)) !!}</p>
            @endif

            @include('partials.reserve-form')
        </div>
    </div>
@endsection

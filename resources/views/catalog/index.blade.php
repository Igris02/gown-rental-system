@extends('layouts.public')

@section('title', 'Gown Catalog')

@section('content')
    <div class="page-head">
        <h1>Gown catalog</h1>
        <p class="count">{{ $gowns->total() }} {{ \Illuminate\Support\Str::plural('gown', $gowns->total()) }} found</p>
    </div>

    <form method="GET" action="{{ route('catalog.index') }}" class="filters" data-filters>
        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search name, design, color, or code"
            aria-label="Search gowns"
        >

        <select name="color" aria-label="Filter by color">
            <option value="">All colors</option>
            @foreach ($colors as $color)
                <option value="{{ $color }}" @selected(request('color') === $color)>{{ $color }}</option>
            @endforeach
        </select>

        <select name="size" aria-label="Filter by size">
            <option value="">All sizes</option>
            @foreach ($sizes as $size)
                <option value="{{ $size }}" @selected(request('size') === $size)>Size {{ $size }}</option>
            @endforeach
        </select>

        <select name="silhouette" aria-label="Filter by silhouette">
            <option value="">All silhouettes</option>
            @foreach ($silhouettes as $silhouette)
                <option value="{{ $silhouette }}" @selected(request('silhouette') === $silhouette)>{{ $silhouette }}</option>
            @endforeach
        </select>

        <label class="date-filter">
            <span>Pick-up date</span>
            <input
                type="date"
                name="date"
                value="{{ $pickup }}"
                min="{{ $minDate }}"
                max="{{ $maxDate }}"
                aria-label="Show only gowns that are free for 3 days starting on this date"
            >
        </label>

        <select name="sort" aria-label="Sort gowns">
            <option value="newest" @selected($sort === 'newest')>Newest</option>
            <option value="price_low" @selected($sort === 'price_low')>Price: low to high</option>
            <option value="price_high" @selected($sort === 'price_high')>Price: high to low</option>
            <option value="name" @selected($sort === 'name')>Name: A to Z</option>
        </select>

        <button type="submit" class="btn">Search</button>

        @if ($hasFilters)
            <a href="{{ route('catalog.index') }}">Clear</a>
        @endif
    </form>

    @if ($pickup)
        <p class="date-note">
            Showing gowns that are free for all 3 days:
            pick up {{ \Illuminate\Support\Carbon::parse($pickup)->format('D, M j') }},
            return {{ \Illuminate\Support\Carbon::parse($pickupEnd)->format('D, M j, Y') }}.
        </p>
    @endif

    @if ($gowns->count())
        <div class="grid">
            @foreach ($gowns as $gown)
                <a href="{{ route('catalog.show', ['gown' => $gown, 'date' => $pickup]) }}" class="card">
                    <div class="card-image">
                        @if ($gown->card_url)
                            <img src="{{ $gown->card_url }}" alt="{{ $gown->name }}" loading="lazy">
                        @else
                            <span>No photo</span>
                        @endif
                    </div>

                    <div class="card-body">
                        <p class="card-code">{{ $gown->gown_code }}</p>
                        <h2>{{ $gown->name }}</h2>
                        <p class="card-meta">{{ $gown->color }} &middot; {{ $gown->silhouette }} &middot; Size {{ $gown->size }}</p>
                        <p class="card-price">{{ $gown->formatted_price }}</p>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($gowns->hasPages())
            <div class="pager">
                @if ($gowns->onFirstPage())
                    <span class="pager-off">Previous</span>
                @else
                    <a href="{{ $gowns->previousPageUrl() }}">Previous</a>
                @endif

                <span>Page {{ $gowns->currentPage() }} of {{ $gowns->lastPage() }}</span>

                @if ($gowns->hasMorePages())
                    <a href="{{ $gowns->nextPageUrl() }}">Next</a>
                @else
                    <span class="pager-off">Next</span>
                @endif
            </div>
        @endif
    @else
        <div class="empty">
            <p>No gowns match your search.</p>
            @if ($hasFilters)
                <p><a href="{{ route('catalog.index') }}">Clear filters and see all gowns</a></p>
            @endif
        </div>
    @endif
@endsection

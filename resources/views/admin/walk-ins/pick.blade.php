@extends('layouts.admin')

@section('title', 'New Walk-in Booking')

@section('content')
    <div class="page-head">
        <h1>New walk-in booking</h1>
        <a href="{{ route('admin.reservations.index') }}">Back to reservations</a>
    </div>

    <p class="step-note"><strong>Step 1 of 2.</strong> Choose the gown the customer wants.</p>

    @if ($gownNotFound)
        <div class="alert alert-error" role="alert">That gown is not available. Please choose another one.</div>
    @endif

    <form method="GET" action="{{ route('admin.walk-ins.create') }}" class="filters">
        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search name, design, color, or code"
            aria-label="Search gowns"
        >

        <label class="date-filter">
            <span>Free from</span>
            <input
                type="date"
                name="date"
                value="{{ $pickup }}"
                min="{{ $minDate }}"
                max="{{ $maxDate }}"
                aria-label="Show only gowns that are free for 3 days starting on this date"
            >
        </label>

        <button type="submit" class="btn btn-outline">Filter</button>

        @if (request()->hasAny(['search', 'date']))
            <a href="{{ route('admin.walk-ins.create') }}">Clear</a>
        @endif
    </form>

    @if ($pickup)
        <p class="date-note">
            Showing gowns that are free for all 3 days:
            pick up {{ \Illuminate\Support\Carbon::parse($pickup)->format('D, M j') }},
            return {{ \Illuminate\Support\Carbon::parse($pickupEnd)->format('D, M j, Y') }}.
        </p>
    @endif

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Design</th>
                    <th>Color</th>
                    <th>Size</th>
                    <th>Silhouette</th>
                    <th>Price</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($gowns as $gown)
                    <tr>
                        <td>
                            @if ($gown->thumbnail_url)
                                <img src="{{ $gown->thumbnail_url }}" alt="{{ $gown->name }}" class="thumb" loading="lazy">
                            @else
                                <span class="thumb thumb-empty">No photo</span>
                            @endif
                        </td>
                        <td>{{ $gown->gown_code }}</td>
                        <td>{{ $gown->name }}</td>
                        <td>{{ $gown->design }}</td>
                        <td>{{ $gown->color }}</td>
                        <td>{{ $gown->size }}</td>
                        <td>{{ $gown->silhouette }}</td>
                        <td>{{ $gown->formatted_price }}</td>
                        <td><a href="{{ route('admin.walk-ins.create', ['gown' => $gown->id, 'date' => $pickup]) }}">Select</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="empty">No available gowns found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
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
@endsection

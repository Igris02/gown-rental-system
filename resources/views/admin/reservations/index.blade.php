@extends('layouts.admin')

@section('title', 'Reservations')

@section('content')
    <div class="page-head">
        <h1>Reservations</h1>
        <a href="{{ route('admin.walk-ins.create') }}" class="btn">New walk-in booking</a>
    </div>

    <form method="GET" action="{{ route('admin.reservations.index') }}" class="filters">
        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search reference, customer, phone, or gown"
            aria-label="Search reservations"
        >

        <select name="status" aria-label="Filter by status">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>

        <select name="source" aria-label="Filter by where the booking came from">
            <option value="">Online and walk-in</option>
            <option value="online" @selected(request('source') === 'online')>Online only</option>
            <option value="walk_in" @selected(request('source') === 'walk_in')>Walk-in only</option>
        </select>

        <input type="date" name="date" value="{{ request('date') }}" aria-label="Show reservations that include this day">

        <button type="submit" class="btn btn-outline">Filter</button>

        @if (request()->hasAny(['search', 'status', 'source', 'date']))
            <a href="{{ route('admin.reservations.index') }}">Clear</a>
        @endif
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Gown</th>
                    <th>Day 1 &middot; Pick up</th>
                    <th>Day 2 &middot; Use</th>
                    <th>Day 3 &middot; Return</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reservations as $reservation)
                    <tr>
                        <td>{{ $reservation->reference }}</td>
                        <td>
                            <strong>{{ $reservation->customer_display_name }}</strong>
                            @if ($reservation->isWalkIn())
                                <span class="tag">Walk-in</span>
                            @endif
                            <br>
                            {{ $reservation->customer_display_contact }}
                            @if ($reservation->isWalkIn() && $reservation->creator)
                                <br><small>Booked by {{ $reservation->creator->name }}</small>
                            @endif
                        </td>
                        <td>
                            <div class="gown-cell">
                                @if ($reservation->gown->thumbnail_url)
                                    <img src="{{ $reservation->gown->thumbnail_url }}" alt="" class="thumb" loading="lazy">
                                @endif
                                <span>
                                    <strong>{{ $reservation->gown->name }}</strong><br>
                                    {{ $reservation->gown->gown_code }}@if ($reservation->size)<br>Size {{ $reservation->size }}@endif
                                </span>
                            </div>
                        </td>
                        <td>{{ $reservation->start_date->format('D, M j, Y') }}</td>
                        <td>{{ $reservation->use_date->format('D, M j, Y') }}</td>
                        <td>{{ $reservation->end_date->format('D, M j, Y') }}</td>
                        <td>{{ $reservation->formatted_price }}</td>
                        <td><span class="badge badge-{{ $reservation->status }}">{{ $reservation->status_label }}</span></td>
                        <td class="actions">
                            @foreach ($reservation->nextStatuses() as $next)
                                <form
                                    method="POST"
                                    action="{{ route('admin.reservations.status', $reservation) }}"
                                    @if (in_array($next, ['rejected', 'cancelled'], true))
                                        data-confirm="{{ \App\Models\Reservation::ACTION_LABELS[$next] }} reservation {{ $reservation->reference }}? The gown's days will be released."
                                    @endif
                                >
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $next }}">
                                    <button type="submit" class="link-btn">{{ \App\Models\Reservation::ACTION_LABELS[$next] }}</button>
                                </form>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="empty">No reservations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($reservations->hasPages())
        <div class="pager">
            @if ($reservations->onFirstPage())
                <span class="pager-off">Previous</span>
            @else
                <a href="{{ $reservations->previousPageUrl() }}">Previous</a>
            @endif

            <span>Page {{ $reservations->currentPage() }} of {{ $reservations->lastPage() }}</span>

            @if ($reservations->hasMorePages())
                <a href="{{ $reservations->nextPageUrl() }}">Next</a>
            @else
                <span class="pager-off">Next</span>
            @endif
        </div>
    @endif
@endsection

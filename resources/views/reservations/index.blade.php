@extends('layouts.public')

@section('title', 'My Reservations')

@section('content')
    <div class="page-head">
        <h1>My reservations</h1>
        <a href="{{ route('catalog.index') }}" class="btn btn-small">Browse gowns</a>
    </div>

    @if ($reservations->count())
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Gown</th>
                        <th>Day 1 &middot; Pick up</th>
                        <th>Day 2 &middot; Use</th>
                        <th>Day 3 &middot; Return</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reservations as $reservation)
                        <tr>
                            <td>{{ $reservation->reference }}</td>
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
                            <td>
                                @if ($reservation->canBeCancelledByCustomer())
                                    <form
                                        method="POST"
                                        action="{{ route('reservations.cancel', $reservation) }}"
                                        data-confirm="Cancel reservation {{ $reservation->reference }} for {{ $reservation->gown->name }}?"
                                    >
                                        @csrf
                                        <button type="submit" class="link-btn">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
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

        <p class="hint">You can cancel a reservation until the day before pick-up. After that, please contact the shop.</p>
    @else
        <div class="empty">
            <p>You have no reservations yet.</p>
            <p><a href="{{ route('catalog.index') }}">Browse the gown catalog</a></p>
        </div>
    @endif
@endsection

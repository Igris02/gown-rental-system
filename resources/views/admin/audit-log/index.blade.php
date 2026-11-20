@extends('layouts.admin')

@section('title', 'Audit Log')

@section('content')
    <div class="audit">
    <div class="page-head">
        <h1>Audit log</h1>
        <a href="{{ route('admin.audit-log.export', request()->query()) }}" class="btn btn-outline">Export CSV</a>
    </div>

    <p class="step-note">A record of who did what and when. Entries cannot be edited or deleted here. Times are shown in shop time ({{ $timezone }}).</p>

    <form method="GET" action="{{ route('admin.audit-log.index') }}" class="filters">
        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search person, item, or description"
            aria-label="Search the audit log"
        >

        <select name="category" aria-label="Filter by type">
            <option value="">All types</option>
            @foreach ($categories as $key => $label)
                <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <label class="date-filter">
            <span>From</span>
            <input type="date" name="from" value="{{ request('from') }}" aria-label="From date">
        </label>

        <label class="date-filter">
            <span>To</span>
            <input type="date" name="to" value="{{ request('to') }}" aria-label="To date">
        </label>

        <button type="submit" class="btn btn-outline">Filter</button>

        @if (request()->hasAny(['search', 'category', 'from', 'to']))
            <a href="{{ route('admin.audit-log.index') }}">Clear</a>
        @endif
    </form>

    @if ($logs->total() > $exportLimit)
        <p class="date-note">{{ number_format($logs->total()) }} entries match. The CSV holds the newest {{ number_format($exportLimit) }}, so narrow the dates to export the rest.</p>
    @endif

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Who</th>
                    <th>Action</th>
                    <th>Item</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->copy()->timezone($timezone)->format('M j, Y') }}<br>{{ $log->created_at->copy()->timezone($timezone)->format('g:i:s A') }}</td>
                        <td>
                            @if ($log->user_name)
                                <strong>{{ $log->user_name }}</strong><br>
                                <small>{{ ucfirst((string) $log->user_role) }}</small>
                            @else
                                <strong>System</strong>
                            @endif
                        </td>
                        <td><span class="tag">{{ $log->action_label }}</span></td>
                        <td>{{ $log->subject_label }}</td>
                        <td>
                            {{ $log->description }}

                            @if (! empty($log->changes) && $log->action !== 'reservation.created')
                                <details class="changes">
                                    <summary>Show changes</summary>
                                    <ul>
                                        @foreach ($log->changes as $field => $change)
                                            <li>
                                                <strong>{{ $field }}:</strong>
                                                @if (is_array($change) && count($change) === 2)
                                                    {{ is_scalar($change[0]) && $change[0] !== '' ? $change[0] : '(empty)' }}
                                                    &rarr;
                                                    {{ is_scalar($change[1]) && $change[1] !== '' ? $change[1] : '(empty)' }}
                                                @else
                                                    {{ is_scalar($change) ? $change : json_encode($change) }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif

                            @if ($log->ip_address)
                                <br><small>IP {{ $log->ip_address }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty">No entries found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        <div class="pager">
            @if ($logs->onFirstPage())
                <span class="pager-off">Previous</span>
            @else
                <a href="{{ $logs->previousPageUrl() }}">Previous</a>
            @endif

            <span>Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>

            @if ($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}">Next</a>
            @else
                <span class="pager-off">Next</span>
            @endif
        </div>
    @endif
    </div>
@endsection

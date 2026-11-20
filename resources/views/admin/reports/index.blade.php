@extends('layouts.admin')

@section('title', 'Reports')

@php
    $dur = fn ($m) => $m === null ? '—' : \App\Services\ReportService::duration($m);
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
    $f = $r['online_funnel'];
    $resp = $r['response'];
    $timeT = $r['targets']['time'];
    $compT = $r['targets']['completion'];
    $points = $r['series']['points'];
    $peak = max(1, max(array_map(fn ($p) => $p['online'] + $p['walk_in'], $points)));
    $labelStep = max(1, (int) ceil(count($points) / 10));
    $unitName = ['day' => 'day', 'week' => 'week (starting Monday)', 'month' => 'month'][$r['series']['unit']];
    $badge = fn ($met, $none = 'Needs data') => $met === null ? $none : ($met ? 'Met' : 'Not met yet');
@endphp

@section('content')
    <div class="report">
    <div class="page-head">
        <h1>Reports</h1>
        <a href="{{ route('admin.reports.export', request()->query()) }}" class="btn btn-outline">Export reservations CSV</a>
    </div>

    <p class="step-note">
        Reservations requested from <strong>{{ \Illuminate\Support\Carbon::parse($from)->format('M j, Y') }}</strong>
        to <strong>{{ \Illuminate\Support\Carbon::parse($to)->format('M j, Y') }}</strong> ({{ $timezone }}).
    </p>

    <div class="presets">
        @foreach ($presets as $key => $label)
            <a href="{{ route('admin.reports.index', ['preset' => $key]) }}" @class(['is-active' => $active === $key])>{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.reports.index') }}" class="filters">
        <label class="date-filter">
            <span>From</span>
            <input type="date" name="from" value="{{ $from }}" max="{{ $today }}" aria-label="From date">
        </label>

        <label class="date-filter">
            <span>To</span>
            <input type="date" name="to" value="{{ $to }}" max="{{ $today }}" aria-label="To date">
        </label>

        <button type="submit" class="btn btn-outline">Show</button>
    </form>

    {{-- Headline numbers --}}
    <div class="tiles">
        <div class="tile">
            <span class="tile-label">Reservations</span>
            <span class="tile-big">{{ number_format($r['total']) }}</span>
            <span class="tile-note">{{ $r['online'] }} online &middot; {{ $r['walk_in'] }} walk-in</span>
        </div>
        <div class="tile">
            <span class="tile-label">Booked by customers online</span>
            <span class="tile-big">{{ $pct($r['self_service_share']) }}</span>
            <span class="tile-note">No staff typing needed</span>
        </div>
        <div class="tile">
            <span class="tile-label">Online requests confirmed</span>
            <span class="tile-big">{{ $pct($f['completion_rate']) }}</span>
            <span class="tile-note">{{ $f['confirmed'] }} of {{ $f['submitted'] }} requests</span>
        </div>
        <div class="tile">
            <span class="tile-label">Average wait for confirmation</span>
            <span class="tile-big tile-small">{{ $dur($resp['average']) }}</span>
            <span class="tile-note">Median {{ $dur($resp['median']) }}</span>
        </div>
    </div>

    {{-- The study's objectives --}}
    <h2>Objectives</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Objective</th>
                    <th>Result</th>
                    <th>Target</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Cut staff time and labor hours on scheduling and routine reservation work</td>
                    <td>
                        @if ($r['self_service_share'] === null)
                            No reservations in this period.
                        @else
                            {{ $pct($r['self_service_share']) }} of reservations were made by customers themselves ({{ $r['online'] }} of {{ $r['total'] }}).
                        @endif
                    </td>
                    <td>40% less scheduling time, 35% fewer labor hours</td>
                    <td><span class="tag">Measure by time study</span></td>
                </tr>
                <tr>
                    <td>Fewer routine catalog and availability inquiries to staff</td>
                    <td>Customers can see availability on the catalog and calendar without asking.</td>
                    <td>50% fewer inquiries</td>
                    <td><span class="tag">Count chat inquiries before and after</span></td>
                </tr>
                <tr>
                    <td>No double bookings</td>
                    <td>
                        {{ $r['double_bookings'] }} clashing {{ $r['double_bookings'] === 1 ? 'pair' : 'pairs' }}
                        among all active reservations (all time, not only this period).
                    </td>
                    <td>0</td>
                    <td><span class="tag">{{ $r['double_bookings'] === 0 ? 'Met' : 'Not met yet' }}</span></td>
                </tr>
                <tr>
                    <td>Faster time to a confirmed reservation</td>
                    <td>
                        @if ($timeT['average'] === null)
                            No online request has been confirmed in this period yet.
                        @else
                            Average wait {{ $dur($timeT['average']) }}
                            @if ($timeT['baseline'])
                                vs {{ $dur($timeT['baseline']) }} before the system
                                @if ($timeT['improvement'] !== null)
                                    ({{ $pct($timeT['improvement']) }} faster)
                                @endif
                            @endif
                            <br><small>Based on {{ $resp['count'] }} confirmed online {{ $resp['count'] === 1 ? 'request' : 'requests' }}.</small>
                        @endif
                    </td>
                    <td>
                        {{ $timeT['target'] }}% faster
                        @if ($timeT['target_minutes'] !== null)
                            <br><small>That is {{ $dur($timeT['target_minutes']) }} or less</small>
                        @endif
                    </td>
                    <td>
                        <span class="tag">{{ $badge($timeT['met'], $timeT['baseline'] ? 'Needs data' : 'Needs baseline') }}</span>
                        @if ($timeT['baseline'] === null)
                            <br><small>{{ $timeT['reason'] }}</small>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Online requests that end in a confirmed reservation</td>
                    <td>
                        @if ($compT['value'] === null)
                            No online requests in this period.
                        @else
                            {{ $pct($compT['value']) }} ({{ $f['confirmed'] }} of {{ $f['submitted'] }})
                        @endif
                    </td>
                    <td>At least {{ rtrim(rtrim(number_format($compT['target'], 1), '0'), '.') }}%</td>
                    <td><span class="tag">{{ $badge($compT['met']) }}</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Requests over time --}}
    <h2>Reservations requested, per {{ $unitName }}</h2>
    <div class="bars" role="img" aria-label="Bar chart of reservations requested per {{ $r['series']['unit'] }}. The table below has the same numbers.">
        @foreach ($points as $i => $p)
            @php($count = $p['online'] + $p['walk_in'])
            <div class="bar-col" title="{{ $p['label'] }}: {{ $p['online'] }} online, {{ $p['walk_in'] }} walk-in">
                <span class="bar-count">{{ $count ?: '' }}</span>
                <div class="bar" style="height: {{ round($count / $peak * 100) }}%">
                    <span class="bar-walkin" style="flex: {{ $p['walk_in'] }}"></span>
                    <span class="bar-online" style="flex: {{ $p['online'] }}"></span>
                </div>
                <span class="bar-label">{{ $i % $labelStep === 0 ? $p['label'] : '' }}</span>
            </div>
        @endforeach
    </div>
    <div class="cal-legend">
        <span><i class="sw sw-pickup"></i>Online</span>
        <span><i class="sw sw-booked"></i>Walk-in</span>
    </div>

    <details class="changes">
        <summary>Show these numbers as a table</summary>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>{{ ucfirst($r['series']['unit']) }}</th><th>Online</th><th>Walk-in</th></tr>
                </thead>
                <tbody>
                    @foreach ($points as $p)
                        <tr><td>{{ $p['label'] }}</td><td>{{ $p['online'] }}</td><td>{{ $p['walk_in'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    <div class="report-columns">
        <div>
            <h2>Where reservations ended up</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Status</th><th>Reservations</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($r['status'] as $status => $count)
                            <tr>
                                <td><span class="badge badge-{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span></td>
                                <td>{{ $count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="date-note">Value of confirmed, picked-up, and returned reservations: <strong>PHP {{ number_format($r['booked_value'], 2) }}</strong></p>
        </div>

        <div>
            <h2>Online requests</h2>
            <div class="table-wrap">
                <table>
                    <tbody>
                        <tr><td>Sent by customers</td><td>{{ $f['submitted'] }}</td></tr>
                        <tr><td>Confirmed by the shop</td><td>{{ $f['confirmed'] }}</td></tr>
                        <tr><td>Rejected by the shop</td><td>{{ $f['rejected'] }}</td></tr>
                        <tr><td>Cancelled before confirmation</td><td>{{ $f['cancelled_before_confirmation'] }}</td></tr>
                        <tr><td>Still waiting for the shop</td><td>{{ $f['waiting'] }}</td></tr>
                        <tr><td>Confirmed within 1 hour</td><td>{{ $pct($resp['within_hour']) }}</td></tr>
                        <tr><td>Confirmed within 24 hours</td><td>{{ $pct($resp['within_day']) }}</td></tr>
                        <tr><td>Fastest / slowest</td><td>{{ $dur($resp['fastest']) }} / {{ $dur($resp['slowest']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <h2>Most reserved gowns</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Code</th><th>Name</th><th>Reservations</th></tr>
            </thead>
            <tbody>
                @forelse ($r['top_gowns'] as $gown)
                    <tr><td>{{ $gown['code'] }}</td><td>{{ $gown['name'] }}</td><td>{{ $gown['count'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="empty">No reservations in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <details class="changes">
        <summary>How these numbers are worked out</summary>
        <ul>
            <li>Every figure counts reservations by the day they were <strong>requested</strong>, in shop time.</li>
            <li><strong>Walk-ins</strong> are confirmed on the spot, so they are left out of the wait and confirmation figures.</li>
            <li><strong>Wait for confirmation</strong> is the time from the customer's request to the first time the shop confirmed it.</li>
            <li><strong>Confirmed</strong> counts an online request once the shop has confirmed it, even if it was later picked up, returned, or cancelled.</li>
            <li>A customer who starts the form and leaves without sending it cannot be seen by the system, so the confirmed rate is measured from requests actually sent.</li>
            <li><strong>Clashing pairs</strong> are two active reservations (pending, confirmed, or picked up) holding the same gown on overlapping days.</li>
            <li>Reservations made before the reports were added use an estimated confirmation time, so older wait times are approximate.</li>
        </ul>
    </details>
    </div>
@endsection

<?php

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the reports page, matched to the study's specific objectives.
 *
 * compute() only works on plain arrays, so every figure can be checked by hand.
 * Everything is counted by the day the reservation was REQUESTED (created), in shop time.
 */
class ReportService
{
    /** Pairs of active reservations that hold the same gown on overlapping days. It should always be 0. */
    public const DOUBLE_BOOKING_SQL = <<<'SQL'
        SELECT COUNT(*) AS pairs
        FROM reservations a
        JOIN reservations b
          ON a.gown_id = b.gown_id
         AND a.id < b.id
         AND a.start_date <= b.end_date
         AND a.end_date >= b.start_date
        JOIN gowns g ON g.id = a.gown_id
        WHERE a.status IN ('pending', 'confirmed', 'picked_up')
          AND b.status IN ('pending', 'confirmed', 'picked_up')
          AND (g.size_type = 'single' OR a.size = b.size)
    SQL;

    private const CONFIRMED_STATES = ['confirmed', 'picked_up', 'returned'];

    private const STATUS_ORDER = ['pending', 'confirmed', 'picked_up', 'returned', 'rejected', 'cancelled'];

    // ---- Database side ---------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function report(string $from, string $to): array
    {
        return self::compute($this->rows($from, $to), $this->options($from, $to)) + [
            'double_bookings' => $this->doubleBookings(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $from, string $to): array
    {
        [$start, $end] = self::dbRange($from, $to);

        return DB::table('reservations as r')
            ->join('gowns as g', 'g.id', '=', 'r.gown_id') // also finds gowns that were deleted later
            ->where('r.created_at', '>=', $start)
            ->where('r.created_at', '<', $end)
            ->orderBy('r.id')
            ->get([
                'r.id', 'r.created_at', 'r.source', 'r.status', 'r.confirmed_at',
                'r.gown_id', 'g.gown_code', 'g.name as gown_name',
                'r.start_date', 'r.end_date', 'r.rental_price',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function doubleBookings(): int
    {
        return (int) (DB::select(self::DOUBLE_BOOKING_SQL)[0]->pairs ?? 0);
    }

    /**
     * The first day (in shop time) that any reservation was requested, or today when there are none.
     */
    public function earliestDay(): string
    {
        $first = DB::table('reservations')->min('created_at');

        if (! $first) {
            return ReservationService::today();
        }

        return (new DateTimeImmutable((string) $first, new DateTimeZone(config('app.timezone'))))
            ->setTimezone(new DateTimeZone(config('reservations.timezone', 'Asia/Manila')))
            ->format('Y-m-d');
    }

    /**
     * @return array<string, mixed>
     */
    public function options(string $from, string $to): array
    {
        return [
            'db_tz' => config('app.timezone'),
            'shop_tz' => config('reservations.timezone', 'Asia/Manila'),
            'from' => $from,
            'to' => $to,
            'baseline' => config('reports.baseline_confirm_minutes'),
            'target_time_reduction' => (float) config('reports.target_time_reduction_percent', 60),
            'target_completion' => (float) config('reports.target_completion_percent', 80),
            'top_n' => (int) config('reports.top_gowns', 5),
        ];
    }

    /**
     * Shop days (inclusive) to a database time range [start, end).
     *
     * @return array{0: string, 1: string}
     */
    public static function dbRange(string $from, string $to): array
    {
        $shop = new DateTimeZone(config('reservations.timezone', 'Asia/Manila'));
        $db = new DateTimeZone(config('app.timezone'));

        $start = (new DateTimeImmutable($from . ' 00:00:00', $shop))->setTimezone($db);
        $end = (new DateTimeImmutable($to . ' 00:00:00', $shop))->add(new DateInterval('P1D'))->setTimezone($db);

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    // ---- Pure side: easy to test -----------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $rows  each with id, created_at, source, status, confirmed_at,
     *        gown_id, gown_code, gown_name, start_date, end_date, rental_price
     * @param array<string, mixed> $o  options from options()
     * @return array<string, mixed>
     */
    public static function compute(array $rows, array $o): array
    {
        $db = new DateTimeZone($o['db_tz']);
        $shop = new DateTimeZone($o['shop_tz']);

        $total = count($rows);
        $online = $walkIn = 0;
        $status = array_fill_keys(self::STATUS_ORDER, 0);
        $value = 0.0;
        $submitted = $confirmedEver = $cancelledEarly = $rejected = $pending = 0;
        $minutes = [];
        $perGown = [];
        $byDay = [];

        foreach ($rows as $row) {
            $isWalkIn = $row['source'] === 'walk_in';
            $isWalkIn ? $walkIn++ : $online++;

            if (isset($status[$row['status']])) {
                $status[$row['status']]++;
            }

            $created = (new DateTimeImmutable((string) $row['created_at'], $db))->setTimezone($shop);
            $byDay[$created->format('Y-m-d')][$isWalkIn ? 'walk_in' : 'online'] = ($byDay[$created->format('Y-m-d')][$isWalkIn ? 'walk_in' : 'online'] ?? 0) + 1;

            $wasConfirmed = ! empty($row['confirmed_at']);

            if (in_array($row['status'], self::CONFIRMED_STATES, true)) {
                $value += (float) $row['rental_price'];
            }

            // A gown counts as "reserved" when the booking was not turned down or cancelled
            if (! in_array($row['status'], ['rejected', 'cancelled'], true)) {
                $key = $row['gown_id'];
                $perGown[$key] ??= ['code' => $row['gown_code'], 'name' => $row['gown_name'], 'count' => 0];
                $perGown[$key]['count']++;
            }

            if ($isWalkIn) {
                continue; // walk-ins are confirmed on the spot, so they say nothing about waiting or completion
            }

            $submitted++;

            if ($wasConfirmed) {
                $confirmedEver++;
                $confirmed = (new DateTimeImmutable((string) $row['confirmed_at'], $db));
                $minutes[] = max(0.0, ($confirmed->getTimestamp() - $created->getTimestamp()) / 60);
            } elseif ($row['status'] === 'rejected') {
                $rejected++;
            } elseif ($row['status'] === 'cancelled') {
                $cancelledEarly++;
            } elseif ($row['status'] === 'pending') {
                $pending++;
            }
        }

        $completion = $submitted > 0 ? round($confirmedEver / $submitted * 100, 1) : null;
        $response = self::responseStats($minutes);

        uasort($perGown, fn ($a, $b) => [$b['count'], $a['code']] <=> [$a['count'], $b['code']]);

        return [
            'from' => $o['from'],
            'to' => $o['to'],
            'total' => $total,
            'online' => $online,
            'walk_in' => $walkIn,
            'self_service_share' => $total > 0 ? round($online / $total * 100, 1) : null,
            'status' => $status,
            'booked_value' => round($value, 2),
            'online_funnel' => [
                'submitted' => $submitted,
                'confirmed' => $confirmedEver,
                'rejected' => $rejected,
                'cancelled_before_confirmation' => $cancelledEarly,
                'waiting' => $pending,
                'completion_rate' => $completion,
            ],
            'response' => $response,
            'targets' => [
                'completion' => [
                    'value' => $completion,
                    'target' => $o['target_completion'],
                    'met' => $completion === null ? null : $completion >= $o['target_completion'],
                ],
                'time' => self::timeTarget($response['average'], $o['baseline'], $o['target_time_reduction']),
            ],
            'series' => self::series($byDay, $o['from'], $o['to']),
            'top_gowns' => array_slice(array_values($perGown), 0, max(1, (int) $o['top_n'])),
        ];
    }

    /**
     * @param float[] $minutes
     * @return array{count:int, average:?float, median:?float, fastest:?float, slowest:?float, within_hour:?float, within_day:?float}
     */
    public static function responseStats(array $minutes): array
    {
        $n = count($minutes);

        if ($n === 0) {
            return ['count' => 0, 'average' => null, 'median' => null, 'fastest' => null, 'slowest' => null, 'within_hour' => null, 'within_day' => null];
        }

        sort($minutes);
        $mid = intdiv($n, 2);
        $median = $n % 2 ? $minutes[$mid] : ($minutes[$mid - 1] + $minutes[$mid]) / 2;

        return [
            'count' => $n,
            'average' => round(array_sum($minutes) / $n, 1),
            'median' => round($median, 1),
            'fastest' => round($minutes[0], 1),
            'slowest' => round($minutes[$n - 1], 1),
            'within_hour' => round(count(array_filter($minutes, fn ($m) => $m <= 60)) / $n * 100, 1),
            'within_day' => round(count(array_filter($minutes, fn ($m) => $m <= 1440)) / $n * 100, 1),
        ];
    }

    /**
     * Compares the average wait with the "before the system" baseline.
     *
     * @return array{baseline:?float, average:?float, target_minutes:?float, improvement:?float, target:float, met:?bool, reason:?string}
     */
    public static function timeTarget(?float $average, ?float $baseline, float $reduction): array
    {
        $out = ['baseline' => $baseline, 'average' => $average, 'target_minutes' => null, 'improvement' => null, 'target' => $reduction, 'met' => null, 'reason' => null];

        if ($baseline === null || $baseline <= 0) {
            $out['reason'] = 'Set REPORT_BASELINE_CONFIRM_MINUTES in .env to the usual wait before the system.';

            return $out;
        }

        $out['target_minutes'] = round($baseline * (1 - $reduction / 100), 1);

        if ($average === null) {
            $out['reason'] = 'No online reservation has been confirmed in this period yet.';

            return $out;
        }

        $out['improvement'] = round(($baseline - $average) / $baseline * 100, 1);
        $out['met'] = $average <= $out['target_minutes'];

        return $out;
    }

    /**
     * Counts per day, week, or month depending on how long the period is.
     *
     * @param array<string, array{online?:int, walk_in?:int}> $byDay
     * @return array{unit:string, points: array<int, array{label:string, online:int, walk_in:int}>}
     */
    public static function series(array $byDay, string $from, string $to): array
    {
        $start = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        $days = (int) $start->diff($end)->days + 1;

        $unit = $days <= 35 ? 'day' : ($days <= 365 ? 'week' : 'month');

        $bucketStart = function (DateTimeImmutable $d) use ($unit): DateTimeImmutable {
            return match ($unit) {
                'week' => $d->modify('monday this week'),
                'month' => $d->modify('first day of this month'),
                default => $d,
            };
        };

        $step = ['day' => 'P1D', 'week' => 'P7D', 'month' => 'P1M'][$unit];
        $points = [];

        for ($cursor = $bucketStart($start); $cursor <= $end; $cursor = $cursor->add(new DateInterval($step))) {
            $points[$cursor->format('Y-m-d')] = [
                'label' => $unit === 'month' ? $cursor->format('M Y') : $cursor->format('M j'),
                'online' => 0,
                'walk_in' => 0,
            ];
        }

        foreach ($byDay as $day => $counts) {
            $key = $bucketStart(new DateTimeImmutable($day))->format('Y-m-d');

            if (isset($points[$key])) {
                $points[$key]['online'] += $counts['online'] ?? 0;
                $points[$key]['walk_in'] += $counts['walk_in'] ?? 0;
            }
        }

        return ['unit' => $unit, 'points' => array_values($points)];
    }

    /**
     * "2 h 15 min" for a number of minutes.
     */
    public static function duration(?float $minutes): string
    {
        if ($minutes === null) {
            return 'No data yet';
        }

        $m = (int) round($minutes);

        if ($m < 1) {
            return 'under 1 min';
        }

        if ($m < 60) {
            return "{$m} min";
        }

        if ($m < 1440) {
            $h = intdiv($m, 60);
            $r = $m % 60;

            return $r ? "{$h} h {$r} min" : "{$h} h";
        }

        $d = intdiv($m, 1440);
        $h = intdiv($m % 1440, 60);

        return $h ? "{$d} d {$h} h" : "{$d} d";
    }

    /**
     * One CSV line per reservation. Reference numbers only: no names, phones, or emails.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<int, string>>
     */
    public static function csvRows(array $rows, string $dbTz, string $shopTz): array
    {
        $db = new DateTimeZone($dbTz);
        $shop = new DateTimeZone($shopTz);

        $out = [['Reference', 'Requested (' . $shopTz . ')', 'Source', 'Status', 'Gown code', 'Gown', 'Pick-up date', 'Return date', 'Price', 'Minutes to confirm']];

        foreach ($rows as $row) {
            $created = (new DateTimeImmutable((string) $row['created_at'], $db))->setTimezone($shop);
            $wait = '';

            if ($row['source'] !== 'walk_in' && ! empty($row['confirmed_at'])) {
                $confirmed = new DateTimeImmutable((string) $row['confirmed_at'], $db);
                $wait = (string) round(max(0, ($confirmed->getTimestamp() - $created->getTimestamp()) / 60), 1);
            }

            $out[] = [
                sprintf('R-%05d', $row['id']),
                $created->format('Y-m-d H:i:s'),
                $row['source'] === 'walk_in' ? 'Walk-in' : 'Online',
                str_replace('_', ' ', (string) $row['status']),
                (string) $row['gown_code'],
                (string) $row['gown_name'],
                substr((string) $row['start_date'], 0, 10),
                substr((string) $row['end_date'], 0, 10),
                number_format((float) $row['rental_price'], 2, '.', ''),
                $wait,
            ];
        }

        return $out;
    }
}

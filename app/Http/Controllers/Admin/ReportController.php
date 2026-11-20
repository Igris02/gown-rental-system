<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\ReportService;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports for the admin: the study's objectives, measured from real reservations.
 */
class ReportController extends Controller
{
    public const PRESETS = [
        '7' => 'Last 7 days',
        '30' => 'Last 30 days',
        '90' => 'Last 90 days',
        'month' => 'This month',
        'all' => 'All time',
    ];

    public function __construct(private ReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        [$from, $to, $active] = $this->period($request);

        return view('admin.reports.index', [
            'r' => $this->reports->report($from, $to),
            'from' => $from,
            'to' => $to,
            'active' => $active,
            'presets' => self::PRESETS,
            'today' => ReservationService::today(),
            'timezone' => config('reservations.timezone', 'Asia/Manila'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);

        $rows = $this->reports->rows($from, $to);
        $csv = ReportService::csvRows($rows, config('app.timezone'), config('reservations.timezone', 'Asia/Manila'));

        AuditLogger::record('report.exported', 'Exported ' . count($rows) . " reservations from {$from} to {$to} as CSV.");

        return response()->streamDownload(function () use ($csv) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($csv as $line) {
                fputcsv($out, $line);
            }

            fclose($out);
        }, "reservations-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Works out the period from the page's links or its date boxes.
     *
     * @return array{0: string, 1: string, 2: string} from, to, which preset is active ('custom' when typed in)
     */
    private function period(Request $request): array
    {
        $today = ReservationService::today();

        $from = ReservationService::parseDate($request->query('from'));
        $to = ReservationService::parseDate($request->query('to'));

        if ($from || $to) {
            $to = $to ?: $today;
            $from = $from ?: ReservationService::addDays($to, -29);
            $active = 'custom';
        } else {
            $preset = $request->query('preset');
            $active = is_string($preset) && isset(self::PRESETS[$preset]) ? $preset : '30';

            [$from, $to] = match ($active) {
                '7' => [ReservationService::addDays($today, -6), $today],
                '90' => [ReservationService::addDays($today, -89), $today],
                'month' => [substr($today, 0, 8) . '01', $today],
                'all' => [$this->reports->earliestDay(), $today],
                default => [ReservationService::addDays($today, -29), $today],
            };
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        // Nothing can be requested in the future
        if ($to > $today) {
            $to = $today;
        }

        if ($from > $to) {
            $from = $to;
        }

        return [$from, $to, $active];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The audit log: admins can read and export it. Nothing here edits or deletes entries.
 */
class AuditLogController extends Controller
{
    private const EXPORT_LIMIT = 5000;

    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->filter($this->filters($request))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit-log.index', [
            'logs' => $logs,
            'categories' => AuditLog::CATEGORIES,
            'timezone' => config('reservations.timezone', 'Asia/Manila'),
            'exportLimit' => self::EXPORT_LIMIT,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $timezone = config('reservations.timezone', 'Asia/Manila');

        $logs = AuditLog::query()
            ->filter($this->filters($request))
            ->orderByDesc('id')
            ->limit(self::EXPORT_LIMIT)
            ->get();

        AuditLogger::record('audit.exported', "Exported {$logs->count()} audit log entries as CSV.");

        $filename = 'audit-log-' . now($timezone)->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($logs, $timezone) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads accents and symbols correctly
            fputcsv($out, ['When (' . $timezone . ')', 'User', 'Role', 'Action', 'Item', 'Description', 'IP address']);

            foreach ($logs as $log) {
                fputcsv($out, array_map([self::class, 'csvSafe'], [
                    $log->created_at->copy()->timezone($timezone)->format('Y-m-d H:i:s'),
                    $log->user_name ?? 'System',
                    $log->user_role ?? '',
                    $log->action_label,
                    $log->subject_label ?? '',
                    $log->description,
                    $log->ip_address ?? '',
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stops a cell that starts with = + - or @ from being run as a formula when opened in Excel.
     */
    public static function csvSafe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    /**
     * @return array{search: string, category: ?string, from: ?string, to: ?string}
     */
    private function filters(Request $request): array
    {
        $timezone = config('reservations.timezone', 'Asia/Manila');
        $dbTimezone = config('app.timezone');

        $from = ReservationService::parseDate($request->query('from'));
        $to = ReservationService::parseDate($request->query('to'));

        return [
            'search' => is_string($request->query('search')) ? trim($request->query('search')) : '',
            'category' => is_string($request->query('category')) ? $request->query('category') : null,
            // Dates are shop days: from the start of "from" up to the end of "to"
            'from' => $from ? Carbon::parse($from, $timezone)->startOfDay()->setTimezone($dbTimezone)->toDateTimeString() : null,
            'to' => $to ? Carbon::parse($to, $timezone)->addDay()->startOfDay()->setTimezone($dbTimezone)->toDateTimeString() : null,
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Models\Gown;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationNotifier;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reservation pages for admin and staff: see every reservation and move it along.
 */
class ReservationController extends Controller
{
    public function __construct(private ReservationService $reservations, private ReservationNotifier $notifier)
    {
    }

    public function index(Request $request): View
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $status = $request->query('status');
        $source = $request->query('source');
        $date = ReservationService::parseDate($request->query('date'));

        $reservations = Reservation::query()
            ->with(['user', 'gown', 'creator'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';

                $query->where(function ($query) use ($search, $like) {
                    $query
                        // Customers with an account
                        ->whereIn('user_id', User::query()->select('id')
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like))
                        // Walk-in customers, whose details are stored on the reservation itself
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_phone', 'like', $like)
                        ->orWhere('customer_email', 'like', $like)
                        ->orWhereIn('gown_id', Gown::withTrashed()->select('id')
                            ->where('name', 'like', $like)
                            ->orWhere('gown_code', 'like', $like));

                    // Typing a reference such as R-00012 finds that exact reservation
                    if (preg_match('/^R-?(\d+)$/i', $search, $m)) {
                        $query->orWhere('id', (int) $m[1]);
                    }
                });
            })
            ->when(is_string($status) && in_array($status, Reservation::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when(is_string($source) && in_array($source, Reservation::SOURCES, true), fn ($query) => $query->where('source', $source))
            // Reservations that include this day, whether it is the pick-up, use, or return day
            ->when($date, fn ($query) => $query->overlapping($date, $date))
            // Things that need attention come first
            ->orderByRaw("FIELD(status, 'pending', 'confirmed', 'picked_up', 'returned', 'rejected', 'cancelled')")
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.reservations.index', [
            'reservations' => $reservations,
            'statuses' => Reservation::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Reservation::STATUSES)],
        ]);

        try {
            $updated = $this->reservations->changeStatus($reservation, $data['status']);
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->notifier->statusChanged($updated->load(['gown', 'user']));

        return back()->with('success', "Reservation {$updated->reference} is now {$updated->status_label}.");
    }
}

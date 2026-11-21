<?php

namespace App\Http\Controllers;

use App\Exceptions\ReservationException;
use App\Models\Gown;
use App\Models\Reservation;
use App\Services\ReservationNotifier;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reservation pages for signed-in customers: book, list their own, and cancel.
 */
class ReservationController extends Controller
{
    public function __construct(private ReservationService $reservations, private ReservationNotifier $notifier)
    {
    }

    public function index(Request $request): View
    {
        $reservations = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->with('gown')
            ->latest('id')
            ->paginate(10);

        return view('reservations.index', ['reservations' => $reservations]);
    }

    public function store(Request $request, Gown $gown): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'string'],
            'size' => ['required', 'string', 'max:20'],
            'customer_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'customer_note' => ['nullable', 'string', 'max:500'],
        ], [
            'start_date.required' => 'Please choose your pick-up date on the calendar.',
            'size.required' => 'Please choose a size.',
            'customer_phone.regex' => 'Please enter a valid mobile number, such as 0917 123 4567.',
            'customer_note.max' => 'Your note must not be longer than 500 characters.',
        ]);

        try {
            $reservation = $this->reservations->create(
                $request->user(),
                $gown->id,
                $data['start_date'],
                $data['size'],
                $data['customer_note'] ?? null,
                $data['customer_phone'] ?? null
            );
        } catch (ReservationException $e) {
            return back()->withInput()->withErrors(['start_date' => $e->getMessage()]);
        }

        $this->notifier->booked($reservation->load(['gown', 'user']));

        return redirect()
            ->route('reservations.index')
            ->with('success', "Reservation {$reservation->reference} was sent. The shop will review and confirm it.");
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        // Customers can only touch their own reservations
        abort_unless((int) $reservation->user_id === (int) $request->user()->id, 403);

        try {
            $cancelled = $this->reservations->cancelByCustomer($reservation);
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->notifier->statusChanged($cancelled->load(['gown', 'user']), byCustomer: true);

        return back()->with('success', "Reservation {$reservation->reference} was cancelled.");
    }
}

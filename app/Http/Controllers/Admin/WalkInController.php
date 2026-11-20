<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Models\Gown;
use App\Services\ReservationNotifier;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Walk-in bookings: staff reserve a gown at the shop for a customer who is there in person.
 * The customer does not need an account.
 *
 * Step 1: choose the gown.   Step 2: choose the dates and enter the customer's details.
 */
class WalkInController extends Controller
{
    public function __construct(private ReservationService $reservations, private ReservationNotifier $notifier)
    {
    }

    public function create(Request $request): View
    {
        $requested = $request->query('gown');
        $gownNotFound = false;

        // Step 2: a gown was chosen, so show its calendar and the customer's details
        if ($requested !== null && $requested !== '') {
            $gown = is_string($requested) && ctype_digit($requested)
                ? Gown::query()->available()->find((int) $requested)
                : null;

            if ($gown) {
                return view('admin.walk-ins.create', [
                    'gown' => $gown,
                    'blockedDays' => $this->reservations->blockedDays($gown, ReservationService::walkInEarliestStart()),
                    'minDate' => ReservationService::walkInEarliestStart(),
                    'maxDate' => ReservationService::latestStart(),
                    'selected' => ReservationService::validWalkInPickupOrNull(
                        old('start_date', $request->query('date'))
                    ) ?? '',
                ]);
            }

            $gownNotFound = true;
        }

        // Step 1: choose a gown. Optionally show only gowns that are free for a date.
        $pickup = ReservationService::validWalkInPickupOrNull($request->query('date'));
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';

        $gowns = Gown::query()
            ->available()
            ->filter(['search' => $search])
            ->when($pickup, fn ($query) => $query->freeBetween($pickup, ReservationService::endDate($pickup)))
            ->orderBy('gown_code')
            ->paginate(12)
            ->withQueryString();

        return view('admin.walk-ins.pick', [
            'gowns' => $gowns,
            'pickup' => $pickup,
            'pickupEnd' => $pickup ? ReservationService::endDate($pickup) : null,
            'minDate' => ReservationService::walkInEarliestStart(),
            'maxDate' => ReservationService::latestStart(),
            'gownNotFound' => $gownNotFound,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gown_id' => ['required', 'integer'],
            'start_date' => ['required', 'string'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:500'],
        ], [
            'start_date.required' => 'Please choose the pick-up date on the calendar.',
            'customer_name.required' => "Please enter the customer's name.",
            'customer_phone.required' => "Please enter the customer's phone number.",
            'customer_phone.regex' => 'Please enter a valid phone number, such as 0917 123 4567.',
            'customer_note.max' => 'The note must not be longer than 500 characters.',
        ]);

        try {
            $reservation = $this->reservations->createWalkIn(
                $request->user(),
                (int) $data['gown_id'],
                $data['start_date'],
                [
                    'name' => $data['customer_name'],
                    'phone' => $data['customer_phone'],
                    'email' => $data['customer_email'] ?? null,
                ],
                $data['customer_note'] ?? null
            );
        } catch (ReservationException $e) {
            return back()->withInput()->withErrors(['start_date' => $e->getMessage()]);
        }

        $this->notifier->booked($reservation->load(['gown', 'user']));

        return redirect()
            ->route('admin.reservations.index')
            ->with('success', "Walk-in reservation {$reservation->reference} was saved for {$reservation->customer_display_name}. It is confirmed.");
    }
}

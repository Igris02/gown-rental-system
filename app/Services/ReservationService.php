<?php

namespace App\Services;

use App\Exceptions\ReservationException;
use App\Models\Gown;
use App\Models\Reservation;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * All the booking rules live here, so the customer pages, the staff pages, and the walk-in
 * bookings follow exactly the same rules, including the double-booking lock.
 *
 * Dates are plain "Y-m-d" strings. That avoids time zone surprises: a rental is a set of
 * calendar days, not a moment in time.
 */
class ReservationService
{
    /** Day 1 pick up, Day 2 use, Day 3 return. */
    public const RENTAL_DAYS = 3;

    // ---------------------------------------------------------------------------------
    // Date helpers
    // ---------------------------------------------------------------------------------

    /**
     * Today's date in the shop's time zone (Asia/Manila unless changed in config).
     */
    public static function today(): string
    {
        $timezone = new DateTimeZone(config('reservations.timezone', 'Asia/Manila'));

        return (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
    }

    public static function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC')))
            ->modify(sprintf('%+d days', $days))
            ->format('Y-m-d');
    }

    /**
     * Day 3 (the return day) for a rental that starts on $start.
     */
    public static function endDate(string $start): string
    {
        return self::addDays($start, self::RENTAL_DAYS - 1);
    }

    /**
     * Returns the date when it is a real calendar date written as Y-m-d, otherwise null.
     */
    public static function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }

    public static function pretty(string $date): string
    {
        return (new DateTimeImmutable($date))->format('D, M j, Y');
    }

    /**
     * Earliest pick-up for customers booking online: tomorrow.
     */
    public static function earliestStart(): string
    {
        return self::addDays(self::today(), (int) config('reservations.min_lead_days', 1));
    }

    /**
     * Earliest pick-up for a walk-in booking made by staff: today, because the customer
     * is standing at the counter and may take the gown right away.
     */
    public static function walkInEarliestStart(): string
    {
        return self::today();
    }

    public static function latestStart(): string
    {
        return self::addDays(self::today(), (int) config('reservations.max_advance_days', 180));
    }

    /**
     * A usable online pick-up date, or null when the value is missing, malformed, or out of range.
     */
    public static function validPickupOrNull(mixed $value): ?string
    {
        $date = self::parseDate($value);

        if ($date === null || $date < self::earliestStart() || $date > self::latestStart()) {
            return null;
        }

        return $date;
    }

    /**
     * Same as validPickupOrNull(), but with the walk-in rules (today is allowed).
     */
    public static function validWalkInPickupOrNull(mixed $value): ?string
    {
        $date = self::parseDate($value);

        if ($date === null || $date < self::walkInEarliestStart() || $date > self::latestStart()) {
            return null;
        }

        return $date;
    }

    /**
     * Every date from $start to $end, both included.
     *
     * @return string[]
     */
    public static function datesBetween(string $start, string $end): array
    {
        $dates = [];

        for ($date = $start; $date <= $end; $date = self::addDays($date, 1)) {
            $dates[] = $date;
        }

        return $dates;
    }

    // ---------------------------------------------------------------------------------
    // Availability
    // ---------------------------------------------------------------------------------

    /**
     * Is the gown free on every day from $start to $end?
     * With $lock = true the matching rows are locked until the transaction ends.
     * With $size, only reservations for that size count (used for gowns that have a separate gown per size).
     */
    public function isFree(int $gownId, string $start, string $end, bool $lock = false, ?string $size = null): bool
    {
        $query = Reservation::query()
            ->active()
            ->where('gown_id', $gownId)
            ->when($size !== null, fn ($q) => $q->where('size', $size))
            ->overlapping($start, $end);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first(['id']) === null;
    }

    /**
     * Blocked days for each size of the gown, for the calendar on the gown page:
     * ['M' => ['2026-12-01', ...], 'L' => [...]].
     * A one-piece gown blocks every size. A separate-per-size gown blocks only the size that was booked.
     * The calendar starts on $from (tomorrow for customers, today for walk-ins).
     *
     * @return array<string, string[]>
     */
    public function blockedBySize(Gown $gown, ?string $from = null): array
    {
        $from ??= self::earliestStart();
        $to = self::endDate(self::latestStart());

        $sizes = $gown->sizeList();
        $map = array_fill_keys($sizes, []);

        $reservations = Reservation::query()
            ->active()
            ->where('gown_id', $gown->id)
            ->overlapping($from, $to)
            ->get(['start_date', 'end_date', 'size']);

        foreach ($reservations as $reservation) {
            $days = self::datesBetween($reservation->start_date->toDateString(), $reservation->end_date->toDateString());
            $targets = $gown->isSeparateSizes() ? [$reservation->size] : $sizes;

            foreach ($targets as $size) {
                if (! isset($map[$size])) {
                    continue;
                }

                foreach ($days as $day) {
                    $map[$size][$day] = true;
                }
            }
        }

        return array_map(function ($days) {
            $days = array_keys($days);
            sort($days);

            return $days;
        }, $map);
    }

    /**
     * The size to pre-select: the requested one if the gown has it, otherwise the first size.
     *
     * @param string[] $sizes
     */
    public static function pickSize(array $sizes, mixed $wanted): string
    {
        return is_string($wanted) && in_array($wanted, $sizes, true) ? $wanted : ($sizes[0] ?? '');
    }
    // ---------------------------------------------------------------------------------
    // Booking and status changes
    // ---------------------------------------------------------------------------------

    /**
     * A customer books a gown online for 3 days starting on $start, in the chosen $size. The
     * reservation starts as pending and waits for staff. Throws ReservationException with a
     * message that can be shown on screen when a rule is broken.
     */
    public function create(User $user, int $gownId, mixed $start, ?string $size, ?string $note = null, ?string $phone = null): Reservation
    {
        $startDate = $this->checkedStartDate($start, self::earliestStart());

        return $this->book($gownId, $startDate, $size, [
            'user_id' => $user->id,
            'status' => Reservation::STATUS_PENDING,
            'source' => Reservation::SOURCE_ONLINE,
            // Optional mobile number for text messages. The customer's name and email come from their account.
            'customer_phone' => $this->cleanText($phone),
            'customer_note' => $this->cleanText($note),
        ]);
    }

    /**
     * Staff book a gown at the shop for a customer who may not have an account. The booking
     * is confirmed straight away, because the customer is there and the staff member agreed it.
     *
     * @param array{name?: ?string, phone?: ?string, email?: ?string} $customer
     */
    public function createWalkIn(User $staff, int $gownId, mixed $start, ?string $size, array $customer, ?string $note = null): Reservation
    {
        $startDate = $this->checkedStartDate($start, self::walkInEarliestStart());

        $name = $this->cleanText($customer['name'] ?? null);
        $phone = $this->cleanText($customer['phone'] ?? null);

        if ($name === null || $phone === null) {
            throw new ReservationException("Please enter the customer's name and phone number.");
        }

        return $this->book($gownId, $startDate, $size, [
            'user_id' => null,
            'status' => Reservation::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'source' => Reservation::SOURCE_WALK_IN,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'customer_email' => $this->cleanText($customer['email'] ?? null),
            'customer_note' => $this->cleanText($note),
            'created_by' => $staff->id,
        ]);
    }
    /**
     * Moves a reservation to a new status, only when the move is allowed.
     */
    public function changeStatus(Reservation $reservation, string $to): Reservation
    {
        return DB::transaction(function () use ($reservation, $to) {
            // Re-read the row under a lock, so two staff members cannot act on it at once
            $current = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($to, $current->nextStatuses(), true)) {
                throw new ReservationException(sprintf(
                    'Reservation %s is %s and cannot be changed to %s. Refresh the page to see its latest status.',
                    $current->reference,
                    strtolower($current->status_label),
                    strtolower(ucfirst(str_replace('_', ' ', $to)))
                ));
            }

            $current->status = $to;

            // The first time the shop confirms. The reports measure how long customers waited for this.
            if ($to === Reservation::STATUS_CONFIRMED && $current->confirmed_at === null) {
                $current->confirmed_at = now();
            }

            if ($to === Reservation::STATUS_PICKED_UP) {
                $current->picked_up_at = now();
            }

            if ($to === Reservation::STATUS_RETURNED) {
                $current->returned_at = now();
            }

            $current->save();

            return $current;
        });
    }

    /**
     * A customer cancels their own reservation.
     */
    public function cancelByCustomer(Reservation $reservation): Reservation
    {
        if (! $reservation->canBeCancelledByCustomer()) {
            throw new ReservationException('This reservation can no longer be cancelled online. Please contact the shop.');
        }

        return $this->changeStatus($reservation, Reservation::STATUS_CANCELLED);
    }

    // ---------------------------------------------------------------------------------
    // Shared by online and walk-in bookings
    // ---------------------------------------------------------------------------------

    /**
     * Checks the pick-up date and returns it, or throws a message for the person booking.
     */
    private function checkedStartDate(mixed $start, string $earliest): string
    {
        $startDate = self::parseDate($start);

        if ($startDate === null) {
            throw new ReservationException('Please choose the pick-up date on the calendar.');
        }

        if ($startDate < $earliest) {
            throw new ReservationException('The earliest pick-up date is ' . self::pretty($earliest) . '.');
        }

        if ($startDate > self::latestStart()) {
            throw new ReservationException('Pick-up dates can only be booked up to ' . self::pretty(self::latestStart()) . '.');
        }

        return $startDate;
    }

    /**
     * Trims text, and turns empty text into null.
     */
    private function cleanText(?string $text): ?string
    {
        $text = $text === null ? null : trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * The one place a reservation is saved. Online and walk-in bookings both pass through
     * here, so both get the same lock and the same availability check.
     *
     * @param array<string, mixed> $attributes who is booking and how
     */
    private function book(int $gownId, string $startDate, ?string $size, array $attributes): Reservation
    {
        $endDate = self::endDate($startDate);

        return DB::transaction(function () use ($gownId, $startDate, $endDate, $size, $attributes) {
            // Lock this gown's row first. Anyone else booking the same gown, online or at the
            // shop, has to wait here until this transaction finishes, so two bookings can never
            // both pass the check.
            $gown = Gown::query()->whereKey($gownId)->lockForUpdate()->first();

            if ($gown === null || $gown->status !== Gown::STATUS_AVAILABLE) {
                throw new ReservationException('This gown is not available for reservation.');
            }

            $sizes = $gown->sizeList();
            $size = $this->cleanText($size) ?? (count($sizes) === 1 ? $sizes[0] : null);

            if ($size === null || ! in_array($size, $sizes, true)) {
                throw new ReservationException('Please choose one of the available sizes for this gown.');
            }

            // A gown with a separate piece per size is checked per size.
            // A one-piece gown is checked as a whole, whichever size was picked.
            $checkSize = $gown->isSeparateSizes() ? $size : null;

            if (! $this->isFree($gown->id, $startDate, $endDate, lock: true, size: $checkSize)) {
                throw new ReservationException(
                    'Sorry, this gown is already reserved on at least one of those days'
                    . ($checkSize ? " in size {$size}" : '')
                    . '. Please choose another pick-up date' . ($checkSize ? ' or size.' : '.')
                );
            }

            return Reservation::create($attributes + [
                'gown_id' => $gown->id,
                'size' => $size,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rental_price' => $gown->rental_price,
            ]);
        });
    }
}

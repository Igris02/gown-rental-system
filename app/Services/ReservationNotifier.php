<?php

namespace App\Services;

use App\Mail\ReservationMail;
use App\Models\Reservation;
use App\Services\Sms\SmsSender;
use App\Support\ReservationMessages;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the emails and text messages about a reservation.
 *
 * It never throws: a mail or SMS problem must not undo or block a booking. Problems are
 * written to storage/logs/laravel.log. The customer gets a text only if a mobile number
 * was given, and an email only if there is an email address (an account, or one taken at the shop).
 */
class ReservationNotifier
{
    public function __construct(private SmsSender $sms)
    {
    }

    /**
     * A new booking: an online request that waits for the shop, or a walk-in that is already confirmed.
     */
    public function booked(Reservation $reservation): void
    {
        $event = $reservation->isWalkIn() ? 'walk_in' : 'received';

        $this->toCustomer($reservation, $event);

        if ($event === 'received') {
            $this->toShop($reservation, 'New online reservation', [
                "{$reservation->customer_display_name} reserved {$reservation->gown->name} ({$reservation->gown->gown_code}).",
                'Pick up: ' . $reservation->start_date->format('D, M j, Y') . '. Return: ' . $reservation->end_date->format('D, M j, Y') . '.',
                "Reference {$reservation->reference}. Please review it in the Reservations page.",
            ]);
        }
    }

    /**
     * Tells the customer when the shop confirms, rejects, or cancels a reservation.
     * Other changes (picked up, returned) send nothing.
     */
    public function statusChanged(Reservation $reservation, bool $byCustomer = false): void
    {
        $event = match ($reservation->status) {
            Reservation::STATUS_CONFIRMED => 'confirmed',
            Reservation::STATUS_REJECTED => 'rejected',
            Reservation::STATUS_CANCELLED => 'cancelled',
            default => null,
        };

        if ($event === null) {
            return;
        }

        $this->toCustomer($reservation, $event);

        if ($event === 'cancelled' && $byCustomer) {
            $this->toShop($reservation, 'A customer cancelled a reservation', [
                "{$reservation->customer_display_name} cancelled {$reservation->reference} for {$reservation->gown->name} ({$reservation->gown->gown_code}).",
                'Pick up was ' . $reservation->start_date->format('D, M j, Y') . '. The days are free again.',
            ]);
        }
    }

    /**
     * Sends a reminder and says what happened.
     *
     * @param 'pickup_reminder'|'return_reminder' $event
     * @return 'sent'|'none'|'failed' sent = at least one message went out,
     *         none = the customer cannot be reached, failed = every attempt failed
     */
    public function reminder(Reservation $reservation, string $event): string
    {
        $result = $this->toCustomer($reservation, $event);
        $attempted = array_filter($result, fn ($r) => $r !== null);

        if ($attempted === []) {
            return 'none';
        }

        return in_array(true, $attempted, true) ? 'sent' : 'failed';
    }

    // ---------------------------------------------------------------------------------

    /**
     * @return array{email: ?bool, sms: ?bool} true = sent, false = failed, null = not attempted
     */
    private function toCustomer(Reservation $reservation, string $event): array
    {
        $message = ReservationMessages::build($event, $this->details($reservation));

        return [
            'email' => $this->email(
                $this->customerEmail($reservation),
                $message['subject'],
                $message['heading'],
                $message['lines']
            ),
            'sms' => $this->sms->send($reservation->customer_phone, $message['sms']),
        ];
    }

    /**
     * @param string[] $lines
     */
    private function toShop(Reservation $reservation, string $heading, array $lines): void
    {
        $to = config('notifications.shop.email');

        if ($to) {
            $this->email($to, $heading, $heading, $lines);
        }
    }

    /**
     * @param string[] $lines
     */
    private function email(?string $to, string $subject, string $heading, array $lines): ?bool
    {
        if (! $to || ! config('notifications.email_enabled', true)) {
            return null;
        }

        try {
            Mail::to($to)->send(new ReservationMail($subject, $heading, $lines, $this->shop()));

            return true;
        } catch (Throwable $e) {
            Log::warning("Email to {$to} failed: " . $e->getMessage());

            return false;
        }
    }

    private function customerEmail(Reservation $reservation): ?string
    {
        $email = $reservation->user?->email ?: $reservation->customer_email;

        return $email ? (string) $email : null;
    }

    /**
     * @return array{name:string, phone:?string, address:?string}
     */
    private function shop(): array
    {
        return [
            'name' => (string) config('app.name'),
            'phone' => config('notifications.shop.phone') ?: null,
            'address' => config('notifications.shop.address') ?: null,
        ];
    }

    private function details(Reservation $reservation): array
    {
        $gown = $reservation->gown;
        $price = (float) $reservation->rental_price;

        return [
            'shop' => (string) config('app.name'),
            'name' => $reservation->customer_display_name,
            'reference' => $reservation->reference,
            'gown' => $gown->name,
            'code' => $gown->gown_code,
            'pickup' => $reservation->start_date->format('D, M j, Y'),
            'use' => $reservation->use_date->format('D, M j, Y'),
            'return' => $reservation->end_date->format('D, M j, Y'),
            'pickup_short' => $reservation->start_date->format('M j'),
            'return_short' => $reservation->end_date->format('M j'),
            'price' => $reservation->formatted_price,
            'price_sms' => 'PHP ' . number_format($price, 2),
            'shop_phone' => config('notifications.shop.phone') ?: null,
        ];
    }
}

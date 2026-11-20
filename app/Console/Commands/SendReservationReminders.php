<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\ReservationNotifier;
use App\Services\ReservationService;
use Illuminate\Console\Command;

class SendReservationReminders extends Command
{
    protected $signature = 'reservations:send-reminders';

    protected $description = 'Remind customers whose pick-up or return day is tomorrow';

    public function handle(ReservationNotifier $notifier): int
    {
        $tomorrow = ReservationService::addDays(ReservationService::today(), 1);

        $this->line("Reminders for {$tomorrow}");

        $this->process(
            $notifier,
            Reservation::query()
                ->where('status', Reservation::STATUS_CONFIRMED)
                ->where('start_date', $tomorrow)
                ->whereNull('pickup_reminded_at'),
            'pickup_reminder',
            'pickup_reminded_at',
            'Pick-up'
        );

        $this->process(
            $notifier,
            Reservation::query()
                ->where('status', Reservation::STATUS_PICKED_UP)
                ->where('end_date', $tomorrow)
                ->whereNull('return_reminded_at'),
            'return_reminder',
            'return_reminded_at',
            'Return'
        );

        return self::SUCCESS;
    }

    private function process(ReservationNotifier $notifier, $query, string $event, string $column, string $label): void
    {
        $counts = ['sent' => 0, 'none' => 0, 'failed' => 0];

        $query->with(['gown', 'user'])->orderBy('id')->each(function (Reservation $reservation) use ($notifier, $event, $column, &$counts) {
            $result = $notifier->reminder($reservation, $event);
            $counts[$result]++;

            // Mark it done unless every attempt failed, so running the command again retries those
            if ($result !== 'failed') {
                $reservation->forceFill([$column => now()])->save();
            }
        });

        $this->line(sprintf(
            '%s reminders: %d sent, %d customers with no email or mobile number, %d failed',
            $label,
            $counts['sent'],
            $counts['none'],
            $counts['failed']
        ));
    }
}

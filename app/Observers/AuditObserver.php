<?php

namespace App\Observers;

use App\Models\Gown;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Watches gowns, reservations, and accounts and writes what changed to the audit log.
 * It is attached in AuditServiceProvider, so the rest of the app does not need to know about it.
 */
class AuditObserver
{
    /** Changes to these alone are bookkeeping, not worth an entry */
    private const IGNORED = [
        'updated_at', 'created_at', 'deleted_at',
        'confirmed_at', 'picked_up_at', 'returned_at', 'pickup_reminded_at', 'return_reminded_at',
    ];

    /** Never write the values of these to the log */
    private const HIDDEN_VALUES = ['image_url', 'image_public_id', 'password', 'remember_token'];

    public function created(Model $model): void
    {
        if ($model instanceof Reservation) {
            $this->reservationCreated($model);
        } elseif ($model instanceof Gown) {
            AuditLogger::record(
                'gown.created',
                "Added gown {$this->gownLabel($model)} at {$this->money($model->rental_price)}.",
                $model,
                $this->gownLabel($model)
            );
        } elseif ($model instanceof User) {
            // A person who signs themselves up has no one signed in yet, so they are the actor
            AuditLogger::record(
                'user.registered',
                "New {$model->role} account: {$model->name} ({$model->email}).",
                $model,
                (string) $model->name,
                [],
                app()->runningInConsole() ? null : (auth()->check() ? false : $model)
            );
        }
    }

    public function updated(Model $model): void
    {
        $changed = array_diff_key($model->getChanges(), array_flip(self::IGNORED));

        if ($changed === []) {
            return;
        }

        if ($model instanceof Reservation) {
            $this->reservationUpdated($model, $changed);
        } elseif ($model instanceof Gown) {
            $fields = $this->describeChanges($model, $changed);

            AuditLogger::record(
                'gown.updated',
                "Edited gown {$this->gownLabel($model)}: " . implode(', ', array_keys($fields)) . '.',
                $model,
                $this->gownLabel($model),
                $fields
            );
        }
        // Accounts: there is no screen that edits them yet, so nothing to record
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Gown) {
            AuditLogger::record('gown.deleted', "Deleted gown {$this->gownLabel($model)}.", $model, $this->gownLabel($model));
        }
    }

    public function restored(Model $model): void
    {
        if ($model instanceof Gown) {
            AuditLogger::record('gown.restored', "Restored gown {$this->gownLabel($model)}.", $model, $this->gownLabel($model));
        }
    }

    // ---------------------------------------------------------------------------------

    private function reservationCreated(Reservation $reservation): void
    {
        $gown = Gown::withTrashed()->find($reservation->gown_id);
        $what = $gown ? $this->gownLabel($gown) : "gown #{$reservation->gown_id}";
        $dates = $reservation->start_date->format('M j') . ' to ' . $reservation->end_date->format('M j, Y');
        $kind = $reservation->isWalkIn() ? 'Walk-in reservation' : 'Online reservation';

        AuditLogger::record(
            'reservation.created',
            "{$kind} {$reservation->reference} created for {$reservation->customer_display_name}: {$what}, {$dates}, {$reservation->status}.",
            $reservation,
            $reservation->reference,
            // Only the booking itself. Phone numbers and emails are not copied into the log.
            [
                'source' => $reservation->source,
                'status' => $reservation->status,
                'start_date' => $reservation->start_date->toDateString(),
                'end_date' => $reservation->end_date->toDateString(),
                'rental_price' => (string) $reservation->rental_price,
            ]
        );
    }

    /**
     * @param array<string, mixed> $changed
     */
    private function reservationUpdated(Reservation $reservation, array $changed): void
    {
        if (array_key_exists('status', $changed)) {
            $from = (string) $reservation->getOriginal('status');
            $to = (string) $reservation->status;

            AuditLogger::record(
                'reservation.status_changed',
                "Reservation {$reservation->reference} changed from " . str_replace('_', ' ', $from) . ' to ' . str_replace('_', ' ', $to) . '.',
                $reservation,
                $reservation->reference,
                ['status' => [$from, $to]]
            );

            unset($changed['status']);
        }

        if ($changed !== []) {
            $fields = $this->describeChanges($reservation, $changed);

            AuditLogger::record(
                'reservation.updated',
                "Edited reservation {$reservation->reference}: " . implode(', ', array_keys($fields)) . '.',
                $reservation,
                $reservation->reference,
                $fields
            );
        }
    }

    /**
     * Turns changed attributes into ['color' => ['Ivory', 'Black']], hiding private values.
     *
     * @param array<string, mixed> $changed
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function describeChanges(Model $model, array $changed): array
    {
        $fields = [];

        foreach ($changed as $key => $new) {
            $label = $key === 'image_url' || $key === 'image_public_id' ? 'photo' : str_replace('_', ' ', $key);

            if (isset($fields[$label])) {
                continue; // both photo fields changed together: one line is enough
            }

            if (in_array($key, self::HIDDEN_VALUES, true)) {
                $fields[$label] = ['(changed)', '(changed)'];
                continue;
            }

            $fields[$label] = [$this->short($model->getOriginal($key)), $this->short($new)];
        }

        return $fields;
    }

    private function short(mixed $value): mixed
    {
        if (is_string($value) && mb_strlen($value) > 200) {
            return mb_substr($value, 0, 200) . '…';
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
    }

    private function gownLabel(Gown $gown): string
    {
        return trim("{$gown->gown_code} {$gown->name}");
    }

    private function money(mixed $amount): string
    {
        return 'PHP ' . number_format((float) $amount, 2);
    }
}

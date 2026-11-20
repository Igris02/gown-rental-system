<?php

namespace App\Models;

use App\Services\ReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PICKED_UP,
        self::STATUS_RETURNED,
        self::STATUS_CANCELLED,
        self::STATUS_REJECTED,
    ];

    /**
     * Statuses that hold the gown for the reserved days. A cancelled, rejected, or
     * returned reservation frees it again.
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PICKED_UP,
    ];

    /**
     * Where the booking came from: the website, or staff at the shop.
     */
    public const SOURCE_ONLINE = 'online';
    public const SOURCE_WALK_IN = 'walk_in';

    public const SOURCES = [
        self::SOURCE_ONLINE,
        self::SOURCE_WALK_IN,
    ];

    /**
     * Which status can follow which. Anything not listed here is refused.
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PICKED_UP, self::STATUS_CANCELLED],
        self::STATUS_PICKED_UP => [self::STATUS_RETURNED],
        self::STATUS_RETURNED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_REJECTED => [],
    ];

    /**
     * Button text for the staff screen, keyed by the status the button moves to.
     */
    public const ACTION_LABELS = [
        self::STATUS_CONFIRMED => 'Confirm',
        self::STATUS_REJECTED => 'Reject',
        self::STATUS_CANCELLED => 'Cancel',
        self::STATUS_PICKED_UP => 'Mark picked up',
        self::STATUS_RETURNED => 'Mark returned',
    ];

    protected $fillable = [
        'user_id',
        'gown_id',
        'start_date',
        'end_date',
        'status',
        'source',
        'customer_name',
        'customer_phone',
        'customer_email',
        'rental_price',
        'customer_note',
        'created_by',
        'confirmed_at',
        'picked_up_at',
        'returned_at',
        'pickup_reminded_at',
        'return_reminded_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'rental_price' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'returned_at' => 'datetime',
        'pickup_reminded_at' => 'datetime',
        'return_reminded_at' => 'datetime',
    ];

    /**
     * The customer's account. Empty for a walk-in customer who has no account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The staff member who made a walk-in booking.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Includes deleted gowns, so old reservations still show which gown they were for.
     */
    public function gown(): BelongsTo
    {
        return $this->belongsTo(Gown::class)->withTrashed();
    }

    /**
     * Reservations that currently hold their gown.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Reservations that share at least one day with $start to $end (both included).
     * Dates are "Y-m-d" strings. Two rentals clash when each one starts on or before
     * the day the other ends. A rental that starts the day after another one ends does not.
     */
    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->where('start_date', '<=', $end)->where('end_date', '>=', $start);
    }

    public function isWalkIn(): bool
    {
        return $this->source === self::SOURCE_WALK_IN;
    }

    /**
     * The customer's name, whether they have an account or were booked at the shop.
     */
    public function getCustomerDisplayNameAttribute(): string
    {
        return $this->user?->name ?? $this->customer_name ?? 'Unknown customer';
    }

    /**
     * How to reach the customer: the email for an account, or the phone and email taken at the shop.
     */
    public function getCustomerDisplayContactAttribute(): string
    {
        if ($this->user) {
            return collect([$this->user->email, $this->customer_phone])->filter()->implode(' · ');
        }

        return collect([$this->customer_phone, $this->customer_email])->filter()->implode(' · ');
    }

    /**
     * A short code for the customer and staff to quote, such as R-00012.
     */
    public function getReferenceAttribute(): string
    {
        return sprintf('R-%05d', $this->id);
    }

    /**
     * Day 2, the day the gown is used.
     */
    public function getUseDateAttribute()
    {
        return $this->start_date->copy()->addDay();
    }

    public function getFormattedPriceAttribute(): string
    {
        return '₱' . number_format((float) $this->rental_price, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * The statuses staff can move this reservation to right now.
     *
     * @return string[]
     */
    public function nextStatuses(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    /**
     * Customers can cancel their own reservation until the day of pick-up.
     */
    public function canBeCancelledByCustomer(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true)
            && $this->start_date->toDateString() > ReservationService::today();
    }
}

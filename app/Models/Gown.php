<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Gown extends Model
{
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_RETIRED = 'retired';

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_MAINTENANCE,
        self::STATUS_RETIRED,
    ];

    public const SIZES = ['XS', 'S', 'M', 'L', 'XL'];

    public const SIZE_TYPE_SINGLE = 'single';      // one piece, fits all listed sizes
    public const SIZE_TYPE_SEPARATE = 'separate';  // one gown per size

    public const SIZE_TYPES = [self::SIZE_TYPE_SINGLE, self::SIZE_TYPE_SEPARATE];

    /** Each gown has a main photo plus up to 2 extra photos, in position 1 and 2. */
    public const EXTRA_PHOTO_SLOTS = [1, 2];

    protected $fillable = [
        'gown_code',
        'name',
        'design',
        'color',
        'size_type',
        'silhouette',
        'rental_price',
        'description',
        'image_url',
        'image_public_id',
        'status',
    ];

    protected $casts = [
        'rental_price' => 'decimal:2',
    ];

    /**
     * Every silhouette used by a gown right now, sorted. Admins type silhouettes freely, so
     * this list grows by itself. It feeds the filter lists and the suggestions on the add and
     * edit form. With $availableOnly, gowns hidden from customers are left out.
     */
    public static function silhouettes(bool $availableOnly = false): Collection
    {
        return static::query()
            ->when($availableOnly, fn ($query) => $query->available())
            ->distinct()
            ->orderBy('silhouette')
            ->pluck('silhouette');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GownPhoto::class)->orderBy('position');
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(GownSize::class);
    }

    /**
     * Sizes in the order of Gown::SIZES, for example ['M', 'L', 'XL'].
     *
     * @return string[]
     */
    public function sizeList(): array
    {
        $order = array_flip(self::SIZES);

        return $this->sizes->pluck('size')
            ->sortBy(fn ($size) => $order[$size] ?? 99)
            ->values()
            ->all();
    }

    /** "M / L / XL" */
    public function getSizeLabelAttribute(): string
    {
        return implode(' / ', $this->sizeList()) ?: '—';
    }

    public function isSeparateSizes(): bool
    {
        return $this->size_type === self::SIZE_TYPE_SEPARATE;
    }

    public function getSizeTypeLabelAttribute(): string
    {
        return $this->isSeparateSizes() ? 'Separate gown per size' : 'One piece, all sizes';
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Price ready to display, such as "₱2,500.00". Use $gown->formatted_price in views.
     */
    public function getFormattedPriceAttribute(): string
    {
        return '₱' . number_format((float) $this->rental_price, 2);
    }

    /**
     * Small, cropped version of the photo for lists. Cloudinary makes it on the fly.
     * Returns null when the gown has no photo.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->transformedImage('c_fill,w_120,h_120,q_auto,f_auto');
    }

    /**
     * Fixed 600 x 800 px photo (3:4) for catalog cards and the gown page.
     * g_auto keeps the important part of the photo in frame when it has to be cropped.
     */
    public function getCardUrlAttribute(): ?string
    {
        return $this->transformedImage('c_fill,g_auto,w_600,h_800,q_auto,f_auto');
    }

    /**
     * Fixed 1200 x 1600 px photo (3:4) shown when a customer clicks to zoom.
     * It uses the same crop as the 600 x 800 photo, so both show the same framing.
     */
    public function getZoomUrlAttribute(): ?string
    {
        return $this->transformedImage('c_fill,g_auto,w_1200,h_1600,q_auto,f_auto');
    }

    /**
     * Asks Cloudinary for a resized copy of the photo, so pages load fast.
     */
    private function transformedImage(string $transformation): ?string
    {
        if (! $this->image_url) {
            return null;
        }

        return str_replace('/upload/', "/upload/{$transformation}/", $this->image_url);
    }

    /**
     * Gowns that customers are allowed to see and reserve.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    /**
     * Gowns that can be rented on every day from $start to $end (Y-m-d strings).
     * One-piece gowns need no active reservation at all. Separate gowns need at least one size that is free.
     */
    public function scopeFreeBetween(Builder $query, string $start, string $end): Builder
    {
        $active = Reservation::ACTIVE_STATUSES;

        return $query->where(function (Builder $q) use ($start, $end, $active) {
            $q->where(function (Builder $q) use ($start, $end) {
                $q->where('size_type', self::SIZE_TYPE_SINGLE)
                    ->whereDoesntHave('reservations', fn ($r) => $r->active()->overlapping($start, $end));
            })->orWhere(function (Builder $q) use ($start, $end, $active) {
                $q->where('size_type', self::SIZE_TYPE_SEPARATE)
                    ->whereExists(function ($sizes) use ($start, $end, $active) {
                        $sizes->select(DB::raw(1))
                            ->from('gown_sizes')
                            ->whereColumn('gown_sizes.gown_id', 'gowns.id')
                            ->whereNotExists(function ($taken) use ($start, $end, $active) {
                                $taken->select(DB::raw(1))
                                    ->from('reservations')
                                    ->whereColumn('reservations.gown_id', 'gown_sizes.gown_id')
                                    ->whereColumn('reservations.size', 'gown_sizes.size')
                                    ->whereIn('reservations.status', $active)
                                    ->where('reservations.start_date', '<=', $end)
                                    ->where('reservations.end_date', '>=', $start);
                            });
                    });
            });
        });
    }

    /**
     * Optional catalog filters: color, size, silhouette, and a search word.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['color'] ?? null, fn ($q, $v) => $q->where('color', $v))
            ->when($filters['size'] ?? null, fn ($q, $v) => $q->whereHas('sizes', fn ($s) => $s->where('size', $v)))
            ->when($filters['silhouette'] ?? null, fn ($q, $v) => $q->where('silhouette', $v))
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(function ($q) use ($v) {
                    $q->where('name', 'like', "%{$v}%")
                        ->orWhere('design', 'like', "%{$v}%")
                        ->orWhere('color', 'like', "%{$v}%")
                        ->orWhere('gown_code', 'like', "%{$v}%");
                });
            });
    }
}

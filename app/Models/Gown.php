<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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

    protected $fillable = [
        'gown_code',
        'name',
        'design',
        'color',
        'size',
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
     * Gowns with no active reservation on any day from $start to $end (Y-m-d strings).
     */
    public function scopeFreeBetween(Builder $query, string $start, string $end): Builder
    {
        return $query->whereDoesntHave(
            'reservations',
            fn ($reservations) => $reservations->active()->overlapping($start, $end)
        );
    }

    /**
     * Optional catalog filters: color, size, silhouette, and a search word.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['color'] ?? null, fn ($q, $v) => $q->where('color', $v))
            ->when($filters['size'] ?? null, fn ($q, $v) => $q->where('size', $v))
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra photo of a gown (the main photo lives on the gowns table).
 * Cloudinary makes the smaller and larger copies on the fly, the same way as for the main photo.
 */
class GownPhoto extends Model
{
    public $timestamps = false;

    protected $fillable = ['gown_id', 'position', 'image_url', 'image_public_id'];

    public function gown(): BelongsTo
    {
        return $this->belongsTo(Gown::class);
    }

    /** Small 240 x 320 px copy for the gown page (shown at 120 x 160 so it stays sharp on phones). */
    public function getThumbUrlAttribute(): string
    {
        return $this->transformed('c_fill,g_auto,w_240,h_320,q_auto,f_auto');
    }

    /** Tiny square copy for the admin form. */
    public function getThumbnailUrlAttribute(): string
    {
        return $this->transformed('c_fill,w_120,h_120,q_auto,f_auto');
    }

    /** Same 1200 x 1600 px copy as the main photo uses in the zoom view. */
    public function getZoomUrlAttribute(): string
    {
        return $this->transformed('c_fill,g_auto,w_1200,h_1600,q_auto,f_auto');
    }

    private function transformed(string $transformation): string
    {
        return str_replace('/upload/', "/upload/{$transformation}/", $this->image_url);
    }
}

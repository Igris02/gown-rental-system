<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GownSize extends Model
{
    public $timestamps = false;

    protected $fillable = ['gown_id', 'size'];

    public function gown(): BelongsTo
    {
        return $this->belongsTo(Gown::class);
    }
}

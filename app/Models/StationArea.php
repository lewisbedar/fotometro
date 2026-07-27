<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'station_id',
    'external_id',
    'public_external_id',
    'name',
    'latitude',
    'longitude',
    'city',
    'postal_code',
    'area_type',
    'source',
    'source_payload',
    'source_updated_at',
    'is_active',
])]
class StationArea extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'source_payload' => 'array',
            'source_updated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(StationStop::class);
    }
}

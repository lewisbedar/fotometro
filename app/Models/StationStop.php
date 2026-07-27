<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'station_id',
    'station_area_id',
    'external_id',
    'zone_external_id',
    'name',
    'latitude',
    'longitude',
    'source',
    'source_payload',
    'source_updated_at',
    'is_active',
])]
class StationStop extends Model
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

    public function area(): BelongsTo
    {
        return $this->belongsTo(StationArea::class, 'station_area_id');
    }
}

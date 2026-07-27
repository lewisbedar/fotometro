<?php

namespace App\Models;

use Database\Factories\LineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'external_id',
    'code',
    'name',
    'slug',
    'color',
    'text_color',
    'sort_order',
    'path_geojson',
    'is_active',
    'source',
    'source_payload',
    'source_updated_at',
])]
class Line extends Model
{
    /** @use HasFactory<LineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'path_geojson' => 'array',
            'is_active' => 'boolean',
            'source_payload' => 'array',
            'source_updated_at' => 'datetime',
        ];
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(Station::class, 'station_line')
            ->withPivot(['position', 'branch', 'is_terminus'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function stationSequences(): HasMany
    {
        return $this->hasMany(LineStationSequence::class)
            ->orderBy('sequence_key')
            ->orderBy('position');
    }
}

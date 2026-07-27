<?php

namespace App\Models;

use App\Enums\CoverageStatus;
use Database\Factories\StationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'external_id',
    'name',
    'slug',
    'latitude',
    'longitude',
    'city',
    'postal_code',
    'district',
    'opening_date',
    'description',
    'coverage_status',
    'is_active',
    'source',
    'source_payload',
    'source_updated_at',
])]
class Station extends Model
{
    /** @use HasFactory<StationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'opening_date' => 'date',
            'coverage_status' => CoverageStatus::class,
            'is_active' => 'boolean',
            'source_payload' => 'array',
            'source_updated_at' => 'datetime',
        ];
    }

    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'station_line')
            ->withPivot(['position', 'branch', 'is_terminus'])
            ->withTimestamps()
            ->orderBy('sort_order');
    }

    public function accesses(): BelongsToMany
    {
        return $this->belongsToMany(StationAccess::class, 'access_station')
            ->withPivot(['source'])
            ->withTimestamps();
    }

    public function areas(): HasMany
    {
        return $this->hasMany(StationArea::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(StationStop::class);
    }
}

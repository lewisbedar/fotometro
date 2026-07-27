<?php

namespace App\Models;

use App\Enums\CoverageStatus;
use Database\Factories\StationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        ];
    }

    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'station_line')
            ->withPivot(['position', 'branch', 'is_terminus'])
            ->withTimestamps()
            ->orderBy('sort_order');
    }
}

<?php

namespace App\Models;

use Database\Factories\LineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name', 'slug', 'color', 'text_color', 'sort_order', 'path_geojson'])]
class Line extends Model
{
    /** @use HasFactory<LineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'path_geojson' => 'array',
        ];
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(Station::class, 'station_line')
            ->withPivot(['position', 'branch', 'is_terminus'])
            ->withTimestamps()
            ->orderByPivot('position');
    }
}

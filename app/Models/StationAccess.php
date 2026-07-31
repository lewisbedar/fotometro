<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'external_id',
    'name',
    'reference',
    'number',
    'latitude',
    'longitude',
    'access_type',
    'street',
    'description',
    'wheelchair_accessible',
    'is_active',
    'source',
    'source_payload',
    'source_updated_at',
])]
class StationAccess extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'wheelchair_accessible' => 'boolean',
            'is_active' => 'boolean',
            'source_payload' => 'array',
            'source_updated_at' => 'datetime',
        ];
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(Station::class, 'access_station')
            ->withPivot(['source'])
            ->withTimestamps();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function displayName(?int $index = null): string
    {
        $label = trim((string) ($this->name ?: $this->reference ?: $this->description));

        if ($label === '') {
            return 'Accès '.(($index ?? 0) + 1);
        }

        // Only append entrance/exit for the one-directional cases: stations
        // sometimes have two accesses sharing the same street name, one entry-only
        // and one exit-only, which otherwise look like an exact duplicate in
        // dropdowns. The common bidirectional case isn't worth calling out.
        return match ($this->access_type) {
            'Entrée', 'Sortie' => "{$label} ({$this->access_type})",
            default => $label,
        };
    }
}

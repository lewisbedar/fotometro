<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'line_id',
    'station_id',
    'sequence_key',
    'branch_key',
    'direction_key',
    'position',
    'is_terminus',
    'is_branch_start',
    'is_branch_end',
    'is_loop_entry',
    'is_loop_exit',
    'is_shared_trunk',
    'source',
    'gtfs_pattern',
])]
class LineStationSequence extends Model
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_terminus' => 'boolean',
            'is_branch_start' => 'boolean',
            'is_branch_end' => 'boolean',
            'is_loop_entry' => 'boolean',
            'is_loop_exit' => 'boolean',
            'is_shared_trunk' => 'boolean',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}

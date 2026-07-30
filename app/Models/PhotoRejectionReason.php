<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'slug', 'sort_order', 'is_active'])]
class PhotoRejectionReason extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}

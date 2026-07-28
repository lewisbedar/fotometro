<?php

namespace App\Services\Photos;

use Illuminate\Support\Facades\Cache;

class PhotoCacheInvalidator
{
    public function forgetPublicCaches(): void
    {
        foreach ([
            'fotometro.public-map.v1',
            'fotometro.public-lines.v1',
            'fotometro.public-stations.v1',
        ] as $key) {
            Cache::forget($key);
        }
    }
}

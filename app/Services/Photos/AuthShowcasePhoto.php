<?php

namespace App\Services\Photos;

use App\Models\Photo;

class AuthShowcasePhoto
{
    /**
     * A random published landscape photo, for the login/register background —
     * portrait photos don't fill a wide split-screen panel well.
     */
    public function random(): ?Photo
    {
        return Photo::query()
            ->publiclyVisible()
            ->whereColumn('width', '>', 'height')
            ->with(['station.lines'])
            ->inRandomOrder()
            ->first();
    }
}

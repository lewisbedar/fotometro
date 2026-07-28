<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\View\View;

class PublicPhotoController extends Controller
{
    public function show(Photo $photo): View
    {
        abort_unless(Photo::query()->publiclyVisible()->whereKey($photo->id)->exists(), 404);

        $photo->load(['station', 'stationAccess', 'category']);
        $neighbors = Photo::query()
            ->publiclyVisible()
            ->where('station_id', $photo->station_id)
            ->orderBy('sort_order')
            ->orderBy('taken_at')
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'original_filename']);
        $index = $neighbors->search(fn (Photo $candidate) => $candidate->id === $photo->id);

        return view('photos.show', [
            'photo' => $photo,
            'previousPhoto' => $index === false || $index === 0 ? null : $neighbors[$index - 1],
            'nextPhoto' => $index === false || $index >= $neighbors->count() - 1 ? null : $neighbors[$index + 1],
            'metaDescription' => trim(($photo->title ?: $photo->original_filename).' - '.$photo->station->name.' sur fotometro.'),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPhotoController extends Controller
{
    public function show(Request $request, Photo $photo): View
    {
        abort_unless(Photo::query()->publiclyVisible()->whereKey($photo->id)->exists(), 404);

        $this->recordView($request, $photo);

        $photo->load(['station.lines', 'stationAccess', 'categories']);
        $neighbors = Photo::query()
            ->publiclyVisible()
            ->where('station_id', $photo->station_id)
            ->orderBy('sort_order')
            ->orderByRaw('taken_at IS NULL')
            ->orderBy('taken_at')
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'original_filename']);
        $index = $neighbors->search(fn (Photo $candidate) => $candidate->id === $photo->id);

        return view('photos.show', [
            'photo' => $photo,
            'previousPhoto' => $index === false || $index === 0 ? null : $neighbors[$index - 1],
            'nextPhoto' => $index === false || $index >= $neighbors->count() - 1 ? null : $neighbors[$index + 1],
            'metaDescription' => trim(($photo->title ?: $photo->original_filename).' - '.$photo->station->name.' sur fotométro.'),
        ]);
    }

    /**
     * Counts one view per session per photo, so refreshing the page or
     * navigating back and forth doesn't trivially inflate the count.
     */
    private function recordView(Request $request, Photo $photo): void
    {
        $viewed = $request->session()->get('viewed_photos', []);

        if (in_array($photo->id, $viewed, true)) {
            return;
        }

        $photo->increment('views_count');
        $viewed[] = $photo->id;
        $request->session()->put('viewed_photos', $viewed);
    }
}

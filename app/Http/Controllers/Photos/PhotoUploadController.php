<?php

namespace App\Http\Controllers\Photos;

use App\Enums\PhotoModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Services\Photos\PhotoImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhotoUploadController extends Controller
{
    public function create(): View
    {
        return view('photos.upload', [
            'photo' => new Photo,
            'categories' => PhotoCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'lines' => Line::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(),
            'selectedLineId' => null,
            'mapConfig' => $this->mapConfig(),
        ]);
    }

    public function store(Request $request, PhotoImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file'],
            'station_id' => ['required', 'exists:stations,id'],
            'station_access_id' => ['nullable', 'exists:station_accesses,id'],
            'photo_category_ids' => ['nullable', 'array'],
            'photo_category_ids.*' => ['integer', 'exists:photo_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        // user_id and moderation_status come only from server-side context —
        // never from the request body — regardless of what the client sends.
        $importer->import($data['file'], [
            'station_id' => $data['station_id'],
            'station_access_id' => $data['station_access_id'] ?? null,
            'photo_category_ids' => $data['photo_category_ids'] ?? [],
            'description' => $data['description'] ?? null,
            'copyright_holder' => $request->user()->name,
            'publish_when_ready' => false,
            'user_id' => $request->user()->id,
            'moderation_status' => PhotoModerationStatus::Pending,
        ]);

        return redirect()->route('photos.upload.thanks');
    }

    private function mapConfig(): array
    {
        $map = config('fotometro.map');

        return [
            'basemapDriver' => $map['basemap_driver'] ?? 'raster',
            'styleUrl' => $map['style_url'] ?? '',
            'rasterUrl' => $map['raster_url'] ?? '',
            'rasterTileSize' => $map['raster_tile_size'] ?? 256,
            'attribution' => $map['attribution'] ?? '',
            'centerLongitude' => $map['center']['longitude'] ?? 2.3522,
            'centerLatitude' => $map['center']['latitude'] ?? 48.8566,
            'zoom' => $map['center']['zoom'] ?? 11.5,
            'maxZoom' => $map['center']['max_zoom'] ?? 19,
        ];
    }
}

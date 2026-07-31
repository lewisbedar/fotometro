<?php

namespace App\Http\Controllers\Photos;

use App\Enums\PhotoLicense;
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
    // Admins/moderators get the same upload page as everyone else, but see
    // the richer batch-import wizard instead of the simple one-file form —
    // there's only one "add a photo" entry point in the product, its content
    // just adapts to what the account is trusted to do.
    public function create(Request $request): View
    {
        if ($request->user()->canModerate()) {
            return view('admin.photos.import', [
                'categories' => PhotoCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
                'licenses' => PhotoLicense::cases(),
                'lines' => Line::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(),
            ]);
        }

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
        if ($request->user()->canModerate()) {
            return $this->storeBatch($request, $importer);
        }

        $data = $request->validate([
            'file' => ['required', 'file'],
            'station_id' => ['required', 'exists:stations,id'],
            'station_access_id' => ['nullable', 'exists:station_accesses,id'],
            'line_id' => ['nullable', 'exists:lines,id'],
            'photo_category_ids' => ['nullable', 'array'],
            'photo_category_ids.*' => ['integer', 'exists:photo_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        // user_id and moderation_status come only from server-side context —
        // never from the request body — regardless of what the client sends.
        $importer->import($data['file'], [
            'station_id' => $data['station_id'],
            'station_access_id' => $data['station_access_id'] ?? null,
            'line_id' => $data['line_id'] ?? null,
            'photo_category_ids' => $data['photo_category_ids'] ?? [],
            'description' => $data['description'] ?? null,
            'copyright_holder' => $request->user()->name,
            'publish_when_ready' => false,
            'user_id' => $request->user()->id,
            'moderation_status' => PhotoModerationStatus::Pending,
        ]);

        return redirect()->route('photos.upload.thanks');
    }

    private function storeBatch(Request $request, PhotoImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'credit_line' => ['nullable', 'string', 'max:255'],
            'license' => ['required', 'in:'.collect(PhotoLicense::cases())->pluck('value')->implode(',')],
            'usage_terms' => ['nullable', 'string'],
            'publish_mode' => ['required', 'in:auto,draft'],
            'files' => ['required', 'array', 'max:'.config('fotometro.photos.batch_limit', 20)],
            'files.*' => ['required', 'file'],
            'photos' => ['required', 'array', 'size:'.count($request->file('files', []))],
            'photos.*.station_id' => ['required', 'exists:stations,id'],
            'photos.*.station_access_id' => ['nullable', 'exists:station_accesses,id'],
            'photos.*.line_id' => ['nullable', 'exists:lines,id'],
            'photos.*.photo_category_ids' => ['nullable', 'array'],
            'photos.*.photo_category_ids.*' => ['integer', 'exists:photo_categories,id'],
            'photos.*.description' => ['nullable', 'string'],
        ]);
        $data['publish_when_ready'] = $data['publish_mode'] === 'auto';

        // The titulaire is always the uploading admin, never a free-text field; the copyright
        // notice is derived from the chosen license rather than typed manually (see PhotoLicense::copyrightNotice).
        $holder = $request->user()->name;
        $license = PhotoLicense::from($data['license']);
        $shared = collect($data)->except(['files', 'photos'])->all();
        $shared['copyright_holder'] = $holder;
        $shared['copyright_notice'] = $license->copyrightNotice($holder);
        $shared['user_id'] = $request->user()->id;
        // The batch wizard is trusted content, unlike the simple public
        // upload flow — it skips the moderation queue entirely.
        $shared['moderation_status'] = PhotoModerationStatus::Approved;

        $created = 0;
        $rejected = 0;
        $createdIds = [];

        foreach ($request->file('files', []) as $index => $file) {
            $perPhoto = $data['photos'][$index] ?? [];

            try {
                $photo = $importer->import($file, [...$shared, ...$perPhoto]);
                $createdIds[] = $photo->id;
                $created++;
            } catch (\Throwable) {
                $rejected++;
            }
        }

        $auto = $data['publish_when_ready'] ? 'publication automatique activée' : 'conservées en brouillon';

        // The photo catalog (/admin/photos) is admin-only — a moderator who
        // just imported a batch can't land there, so send them to the same
        // "thanks" page a regular upload ends on instead.
        $redirect = $request->user()->isAdmin()
            ? redirect()->route('admin.photos.index')
            : redirect()->route('photos.upload.thanks');

        return $redirect
            ->with('status', "{$created} photos importées, {$created} en cours de traitement, {$auto}. {$rejected} rejetée(s).")
            ->with('imported_photo_ids', $createdIds);
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

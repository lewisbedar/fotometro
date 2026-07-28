<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PhotoLicense;
use App\Enums\PhotoProcessingStatus;
use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\Station;
use App\Services\Photos\PhotoImporter;
use App\Services\Photos\PhotoCacheInvalidator;
use App\Services\Photos\PhotoProcessor;
use App\Services\Photos\PhotoPublicationService;
use App\Services\Photos\StationCoverageUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PhotoController extends Controller
{
    public function index(Request $request): View
    {
        $photos = Photo::query()
            ->with(['station.lines', 'stationAccess', 'category'])
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', '%'.$request->q.'%')
                ->orWhere('original_filename', 'like', '%'.$request->q.'%')
                ->orWhere('description', 'like', '%'.$request->q.'%')
                ->orWhereHas('station', fn ($station) => $station->where('name', 'like', '%'.$request->q.'%'))))
            ->when($request->filled('station_id'), fn ($query) => $query->where('station_id', $request->station_id))
            ->when($request->filled('category_id'), fn ($query) => $query->where('photo_category_id', $request->category_id))
            ->when($request->filled('processing_status'), fn ($query) => $query->where('processing_status', $request->processing_status))
            ->when($request->filled('published'), fn ($query) => $query->where('is_published', $request->boolean('published')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.photos.index', [
            'photos' => $photos,
            'photoStats' => [
                'published' => Photo::query()->where('is_published', true)->count(),
                'drafts' => Photo::query()->where('processing_status', PhotoProcessingStatus::Ready)->where('is_published', false)->count(),
                'pending' => Photo::query()->where('processing_status', PhotoProcessingStatus::Pending)->count(),
                'failed' => Photo::query()->where('processing_status', PhotoProcessingStatus::Failed)->count(),
            ],
            'stations' => Station::query()->orderBy('name')->limit(500)->get(),
            'categories' => PhotoCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => PhotoProcessingStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.photos.import', $this->formData());
    }

    public function store(Request $request, PhotoImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'exists:stations,id'],
            'station_access_id' => ['nullable', 'exists:station_accesses,id'],
            'photo_category_id' => ['nullable', 'exists:photo_categories,id'],
            'copyright_holder' => ['nullable', 'string', 'max:255'],
            'copyright_notice' => ['nullable', 'string', 'max:255'],
            'credit_line' => ['nullable', 'string', 'max:255'],
            'license' => ['required', 'in:'.collect(PhotoLicense::cases())->pluck('value')->implode(',')],
            'usage_terms' => ['nullable', 'string'],
            'publish_mode' => ['required', 'in:auto,draft'],
            'files' => ['required', 'array', 'max:'.config('fotometro.photos.batch_limit', 20)],
            'files.*' => ['required', 'file'],
        ]);
        $data['publish_when_ready'] = $data['publish_mode'] === 'auto';

        $created = 0;
        $rejected = 0;
        $createdIds = [];

        foreach ($request->file('files', []) as $file) {
            try {
                $photo = $importer->import($file, $data);
                $createdIds[] = $photo->id;
                $created++;
            } catch (\Throwable) {
                $rejected++;
            }
        }

        $auto = $data['publish_when_ready'] ? 'publication automatique activée' : 'conservées en brouillon';

        return redirect()->route('admin.photos.index')
            ->with('status', "{$created} photos importées, {$created} en cours de traitement, {$auto}. {$rejected} rejetée(s).")
            ->with('imported_photo_ids', $createdIds);
    }

    public function show(Photo $photo): View
    {
        return view('admin.photos.show', ['photo' => $photo->load(['station', 'stationAccess', 'category'])]);
    }

    public function edit(Photo $photo): View
    {
        return view('admin.photos.form', [
            'photo' => $photo,
            ...$this->formData($photo->station_id),
        ]);
    }

    public function update(Request $request, Photo $photo, PhotoImporter $importer, StationCoverageUpdater $coverageUpdater): RedirectResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'exists:stations,id'],
            'station_access_id' => ['nullable', 'exists:station_accesses,id'],
            'photo_category_id' => ['nullable', 'exists:photo_categories,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'taken_at' => ['nullable', 'date'],
            'copyright_holder' => ['required', 'string', 'max:255'],
            'copyright_notice' => ['required', 'string', 'max:255'],
            'credit_line' => ['nullable', 'string', 'max:255'],
            'license' => ['required', 'in:'.collect(PhotoLicense::cases())->pluck('value')->implode(',')],
            'usage_terms' => ['nullable', 'string'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'publish_when_ready' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $importer->validateAccess((int) $data['station_id'], $data['station_access_id'] ?? null);
        $oldStation = $photo->station;

        $photo->update([
            ...$data,
            'station_access_id' => $data['station_access_id'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
            'is_published' => $request->boolean('is_published'),
            'publish_when_ready' => $request->boolean('publish_when_ready'),
            'published_at' => $request->boolean('is_published') ? ($photo->published_at ?? now()) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $coverageUpdater->update($oldStation);
        $coverageUpdater->update($photo->station);

        return redirect()->route('admin.photos.show', $photo)->with('status', 'Photo mise à jour.');
    }

    public function destroy(Photo $photo, StationCoverageUpdater $coverageUpdater, PhotoCacheInvalidator $cacheInvalidator): RedirectResponse
    {
        $station = $photo->station;
        Storage::disk(config('fotometro.photos.disk', 'local'))->delete($photo->original_path);
        Storage::disk('public')->delete(array_filter([$photo->web_path, $photo->thumbnail_path]));
        $photo->delete();
        $coverageUpdater->update($station);
        $cacheInvalidator->forgetPublicCaches();

        return redirect()->route('admin.photos.index')->with('status', 'Photo supprimée.');
    }

    public function process(Photo $photo, PhotoProcessor $processor): RedirectResponse
    {
        $photo = $processor->process($photo, true)->fresh();
        $message = $photo->processing_status === PhotoProcessingStatus::Ready
            ? ($photo->is_published ? 'Photo traitée et publiée.' : 'Photo traitée et conservée en brouillon.')
            : 'Le traitement a échoué. Vous pouvez réessayer.';

        return back()->with('status', $message);
    }

    public function publish(Photo $photo, PhotoPublicationService $publication): RedirectResponse
    {
        if (! $publication->publish($photo)) {
            return back()->with('status', 'Cette photo ne peut pas être publiée avant la fin du traitement.');
        }

        return back()->with('status', 'Photo publiée.');
    }

    public function unpublish(Photo $photo, PhotoPublicationService $publication): RedirectResponse
    {
        $publication->unpublish($photo);

        return back()->with('status', 'Photo dépubliée.');
    }

    public function bulk(Request $request, PhotoProcessor $processor, PhotoPublicationService $publication, StationCoverageUpdater $coverageUpdater, PhotoCacheInvalidator $cacheInvalidator): RedirectResponse
    {
        $data = $request->validate([
            'photo_ids' => ['required', 'array'],
            'photo_ids.*' => ['integer', 'exists:photos,id'],
            'bulk_action' => ['required', 'in:publish,unpublish,process,retry,delete'],
        ]);
        $photos = Photo::query()->whereIn('id', $data['photo_ids'])->with('station')->get();
        $done = 0;
        $ignored = 0;
        $limit = (int) config('fotometro.photos.manual_process_limit', 5);

        if (in_array($data['bulk_action'], ['process', 'retry'], true) && $photos->count() > $limit) {
            return back()->with('status', 'Ce lot est trop important pour un traitement immédiat. Il sera traité progressivement.');
        }

        foreach ($photos as $photo) {
            if ($data['bulk_action'] === 'publish') {
                $publication->publish($photo) ? $done++ : $ignored++;
            } elseif ($data['bulk_action'] === 'unpublish') {
                $publication->unpublish($photo);
                $done++;
            } elseif (in_array($data['bulk_action'], ['process', 'retry'], true)) {
                if ($data['bulk_action'] === 'retry' && $photo->processing_status !== PhotoProcessingStatus::Failed) {
                    $ignored++;
                    continue;
                }
                $processor->process($photo, true);
                $done++;
            } elseif ($data['bulk_action'] === 'delete') {
                $station = $photo->station;
                Storage::disk(config('fotometro.photos.disk', 'local'))->delete($photo->original_path);
                Storage::disk('public')->delete(array_filter([$photo->web_path, $photo->thumbnail_path]));
                $photo->delete();
                $coverageUpdater->update($station);
                $cacheInvalidator->forgetPublicCaches();
                $done++;
            }
        }

        return back()->with('status', "{$done} photo(s) traitée(s), {$ignored} ignorée(s).");
    }

    private function formData(?int $stationId = null): array
    {
        $selectedLineId = null;

        if ($stationId) {
            $selectedLineId = Station::query()
                ->whereKey($stationId)
                ->with(['lines' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
                ->first()
                ?->lines
                ->first()
                ?->id;
        }

        return [
            'categories' => PhotoCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'licenses' => PhotoLicense::cases(),
            'lines' => Line::query()
                ->where('is_active', true)
                ->withCount(['stations' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
            'selectedLineId' => $selectedLineId,
            'mapConfig' => $this->mapConfig(),
        ];
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

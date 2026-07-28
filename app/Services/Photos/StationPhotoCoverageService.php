<?php

namespace App\Services\Photos;

use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\Station;
use Illuminate\Support\Collection;

class StationPhotoCoverageService
{
    public function summarize(Station $station): array
    {
        $publicPhotos = Photo::query()
            ->publiclyVisible()
            ->where('station_id', $station->id);

        $lastPhoto = (clone $publicPhotos)
            ->orderByRaw('COALESCE(taken_at, published_at, created_at) DESC')
            ->first(['id', 'taken_at', 'published_at', 'created_at']);

        $categoryBreakdown = $this->categoryBreakdown($station);
        $accessBreakdown = $this->accessBreakdown($station);
        $essential = $this->essentialCoverage($station, $accessBreakdown);

        return [
            'total_photos' => (clone $publicPhotos)->count(),
            'represented_categories' => (clone $publicPhotos)->whereNotNull('photo_category_id')->distinct('photo_category_id')->count('photo_category_id'),
            'total_accesses' => $accessBreakdown['total'],
            'photographed_accesses' => $accessBreakdown['covered'],
            'last_photo_at' => $lastPhoto?->taken_at ?? $lastPhoto?->published_at ?? $lastPhoto?->created_at,
            'category_breakdown' => $categoryBreakdown,
            'access_breakdown' => $accessBreakdown,
            'essential_coverage' => $essential,
            'overall_percentage' => $essential['percentage'],
        ];
    }

    /**
     * Simplified "well documented" rule: a station is considered sufficiently
     * covered once every active access has a photo and the platforms (the
     * 'interieur-quai' sub-category) have at least one. This drives
     * coverage_percentage/coverage_status. The detailed axis breakdown above
     * stays available as supplementary "what's missing" information rather
     * than as part of the pass/fail criterion.
     *
     * @return array{accesses_percentage: int, accesses_missing: Collection<int, \App\Models\StationAccess>, platforms_photographed: bool, percentage: int, complete: bool}
     */
    public function essentialCoverage(Station $station, ?array $accessBreakdown = null): array
    {
        $accessBreakdown ??= $this->accessBreakdown($station);
        $hasAccesses = $accessBreakdown['total'] > 0;

        $platformsPhotographed = Photo::query()
            ->publiclyVisible()
            ->where('station_id', $station->id)
            ->whereHas('category', fn ($query) => $query->where('slug', 'interieur-quai'))
            ->exists();

        // A station without any registered access isn't penalized for it (that
        // criterion is dropped from the average entirely) but, unlike a simple
        // default of 100, a station with zero photos must still read as 0%.
        $components = collect([$platformsPhotographed ? 100 : 0]);
        if ($hasAccesses) {
            $components->push($accessBreakdown['percentage']);
        }

        return [
            'accesses_percentage' => $hasAccesses ? $accessBreakdown['percentage'] : null,
            'accesses_missing' => $accessBreakdown['missing'],
            'platforms_photographed' => $platformsPhotographed,
            'percentage' => (int) round($components->average()),
            'complete' => $accessBreakdown['missing']->isEmpty() && $platformsPhotographed,
        ];
    }

    /**
     * Per top-level category (Extérieur, Intérieur, ...), coverage is the share of
     * active sub-categories that have at least one publicly visible photo. The
     * sub-category tree acts as the shot list expected for that axis; the ones
     * without a photo yet are surfaced as 'missing' so the page can read as a
     * checklist.
     *
     * @return Collection<int, array{category: PhotoCategory, covered: int, total: int, percentage: int, missing: Collection<int, PhotoCategory>}>
     */
    public function categoryBreakdown(Station $station): Collection
    {
        $coveredChildIds = Photo::query()
            ->publiclyVisible()
            ->where('station_id', $station->id)
            ->whereNotNull('photo_category_id')
            ->distinct()
            ->pluck('photo_category_id');

        return PhotoCategory::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (PhotoCategory $root) use ($coveredChildIds): array {
                $children = $root->children()->where('is_active', true)->get();
                $missing = $children->reject(fn (PhotoCategory $child) => $coveredChildIds->contains($child->id))->values();
                $total = $children->count();
                $covered = $total - $missing->count();

                return [
                    'category' => $root,
                    'covered' => $covered,
                    'total' => $total,
                    'percentage' => $total > 0 ? (int) round($covered / $total * 100) : 0,
                    'missing' => $missing,
                ];
            });
    }

    /**
     * Entrées-sorties coverage: share of active accesses with at least one publicly
     * visible photo attached. The uncovered accesses are surfaced as 'missing'.
     *
     * @return array{covered: int, total: int, percentage: int, missing: Collection<int, \App\Models\StationAccess>}
     */
    public function accessBreakdown(Station $station): array
    {
        $publicPhotos = Photo::query()
            ->publiclyVisible()
            ->where('station_id', $station->id);

        $activeAccesses = $station->accesses()->where('station_accesses.is_active', true)->get();
        $coveredAccessIds = (clone $publicPhotos)->whereNotNull('station_access_id')->distinct()->pluck('station_access_id');
        $missing = $activeAccesses->reject(fn ($access) => $coveredAccessIds->contains($access->id))->values();
        $total = $activeAccesses->count();
        $covered = $total - $missing->count();

        return [
            'covered' => $covered,
            'total' => $total,
            'percentage' => $total > 0 ? (int) round($covered / $total * 100) : 0,
            'missing' => $missing,
        ];
    }
}

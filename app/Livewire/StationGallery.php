<?php

namespace App\Livewire;

use App\Models\Line;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\Station;
use App\Models\StationAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StationGallery extends Component
{
    use WithPagination;

    public Station $station;

    #[Url(as: 'category', history: true)]
    public ?string $categorySlug = null;

    #[Url(as: 'access', history: true)]
    public ?int $accessId = null;

    #[Url(as: 'ligne', history: true)]
    public ?int $lineId = null;

    public function mount(Station $station): void
    {
        $this->station = $station;
    }

    public function updatedCategorySlug(): void
    {
        $this->resetPage();
    }

    public function updatedAccessId(): void
    {
        $this->resetPage();
    }

    public function updatedLineId(): void
    {
        $this->resetPage();
    }

    public function selectCategory(?string $slug): void
    {
        $this->categorySlug = $slug;
        $this->resetPage();
    }

    public function selectAccess(?int $accessId): void
    {
        $this->accessId = $accessId;
        $this->resetPage();
    }

    public function selectLine(?int $lineId): void
    {
        $this->lineId = $lineId;
        $this->resetPage();
    }

    #[On('filterByAccess')]
    public function filterByAccess(int $accessId): void
    {
        $this->selectAccess($accessId);
    }

    public function resetFilters(): void
    {
        $this->categorySlug = null;
        $this->accessId = null;
        $this->lineId = null;
        $this->resetPage();
    }

    #[Computed]
    public function selectedCategory(): ?PhotoCategory
    {
        if (! $this->categorySlug) {
            return null;
        }

        return PhotoCategory::query()->where('slug', $this->categorySlug)->where('is_active', true)->first();
    }

    #[Computed]
    public function selectedAccess(): ?StationAccess
    {
        if (! $this->accessId) {
            return null;
        }

        return $this->station->accesses()
            ->where('station_accesses.id', $this->accessId)
            ->where('station_accesses.is_active', true)
            ->first();
    }

    #[Computed]
    public function selectedLine(): ?Line
    {
        if (! $this->lineId) {
            return null;
        }

        return $this->station->lines->firstWhere('id', $this->lineId);
    }

    #[Computed]
    public function allPhotos(): Collection
    {
        return $this->basePhotosQuery()->get();
    }

    /**
     * Only meaningful (and only shown by the view) for interchange stations —
     * a single-line station has nothing to filter by.
     */
    #[Computed]
    public function lineFilters(): Collection
    {
        if ($this->station->lines->count() < 2) {
            return collect();
        }

        return $this->station->lines->map(fn (Line $line) => [
            'line' => $line,
            'count' => $this->allPhotos->where('line_id', $line->id)->count(),
        ]);
    }

    #[Computed]
    public function categoryFilters(): Collection
    {
        // Each photo can carry several categories now, so it may contribute to
        // several root groups at once (e.g. a photo tagged both "Entrée" and
        // "Carrelage" counts under both Extérieur and Architecture).
        $photoRoots = $this->allPhotos->map(fn (Photo $photo) => $photo->categories
            ->map(fn (PhotoCategory $category) => $category->parent ?: $category)
            ->unique('id'));

        return $photoRoots->flatten(1)->unique('id')->keyBy('id')
            ->map(fn (PhotoCategory $root) => [
                'category' => $root,
                'count' => $photoRoots->filter(fn (Collection $roots) => $roots->contains('id', $root->id))->count(),
            ])
            ->sortBy(fn (array $item) => [$item['category']->sort_order, $item['category']->name])
            ->values();
    }

    #[Computed]
    public function subCategoryFilters(): Collection
    {
        $selected = $this->selectedCategory;

        if (! $selected || $selected->parent_id !== null) {
            return collect();
        }

        return $this->allPhotos
            ->flatMap(fn (Photo $photo) => $photo->categories)
            ->filter(fn (PhotoCategory $category) => (int) $category->parent_id === (int) $selected->id)
            ->groupBy('id')
            ->map(fn (Collection $categories) => [
                'category' => $categories->first(),
                'count' => $categories->count(),
            ])
            ->sortBy(fn (array $item) => [$item['category']->sort_order, $item['category']->name])
            ->values();
    }

    private function basePhotosQuery(): Builder
    {
        return Photo::query()
            ->publiclyVisible()
            ->where('station_id', $this->station->id)
            ->with(['categories.parent', 'stationAccess'])
            ->orderBy('sort_order')
            ->orderByRaw('taken_at IS NULL')
            ->orderBy('taken_at')
            ->orderBy('id');
    }

    private function applyCategoryFilter(Builder $query, PhotoCategory $category): void
    {
        // A photo matches if at least one of its (possibly several) categories
        // falls within the targeted set — not an exact single-category match.
        if ($category->parent_id !== null) {
            $query->whereHas('categories', fn ($categories) => $categories->whereKey($category->id));

            return;
        }

        $childIds = $category->children()->pluck('id')->all();
        $query->whereHas('categories', fn ($categories) => $categories->whereIn('photo_categories.id', [$category->id, ...$childIds]));
    }

    public function render(): View
    {
        $query = $this->basePhotosQuery();

        if ($category = $this->selectedCategory) {
            $this->applyCategoryFilter($query, $category);
        }

        if ($access = $this->selectedAccess) {
            $query->where('station_access_id', $access->id);
        }

        if ($line = $this->selectedLine) {
            $query->where('line_id', $line->id);
        }

        return view('livewire.station-gallery', [
            'photos' => $query->paginate(24),
        ]);
    }
}

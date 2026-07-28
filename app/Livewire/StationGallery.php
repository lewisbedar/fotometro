<?php

namespace App\Livewire;

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

    #[On('filterByAccess')]
    public function filterByAccess(int $accessId): void
    {
        $this->selectAccess($accessId);
    }

    public function resetFilters(): void
    {
        $this->categorySlug = null;
        $this->accessId = null;
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
    public function allPhotos(): Collection
    {
        return $this->basePhotosQuery()->get();
    }

    #[Computed]
    public function categoryFilters(): Collection
    {
        $photos = $this->allPhotos;

        return $photos
            ->map(fn (Photo $photo) => $photo->category?->parent ?: $photo->category)
            ->filter()
            ->groupBy('id')
            ->map(fn (Collection $categories, int $id) => [
                'category' => $categories->first(),
                'count' => $photos->filter(fn (Photo $photo) => $photo->category?->id === $id || $photo->category?->parent_id === $id)->count(),
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
            ->map(fn (Photo $photo) => $photo->category)
            ->filter(fn (?PhotoCategory $category) => $category && (int) $category->parent_id === (int) $selected->id)
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
            ->with(['category.parent', 'stationAccess'])
            ->orderBy('sort_order')
            ->orderByRaw('taken_at IS NULL')
            ->orderBy('taken_at')
            ->orderBy('id');
    }

    private function applyCategoryFilter(Builder $query, PhotoCategory $category): void
    {
        if ($category->parent_id !== null) {
            $query->where('photo_category_id', $category->id);

            return;
        }

        $childIds = $category->children()->pluck('id')->all();
        $query->whereIn('photo_category_id', [$category->id, ...$childIds]);
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

        return view('livewire.station-gallery', [
            'photos' => $query->paginate(24),
        ]);
    }
}

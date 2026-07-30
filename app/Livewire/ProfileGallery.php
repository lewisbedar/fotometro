<?php

namespace App\Livewire;

use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProfileGallery extends Component
{
    use WithPagination;

    public User $user;

    #[Url(as: 'tri', history: true)]
    public string $sort = 'recent';

    #[Url(as: 'categorie', history: true)]
    public ?string $categorySlug = null;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedCategorySlug(): void
    {
        $this->resetPage();
    }

    public function selectCategory(?string $slug): void
    {
        $this->categorySlug = $slug;
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
    public function allPhotos(): Collection
    {
        return $this->basePhotosQuery()->get();
    }

    #[Computed]
    public function categoryFilters(): Collection
    {
        // Each photo can carry several categories, so it may contribute to
        // several root groups at once — same convention as StationGallery.
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
        $query = Photo::query()
            ->publiclyVisible()
            ->where('user_id', $this->user->id)
            ->with(['categories.parent', 'stationAccess', 'station']);

        match ($this->sort) {
            'popular' => $query->orderByDesc('views_count')->orderByDesc('id'),
            'oldest' => $query->orderByRaw('taken_at IS NULL')->orderBy('taken_at')->orderBy('id'),
            default => $query->orderByRaw('taken_at IS NULL')->orderByDesc('taken_at')->orderByDesc('id'),
        };

        return $query;
    }

    private function applyCategoryFilter(Builder $query, PhotoCategory $category): void
    {
        // A photo matches if at least one of its (possibly several)
        // categories falls within the targeted set.
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

        return view('livewire.profile-gallery', [
            'photos' => $query->paginate(24),
        ]);
    }
}

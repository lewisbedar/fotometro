<?php

namespace App\Livewire;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProfileGallery extends Component
{
    use WithPagination;

    public User $user;

    #[Url(as: 'tri', history: true)]
    public string $sort = 'recent';

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function render(): View
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

        return view('livewire.profile-gallery', [
            'photos' => $query->paginate(24),
        ]);
    }
}

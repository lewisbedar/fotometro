<?php

namespace App\Livewire;

use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\PhotoRejectionReason;
use App\Models\Station;
use App\Services\Photos\PhotoPublicationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class PhotoModerationQueue extends Component
{
    public ?int $currentPhotoId = null;

    public bool $editing = false;

    public bool $rejecting = false;

    // Edit panel (up/down) fields.
    public ?int $station_id = null;

    public ?int $station_access_id = null;

    /** @var array<int,int> */
    public array $category_ids = [];

    public ?string $description = null;

    // Reject panel (left) fields.
    public ?int $rejection_reason_id = null;

    public string $custom_rejection_note = '';

    public function mount(): void
    {
        Gate::authorize('moderate-photos');

        $this->loadNext();
    }

    #[Computed]
    public function currentPhoto(): ?Photo
    {
        if (! $this->currentPhotoId) {
            return null;
        }

        return Photo::query()->with(['station', 'stationAccess', 'categories', 'user'])->find($this->currentPhotoId);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return Photo::query()->awaitingModeration()->count();
    }

    #[Computed]
    public function rejectionReasons(): Collection
    {
        return PhotoRejectionReason::query()->where('is_active', true)->orderBy('sort_order')->orderBy('label')->get();
    }

    #[Computed]
    public function availableCategories(): Collection
    {
        return PhotoCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function availableStations(): Collection
    {
        return Station::query()->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function availableAccessesForSelectedStation(): Collection
    {
        if (! $this->station_id) {
            return collect();
        }

        return Station::query()->find($this->station_id)?->accesses()->where('is_active', true)->get() ?? collect();
    }

    public function loadNext(): void
    {
        $this->editing = false;
        $this->rejecting = false;
        $this->reset(['rejection_reason_id', 'custom_rejection_note']);

        $this->currentPhotoId = Photo::query()->awaitingModeration()->first()?->id;
        unset($this->currentPhoto);
    }

    public function approve(PhotoPublicationService $publication): void
    {
        Gate::authorize('moderate-photos');

        if (! $photo = $this->currentPhoto) {
            return;
        }

        $publication->publish($photo);
        $this->loadNext();
    }

    public function startReject(): void
    {
        $this->rejecting = true;
        $this->editing = false;
    }

    public function cancelReject(): void
    {
        $this->rejecting = false;
        $this->reset(['rejection_reason_id', 'custom_rejection_note']);
    }

    public function reject(PhotoPublicationService $publication): void
    {
        Gate::authorize('moderate-photos');

        if (! $photo = $this->currentPhoto) {
            return;
        }

        $this->validate([
            'rejection_reason_id' => ['nullable', 'integer', 'exists:photo_rejection_reasons,id'],
            'custom_rejection_note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $this->rejection_reason_id && trim($this->custom_rejection_note) === '') {
            $this->addError('custom_rejection_note', 'Choisissez un motif ou précisez-en un.');

            return;
        }

        $reason = $this->rejection_reason_id
            ? PhotoRejectionReason::query()->find($this->rejection_reason_id)
            : null;

        $publication->reject($photo, $reason, trim($this->custom_rejection_note) ?: null);
        $this->loadNext();
    }

    public function startEdit(): void
    {
        if (! $photo = $this->currentPhoto) {
            return;
        }

        $this->station_id = $photo->station_id;
        $this->station_access_id = $photo->station_access_id;
        $this->category_ids = $photo->categories->pluck('id')->all();
        $this->description = $photo->description;
        $this->editing = true;
        $this->rejecting = false;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
    }

    public function saveEdit(PhotoPublicationService $publication): void
    {
        Gate::authorize('moderate-photos');

        if (! $photo = $this->currentPhoto) {
            return;
        }

        $data = $this->validate([
            'station_id' => ['required', 'exists:stations,id'],
            'station_access_id' => ['nullable', 'exists:station_accesses,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:photo_categories,id'],
            'description' => ['nullable', 'string'],
        ]);

        $photo->update([
            'station_id' => $data['station_id'],
            'station_access_id' => $data['station_access_id'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
        $photo->categories()->sync($data['category_ids'] ?? []);

        // Editing and saving auto-approves — no separate approve click after
        // a manual correction (decided with the user up front).
        $publication->publish($photo->fresh());
        $this->editing = false;
        $this->loadNext();
    }

    public function render(): View
    {
        return view('livewire.photo-moderation-queue');
    }
}

<?php

namespace App\Services\Photos;

use App\Enums\PhotoProcessingStatus;
use App\Models\Photo;

class PhotoPublicationService
{
    public function __construct(
        private readonly StationCoverageUpdater $coverageUpdater,
        private readonly PhotoCacheInvalidator $cacheInvalidator,
    ) {}

    public function publish(Photo $photo): bool
    {
        if ($photo->processing_status !== PhotoProcessingStatus::Ready) {
            return false;
        }

        $photo->forceFill([
            'is_published' => true,
            'published_at' => $photo->published_at ?? now(),
        ])->save();

        $this->afterVisibilityChange($photo);

        return true;
    }

    public function unpublish(Photo $photo): void
    {
        $photo->forceFill([
            'is_published' => false,
            'published_at' => null,
        ])->save();

        if ($photo->station->cover_photo_id === $photo->id) {
            $photo->station->forceFill(['cover_photo_id' => null])->save();
        }

        $this->afterVisibilityChange($photo);
    }

    public function afterVisibilityChange(Photo $photo): void
    {
        $this->coverageUpdater->update($photo->station);
        $this->cacheInvalidator->forgetPublicCaches();
    }
}

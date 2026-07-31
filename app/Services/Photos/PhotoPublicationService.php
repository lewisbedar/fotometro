<?php

namespace App\Services\Photos;

use App\Enums\PhotoModerationStatus;
use App\Enums\PhotoProcessingStatus;
use App\Models\Photo;
use App\Models\PhotoRejectionReason;
use App\Notifications\PhotoPublishedNotification;
use App\Notifications\PhotoRejectedNotification;
use Illuminate\Support\Facades\Auth;

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

        $wasPublished = $photo->is_published;

        $photo->forceFill([
            'is_published' => true,
            'published_at' => $photo->published_at ?? now(),
            'moderation_status' => PhotoModerationStatus::Approved,
            'moderated_at' => $photo->moderated_at ?? now(),
            'moderated_by' => $photo->moderated_by ?? Auth::id(),
        ])->save();

        // Only on the first publish transition, not on a re-save (e.g. the
        // moderation queue's edit-then-republish flow calling publish() again).
        if (! $wasPublished && $photo->user_id) {
            $photo->user->notify(new PhotoPublishedNotification($photo));
        }

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

    /**
     * A rejected photo was never public — no coverage/cache invalidation
     * needed, unlike publish()/unpublish().
     */
    public function reject(Photo $photo, ?PhotoRejectionReason $reason, ?string $note): void
    {
        $photo->forceFill([
            'moderation_status' => PhotoModerationStatus::Rejected,
            'photo_rejection_reason_id' => $reason?->id,
            'rejection_note' => $note,
            'moderated_at' => now(),
            'moderated_by' => Auth::id(),
        ])->save();

        if ($photo->user_id) {
            $photo->user->notify(new PhotoRejectedNotification($reason?->label, $note));
        }
    }

    public function afterVisibilityChange(Photo $photo): void
    {
        $this->coverageUpdater->update($photo->station);
        $this->cacheInvalidator->forgetPublicCaches();
    }
}

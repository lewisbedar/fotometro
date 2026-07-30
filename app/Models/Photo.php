<?php

namespace App\Models;

use App\Enums\PhotoLicense;
use App\Enums\PhotoModerationStatus;
use App\Enums\PhotoProcessingStatus;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'station_id',
    'station_access_id',
    'title',
    'slug',
    'description',
    'original_filename',
    'original_path',
    'web_path',
    'thumbnail_path',
    'mime_type',
    'file_size',
    'width',
    'height',
    'orientation',
    'taken_at',
    'camera_make',
    'camera_model',
    'lens',
    'focal_length',
    'focal_length_35mm',
    'aperture',
    'shutter_speed',
    'iso',
    'latitude',
    'longitude',
    'copyright_holder',
    'copyright_notice',
    'credit_line',
    'license',
    'usage_terms',
    'processing_status',
    'processing_error',
    'is_featured',
    'is_published',
    'publish_when_ready',
    'published_at',
    'sort_order',
])]
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'published_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'focal_length' => 'decimal:2',
            'aperture' => 'decimal:2',
            'processing_status' => PhotoProcessingStatus::class,
            'license' => PhotoLicense::class,
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'publish_when_ready' => 'boolean',
            'moderation_status' => PhotoModerationStatus::class,
            'moderated_at' => 'datetime',
        ];
    }

    public function publicLabel(): string
    {
        return $this->title
            ?: $this->categories->first()?->name
            ?: 'Photographie de '.$this->station?->name;
    }

    public function adminStatusLabel(): string
    {
        return match (true) {
            $this->processing_status === PhotoProcessingStatus::Pending => 'En attente',
            $this->processing_status === PhotoProcessingStatus::Processing => 'Traitement',
            $this->processing_status === PhotoProcessingStatus::Failed => 'Erreur',
            $this->processing_status === PhotoProcessingStatus::Ready && $this->is_published => 'Publiée',
            $this->processing_status === PhotoProcessingStatus::Ready => 'Brouillon',
            default => $this->processing_status->label(),
        };
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function stationAccess(): BelongsTo
    {
        return $this->belongsTo(StationAccess::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PhotoCategory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(PhotoRejectionReason::class, 'photo_rejection_reason_id');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('processing_status', PhotoProcessingStatus::Ready)
            ->where('is_published', true)
            // Belt-and-braces: PhotoPublicationService::publish() is the only
            // place that flips is_published to true, and it already stamps
            // moderation_status=Approved when it does — this re-checks the
            // invariant explicitly rather than trusting it silently, given
            // "no data leak" is this feature's top priority.
            ->where('moderation_status', PhotoModerationStatus::Approved)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()))
            ->whereHas('station', fn (Builder $station) => $station->where('is_active', true));
    }

    public function scopeAwaitingModeration(Builder $query): Builder
    {
        return $query
            ->where('moderation_status', PhotoModerationStatus::Pending)
            ->where('processing_status', PhotoProcessingStatus::Ready)
            ->orderBy('created_at');
    }

    public function publicWebUrl(): ?string
    {
        return $this->web_url;
    }

    public function publicThumbnailUrl(): ?string
    {
        return $this->thumbnail_url;
    }

    public function getWebUrlAttribute(): ?string
    {
        return $this->publicUrlFor($this->web_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->publicUrlFor($this->thumbnail_path);
    }

    private function publicUrlFor(?string $path): ?string
    {
        if (! $path || Str::startsWith($path, 'photos/originals/')) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}

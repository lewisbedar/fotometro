<?php

namespace App\Services\Photos;

use App\Enums\PhotoLicense;
use App\Enums\PhotoModerationStatus;
use App\Enums\PhotoProcessingStatus;
use App\Models\Photo;
use App\Models\StationAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhotoImporter
{
    public function __construct(
        private readonly ExifReader $exif,
        private readonly PhotoPathGenerator $paths,
        private readonly PhotoProcessor $processor,
    ) {}

    public function import(UploadedFile $file, array $attributes): Photo
    {
        $this->validateFile($file);
        $this->validateAccess((int) $attributes['station_id'], $attributes['station_access_id'] ?? null);

        $disk = Storage::disk(config('fotometro.photos.disk', 'local'));
        $path = $this->paths->originalPath($file);

        return DB::transaction(function () use ($file, $attributes, $disk, $path): Photo {
            $disk->put($path, file_get_contents($file->getRealPath()));
            $absolutePath = $disk->path($path);
            $size = getimagesize($absolutePath);
            $exif = $this->exif->read($absolutePath);
            $holder = $this->copyrightHolder($attributes, $exif);

            $photo = Photo::query()->create([
                'station_id' => $attributes['station_id'],
                'station_access_id' => $attributes['station_access_id'] ?? null,
                'line_id' => $attributes['line_id'] ?? null,
                'title' => $attributes['title'] ?? null,
                'slug' => $this->uniqueSlug($attributes['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
                'description' => $attributes['description'] ?? null,
                'original_filename' => $file->getClientOriginalName(),
                'original_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize() ?: 0,
                'width' => $size ? (int) $size[0] : null,
                'height' => $size ? (int) $size[1] : null,
                'orientation' => $exif['orientation'] ?? null,
                'taken_at' => $attributes['taken_at'] ?? $exif['taken_at'] ?? null,
                'camera_make' => $exif['camera_make'] ?? null,
                'camera_model' => $exif['camera_model'] ?? null,
                'lens' => $exif['lens'] ?? null,
                'focal_length' => $exif['focal_length'] ?? null,
                'focal_length_35mm' => $exif['focal_length_35mm'] ?? null,
                'aperture' => $exif['aperture'] ?? null,
                'shutter_speed' => $exif['shutter_speed'] ?? null,
                'iso' => $exif['iso'] ?? null,
                'latitude' => $exif['latitude'] ?? null,
                'longitude' => $exif['longitude'] ?? null,
                'copyright_holder' => $holder,
                'copyright_notice' => $this->copyrightNotice($attributes, $holder),
                'credit_line' => $attributes['credit_line'] ?? config('fotometro.photos.default_credit_line'),
                'license' => $attributes['license'] ?? config('fotometro.photos.default_license', PhotoLicense::AllRightsReserved->value),
                'usage_terms' => $attributes['usage_terms'] ?? config('fotometro.photos.default_usage_terms'),
                'processing_status' => PhotoProcessingStatus::Pending,
                'is_featured' => (bool) ($attributes['is_featured'] ?? false),
                'is_published' => false,
                'publish_when_ready' => (bool) ($attributes['publish_when_ready'] ?? true),
                'published_at' => null,
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            ]);

            $photo->categories()->sync($attributes['photo_category_ids'] ?? []);

            // Not mass-fillable on purpose (see Photo's #[Fillable]) — these
            // two decide whether a photo goes straight to public or sits in
            // the moderation queue, so they're only ever set here from
            // server-controlled context, never from a request payload.
            $photo->forceFill([
                'user_id' => $attributes['user_id'] ?? null,
                'moderation_status' => $attributes['moderation_status'] ?? PhotoModerationStatus::Pending,
            ])->save();

            if (config('fotometro.photos.process_synchronously', false)) {
                $this->processor->process($photo);
            }

            return $photo;
        });
    }

    private function validateFile(UploadedFile $file): void
    {
        $maxBytes = (int) config('fotometro.photos.max_upload_mb', 40) * 1024 * 1024;
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $size = @getimagesize($file->getRealPath());

        if (! $file->isValid() || $file->getSize() > $maxBytes || ! $size) {
            throw ValidationException::withMessages(['files' => 'Le fichier image est invalide ou trop volumineux.']);
        }

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['files' => 'Format photo non autorise.']);
        }

        // Uploads are no longer limited to a trusted admin — guard against a
        // small file that decompresses into a huge pixel grid (a classic
        // decompression-bomb DoS vector) now that the public can submit.
        if (((int) $size[0]) * ((int) $size[1]) > 50_000_000) {
            throw ValidationException::withMessages(['files' => 'Les dimensions de l’image sont trop importantes.']);
        }
    }

    public function validateAccess(int $stationId, mixed $accessId): void
    {
        if ($accessId === null || $accessId === '') {
            return;
        }

        $valid = StationAccess::query()
            ->whereKey($accessId)
            ->whereHas('stations', fn ($query) => $query->whereKey($stationId))
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages(['station_access_id' => 'Cet accès n’appartient pas à la station sélectionnée.']);
        }
    }

    private function copyrightHolder(array $attributes, array $exif): string
    {
        foreach ([$attributes['copyright_holder'] ?? null, $exif['exif_artist'] ?? null, $exif['exif_copyright'] ?? null, config('fotometro.photos.default_copyright_holder')] as $candidate) {
            $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return 'fotometro';
    }

    private function copyrightNotice(array $attributes, string $holder): string
    {
        $notice = trim((string) ($attributes['copyright_notice'] ?? config('fotometro.photos.default_copyright_notice') ?? ''));

        return $notice !== '' ? $notice : "© {$holder} — Tous droits réservés";
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'photo';
        $candidate = $slug;
        $index = 2;

        while (Photo::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$index}";
            $index++;
        }

        return $candidate;
    }
}

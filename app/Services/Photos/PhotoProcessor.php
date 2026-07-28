<?php

namespace App\Services\Photos;

use App\Enums\PhotoProcessingStatus;
use App\Models\Photo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PhotoProcessor
{
    public function __construct(
        private readonly StationCoverageUpdater $coverageUpdater,
        private readonly PhotoCacheInvalidator $cacheInvalidator,
    ) {}

    public function process(Photo $photo, bool $force = false): Photo
    {
        if (! $force && $photo->processing_status === PhotoProcessingStatus::Ready) {
            return $photo;
        }

        $photo->forceFill([
            'processing_status' => PhotoProcessingStatus::Processing,
            'processing_error' => null,
        ])->save();

        $public = Storage::disk('public');
        $private = Storage::disk(config('fotometro.photos.disk', 'local'));
        $webPath = app(PhotoPathGenerator::class)->webPath($photo->original_path);
        $thumbnailPath = app(PhotoPathGenerator::class)->thumbnailPath($photo->original_path);

        try {
            if (! function_exists('imagecreatetruecolor')) {
                throw new RuntimeException('GD is not available for image processing.');
            }

            $originalPath = $private->path($photo->original_path);
            [$image, $width, $height] = $this->readImage($originalPath, $photo->mime_type);
            $image = $this->applyOrientation($image, $photo->orientation);

            $web = $this->resize($image, imagesx($image), imagesy($image), (int) config('fotometro.photos.web_max_width', 2200));
            $thumb = $this->resize($image, imagesx($image), imagesy($image), (int) config('fotometro.photos.thumbnail_width', 600));

            $this->saveImage($web, $public->path($webPath), $photo->mime_type, (int) config('fotometro.photos.web_quality', 85));
            $this->saveImage($thumb, $public->path($thumbnailPath), $photo->mime_type, (int) config('fotometro.photos.thumbnail_quality', 82));

            imagedestroy($image);
            imagedestroy($web);
            imagedestroy($thumb);

            [$finalWidth, $finalHeight] = getimagesize($public->path($webPath)) ?: [$width, $height];

            $readyAttributes = [
                'web_path' => $webPath,
                'thumbnail_path' => $thumbnailPath,
                'width' => $finalWidth,
                'height' => $finalHeight,
                'processing_status' => PhotoProcessingStatus::Ready,
                'processing_error' => null,
            ];

            if ($photo->publish_when_ready) {
                $readyAttributes['is_published'] = true;
                $readyAttributes['published_at'] = now();
            }

            $photo->forceFill($readyAttributes)->save();

            $this->coverageUpdater->update($photo->station);
            $this->cacheInvalidator->forgetPublicCaches();

            return $photo;
        } catch (\Throwable $exception) {
            $public->delete([$webPath, $thumbnailPath]);
            Log::error('Photo processing failed.', ['photo_id' => $photo->id, 'error' => $exception->getMessage()]);
            $photo->forceFill([
                'processing_status' => PhotoProcessingStatus::Failed,
                'processing_error' => $exception->getMessage(),
            ])->save();

            $this->coverageUpdater->update($photo->station);
            $this->cacheInvalidator->forgetPublicCaches();

            return $photo;
        }
    }

    private function readImage(string $path, string $mime): array
    {
        $size = getimagesize($path);

        if ($size === false) {
            throw new RuntimeException('The original file is not a readable image.');
        }

        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };

        if (! $image) {
            throw new RuntimeException("Unsupported image MIME type {$mime}.");
        }

        return [$image, (int) $size[0], (int) $size[1]];
    }

    private function applyOrientation(\GdImage $image, ?int $orientation): \GdImage
    {
        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function resize(\GdImage $image, int $width, int $height, int $maxWidth): \GdImage
    {
        if ($width <= $maxWidth) {
            $copy = imagecreatetruecolor($width, $height);
            imagealphablending($copy, false);
            imagesavealpha($copy, true);
            imagecopy($copy, $image, 0, 0, 0, 0, $width, $height);

            return $copy;
        }

        $newWidth = $maxWidth;
        $newHeight = max(1, (int) round($height * ($newWidth / $width)));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private function saveImage(\GdImage $image, string $path, string $mime, int $quality): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, $quality),
            'image/png' => imagepng($image, $path),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $path, $quality) : false,
            default => false,
        };

        if (! $saved) {
            throw new RuntimeException('Unable to save processed image.');
        }
    }
}

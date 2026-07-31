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

            // Only the (already resolution-capped) web version is marked, not
            // the thumbnail — a casual-deterrence watermark on a 600px grid
            // preview would just be visual clutter for little benefit, and
            // this is about discouraging reuse of the size worth reusing.
            $this->applyWatermark($web);

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

    /**
     * A light, casual-deterrence watermark burned into the pixels — unlike a
     * CSS overlay, this stays on the image however it's obtained (saved via
     * right-click, fetched from the direct URL, grabbed from devtools).
     * Uses GD's built-in bitmap font rather than a bundled .ttf: one less
     * asset to deploy/miss on a shared host, and legibility here only
     * matters enough to make the source identifiable, not to look polished.
     * The built-in font only understands single-byte encodings, not UTF-8,
     * so the text is converted to Latin-1 first (covers French accents —
     * scripts outside Latin-1 would need a bundled .ttf instead).
     */
    private function applyWatermark(\GdImage $image): void
    {
        if (! config('fotometro.photos.watermark.enabled', false)) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $text = config('fotometro.photos.watermark.text') ?: (parse_url(config('app.url', ''), PHP_URL_HOST) ?: 'fotometro');
        $text = $this->toLatin1ForBuiltInFont($text);
        $opacity = max(0.0, min(1.0, (float) config('fotometro.photos.watermark.opacity', 0.45)));

        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);

        // Scale the fixed-size bitmap font relative to the image width so
        // the watermark stays legible on a 2200px photo without dominating
        // a smaller one.
        $scale = max(1, (int) round($width / 900));
        $scaledWidth = $textWidth * $scale;
        $scaledHeight = $textHeight * $scale;

        $glyphs = imagecreatetruecolor($textWidth, $textHeight);
        imagealphablending($glyphs, false);
        imagesavealpha($glyphs, true);
        imagefilledrectangle($glyphs, 0, 0, $textWidth - 1, $textHeight - 1, imagecolorallocatealpha($glyphs, 0, 0, 0, 127));
        imagestring($glyphs, $font, 0, 0, $text, imagecolorallocatealpha($glyphs, 255, 255, 255, (int) round(127 * (1 - $opacity))));

        $overlay = imagecreatetruecolor($scaledWidth, $scaledHeight);
        imagealphablending($overlay, false);
        imagesavealpha($overlay, true);
        imagefilledrectangle($overlay, 0, 0, $scaledWidth - 1, $scaledHeight - 1, imagecolorallocatealpha($overlay, 0, 0, 0, 127));
        imagecopyresampled($overlay, $glyphs, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $textWidth, $textHeight);
        imagedestroy($glyphs);

        $padding = max(10, (int) round($width * 0.015));
        [$destX, $destY] = match (config('fotometro.photos.watermark.position', 'bottom-right')) {
            'bottom-left' => [$padding, $height - $scaledHeight - $padding],
            'top-right' => [$width - $scaledWidth - $padding, $padding],
            'top-left' => [$padding, $padding],
            default => [$width - $scaledWidth - $padding, $height - $scaledHeight - $padding],
        };

        imagealphablending($image, true);
        imagecopy($image, $overlay, $destX, $destY, 0, 0, $scaledWidth, $scaledHeight);
        imagealphablending($image, false);
        imagedestroy($overlay);
    }

    private function toLatin1ForBuiltInFont(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

        return $converted !== false ? $converted : $text;
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

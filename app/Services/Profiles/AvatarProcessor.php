<?php

namespace App\Services\Profiles;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AvatarProcessor
{
    public function store(UploadedFile $file, User $user): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD is not available for image processing.');
        }

        [$image, $width, $height] = $this->readImage($file->getRealPath(), $file->getMimeType() ?: '');

        $target = (int) config('fotometro.avatar.size', 400);
        $square = $this->squareCrop($image, $width, $height, $target);

        // Fixed filename per user (always re-encoded to .jpg regardless of the
        // source format) so re-uploading overwrites in place and never leaves
        // orphaned files behind.
        $disk = Storage::disk('public');
        $path = "avatars/{$user->id}.jpg";
        $absolutePath = $disk->path($path);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $saved = imagejpeg($square, $absolutePath, (int) config('fotometro.avatar.quality', 85));

        imagedestroy($image);
        imagedestroy($square);

        if (! $saved) {
            throw new RuntimeException('Unable to save the avatar image.');
        }

        return $path;
    }

    private function readImage(string $path, string $mime): array
    {
        $size = getimagesize($path);

        if ($size === false) {
            throw new RuntimeException('The uploaded file is not a readable image.');
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

    private function squareCrop(\GdImage $image, int $width, int $height, int $target): \GdImage
    {
        $side = min($width, $height);
        $srcX = intdiv($width - $side, 2);
        $srcY = intdiv($height - $side, 2);

        $square = imagecreatetruecolor($target, $target);
        imagecopyresampled($square, $image, 0, 0, $srcX, $srcY, $target, $target, $side, $side);

        return $square;
    }
}

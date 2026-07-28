<?php

namespace App\Services\Photos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class PhotoPathGenerator
{
    public function originalPath(UploadedFile $file): string
    {
        return 'photos/originals/'.$this->name($file);
    }

    public function webPath(string $originalPath): string
    {
        return 'photos/web/'.pathinfo($originalPath, PATHINFO_FILENAME).'.'.$this->extension($originalPath);
    }

    public function thumbnailPath(string $originalPath): string
    {
        return 'photos/thumbnails/'.pathinfo($originalPath, PATHINFO_FILENAME).'.'.$this->extension($originalPath);
    }

    private function name(UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');

        return (string) Str::uuid().'-'.Str::random(16).'.'.$extension;
    }

    private function extension(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }
}

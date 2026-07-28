<?php

namespace App\Services\Photos;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class ExifReader
{
    public function read(string $path): array
    {
        if (! function_exists('exif_read_data')) {
            return [];
        }

        try {
            $exif = @exif_read_data($path, null, true);
        } catch (\Throwable $exception) {
            Log::info('Unable to read EXIF metadata.', ['path' => $path, 'error' => $exception->getMessage()]);

            return [];
        }

        if (! is_array($exif)) {
            return [];
        }

        $ifd0 = $exif['IFD0'] ?? [];
        $exifData = $exif['EXIF'] ?? [];
        $gps = $exif['GPS'] ?? [];

        return [
            'taken_at' => $this->date($exifData['DateTimeOriginal'] ?? $ifd0['DateTime'] ?? null),
            'camera_make' => $this->string($ifd0['Make'] ?? null),
            'camera_model' => $this->string($ifd0['Model'] ?? null),
            'lens' => $this->string($exifData['UndefinedTag:0xA434'] ?? $exifData['LensModel'] ?? null),
            'focal_length' => $this->number($exifData['FocalLength'] ?? null),
            'focal_length_35mm' => $this->int($exifData['FocalLengthIn35mmFilm'] ?? null),
            'aperture' => $this->number($exifData['FNumber'] ?? null),
            'shutter_speed' => $this->shutter($exifData['ExposureTime'] ?? null),
            'iso' => $this->int($exifData['ISOSpeedRatings'] ?? $exifData['PhotographicSensitivity'] ?? null),
            'orientation' => $this->int($ifd0['Orientation'] ?? null),
            'latitude' => $this->gps($gps['GPSLatitude'] ?? null, $gps['GPSLatitudeRef'] ?? null),
            'longitude' => $this->gps($gps['GPSLongitude'] ?? null, $gps['GPSLongitudeRef'] ?? null),
            'exif_copyright' => $this->string($ifd0['Copyright'] ?? null),
            'exif_artist' => $this->string($ifd0['Artist'] ?? null),
        ];
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y:m:d H:i:s', trim($value)) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function string(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    private function int(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        if (is_string($value) && str_contains($value, '/')) {
            [$num, $den] = array_map('floatval', explode('/', $value, 2));

            return $den == 0.0 ? null : round($num / $den, 2);
        }

        return null;
    }

    private function shutter(mixed $value): ?string
    {
        return $this->string($value);
    }

    private function gps(mixed $coordinates, mixed $ref): ?float
    {
        if (! is_array($coordinates) || count($coordinates) < 3) {
            return null;
        }

        $degrees = $this->number($coordinates[0]);
        $minutes = $this->number($coordinates[1]);
        $seconds = $this->number($coordinates[2]);

        if ($degrees === null || $minutes === null || $seconds === null) {
            return null;
        }

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if (in_array(strtoupper((string) $ref), ['S', 'W'], true)) {
            $decimal *= -1;
        }

        return round($decimal, 7);
    }
}

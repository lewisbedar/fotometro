<?php

namespace App\Services\Idfm;

use App\Models\Line;
use App\Services\Idfm\Concerns\NormalizesIdfmRecords;

class TraceImporter
{
    use NormalizesIdfmRecords;

    public function import(array $payload): ImportReport
    {
        $report = new ImportReport;

        foreach ($this->records($payload) as $record) {
            $lineExternalId = $this->lineIdentifier($record);
            $line = $lineExternalId === null ? null : Line::query()
                ->get()
                ->first(fn (Line $line) => IdfmIdentifier::line($line->external_id) === $lineExternalId);
            $geometry = $this->geometry($record);

            if (! $line || ! $geometry) {
                $report->ignored();
                continue;
            }

            if (! $this->isValidLineGeometry($geometry)) {
                $report->warn("Invalid GeoJSON trace ignored for line {$lineExternalId}.");
                $report->ignored();
                continue;
            }

            $line->forceFill([
                'path_geojson' => $geometry,
                'source_updated_at' => now(),
            ])->save();

            $report->updated();
        }

        return $report;
    }

    public function isValidLineGeometry(array $geometry): bool
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if ($type === 'LineString') {
            return is_array($coordinates) && count($coordinates) > 1 && collect($coordinates)->every(fn ($coordinate) => $this->validCoordinate($coordinate));
        }

        if ($type === 'MultiLineString') {
            return is_array($coordinates)
                && count($coordinates) > 0
                && collect($coordinates)->every(fn ($line) => is_array($line) && count($line) > 1 && collect($line)->every(fn ($coordinate) => $this->validCoordinate($coordinate)));
        }

        return false;
    }

    private function geometry(array $record): ?array
    {
        $candidate = $this->value($record, ['geojson', 'geometry', 'geo_shape', 'shape', 'path_geojson']);

        if (is_string($candidate)) {
            $candidate = json_decode($candidate, true);
        }

        if (is_array($candidate) && ($candidate['type'] ?? null) === 'Feature') {
            $candidate = $candidate['geometry'] ?? null;
        }

        return is_array($candidate) ? $candidate : null;
    }

    private function validCoordinate(mixed $coordinate): bool
    {
        return is_array($coordinate)
            && count($coordinate) >= 2
            && is_finite((float) $coordinate[0])
            && is_finite((float) $coordinate[1]);
    }
}

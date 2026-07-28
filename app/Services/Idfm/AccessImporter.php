<?php

namespace App\Services\Idfm;

use App\Models\Station;
use App\Models\StationAccess;
use App\Models\StationArea;
use App\Services\Idfm\Concerns\NormalizesIdfmRecords;

class AccessImporter
{
    use NormalizesIdfmRecords;

    public function import(array $accessPayload, array $relationPayload = [], array $options = []): ImportReport
    {
        $report = new ImportReport;
        $seenAccessIds = [];

        foreach ($this->records($accessPayload) as $record) {
            $report->accessRecordsRead++;
            $externalId = (string) $this->value($record, ['access_id', 'id_access', 'external_id', 'AccId', 'accid', 'id']);

            if ($externalId === '') {
                $report->ignored();
                continue;
            }

            $access = StationAccess::query()->where('external_id', $externalId)->first();
            $attributes = [
                'external_id' => $externalId,
                'name' => $this->value($record, ['name', 'access_name', 'nom', 'accname', 'accshortname']),
                'reference' => $this->value($record, ['reference', 'ref', 'accprivatecode']),
                'number' => $this->value($record, ['number', 'access_number', 'accshortname']),
                'latitude' => $this->latitude($record),
                'longitude' => $this->longitude($record),
                'access_type' => $this->value($record, ['access_type', 'type']),
                'street' => $this->value($record, ['street', 'rue', 'adresse']),
                'description' => $this->value($record, ['description', 'accdescription']),
                'wheelchair_accessible' => $this->wheelchair($this->value($record, ['wheelchair_accessible', 'wheelchair', 'accessible_upr'])),
                'is_active' => true,
                'source' => 'idfm',
                'source_payload' => $record,
                'source_updated_at' => now(),
            ];

            if ($access) {
                $access->fill($attributes)->save();
                $report->updated();
            } else {
                $access = StationAccess::query()->create($attributes);
                $report->created();
            }

            $seenAccessIds[] = $externalId;
        }

        foreach ($this->records($relationPayload) as $record) {
            $report->accessRelationsRead++;
            $accessExternalId = (string) $this->value($record, ['access_id', 'id_access', 'station_access_id', 'AccId', 'accid']);
            $zoneExternalId = $this->bareId($this->value($record, ['ZdAId', 'zdaid', 'zone_external_id']));
            $access = StationAccess::query()->where('external_id', $accessExternalId)->first();
            $area = $zoneExternalId === null ? null : StationArea::query()
                ->where('external_id', $zoneExternalId)
                ->with('station')
                ->first();
            $station = $area?->station;

            if (! $access) {
                $report->unknownAccid++;
                $report->ignored();
                continue;
            }

            if (! $station) {
                $report->unknownZdaid++;
                $report->ignored();
                continue;
            }

            $before = $access->stations()->count();
            $access->stations()->syncWithoutDetaching([
                $station->id => ['source' => 'idfm'],
            ]);
            $after = $access->stations()->count();

            if ($after > $before) {
                $report->accessStationRelationsCreated++;
            }

            if ($after > 1) {
                $report->accessesLinkedToMultipleStations++;
            }
        }

        if (($options['deactivate_absent'] ?? true) && $seenAccessIds !== []) {
            $count = StationAccess::query()
                ->where('source', 'idfm')
                ->whereNotIn('external_id', $seenAccessIds)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $report->deactivated($count);
        }

        return $report;
    }

    private function wheelchair(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->boolValue($value);
    }

    private function latitude(array $record): ?float
    {
        $direct = $this->coordinate($this->value($record, ['latitude', 'lat', 'access_lat']));

        if ($direct !== null) {
            return $direct;
        }

        $point = (string) $this->value($record, ['accgeopoint', 'geo_point_2d', 'pointgeo']);

        if (preg_match('/(-?\d+(?:[\.,]\d+)?)\s*,\s*(-?\d+(?:[\.,]\d+)?)/', $point, $matches) !== 1) {
            return null;
        }

        return $this->coordinate($matches[1]);
    }

    private function longitude(array $record): ?float
    {
        $direct = $this->coordinate($this->value($record, ['longitude', 'lon', 'access_lon']));

        if ($direct !== null) {
            return $direct;
        }

        $point = (string) $this->value($record, ['accgeopoint', 'geo_point_2d', 'pointgeo']);

        if (preg_match('/(-?\d+(?:[\.,]\d+)?)\s*,\s*(-?\d+(?:[\.,]\d+)?)/', $point, $matches) !== 1) {
            return null;
        }

        return $this->coordinate($matches[2]);
    }

    private function bareId(mixed $value): ?string
    {
        $identifier = trim((string) $value, " \t\n\r\0\x0B\"'");
        $identifier = preg_replace('/^IDFM\s*:\s*/i', '', $identifier) ?? $identifier;

        return $identifier === '' ? null : $identifier;
    }
}

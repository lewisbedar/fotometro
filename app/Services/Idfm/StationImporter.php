<?php

namespace App\Services\Idfm;

use App\Enums\CoverageStatus;
use App\Models\Line;
use App\Models\Station;
use App\Models\StationArea;
use App\Models\StationStop;
use App\Services\Idfm\Concerns\NormalizesIdfmRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StationImporter
{
    use NormalizesIdfmRecords;

    public function import(array $payload, array $options = []): ImportReport
    {
        $report = new ImportReport;
        $reportRecords = $options['report_records'] ?? true;
        $lineLookup = $this->lineLookup();
        $areaRecords = $this->areaRecords($options['stop_areas'] ?? []);
        $stopToArea = $this->stopToAreaMap($options['stop_relations'] ?? []);
        $seenStationIds = [];
        $seenStopIds = [];
        $seenAreaIds = [];
        $lineIds = [];

        $report->phase = 'stations';
        $report->databaseDriver = DB::connection()->getDriverName();
        $report->databaseName = (string) DB::connection()->getDatabaseName();
        $report->linesLoadedFromDatabase = $lineLookup['count'];
        $report->lineLookupKeys = count($lineLookup['by_external_id']) + count($lineLookup['by_code']);
        $report->databaseLineIds = array_slice(array_values(array_unique(array_keys($lineLookup['by_external_id']))), 0, 5);
        $report->csvHeaders = $payload['_headers'] ?? [];

        if ($lineLookup['count'] === 0) {
            $report->error('Cannot import stations: no metro lines are available in the database. Run --only=lines first.');

            return $report;
        }

        $this->records($payload)
            ->each(fn () => $reportRecords ? $report->rawRecords() : null)
            ->filter(function (array $record) use ($report, $reportRecords): bool {
                $isMetro = $this->isMetro($record);

                if (! $isMetro) {
                    $report->ignoredReason('non_metro');
                } elseif ($reportRecords) {
                    $report->retainedRecords();
                }

                return $isMetro;
            })
            ->each(function (array $record) use ($report, &$seenStationIds, &$seenStopIds, &$seenAreaIds, &$lineIds, $lineLookup, $areaRecords, $stopToArea): void {
                $lineExternalId = $this->lineIdentifier($record);
                $lineIds[] = $lineExternalId;
                $stopExternalId = $this->stationIdentifier($record);
                $name = trim((string) $this->value($record, ['stop_name', 'name', 'station_name', 'StopName']));

                if ($lineExternalId === null) {
                    $report->ignoredReason('missing_line_id');
                    return;
                }

                if ($stopExternalId === null) {
                    $report->ignoredReason('missing_station_id');
                    return;
                }

                if ($name === '') {
                    $report->ignoredReason('missing_station_name');
                    return;
                }

                $line = $lineLookup['by_external_id'][$lineExternalId]
                    ?? $lineLookup['by_code'][$this->lineCode($record)]
                    ?? null;

                if (! $line) {
                    $report->unmatchedLineRecords++;
                    $report->unmatchedLineIds[] = $lineExternalId;
                    $report->ignoredReason('unmatched_line');
                    return;
                }

                $zoneExternalId = $stopToArea[$this->bareId($stopExternalId)] ?? null;
                $areaRecord = $zoneExternalId ? ($areaRecords[$zoneExternalId] ?? null) : null;
                $station = null;
                $area = null;

                if ($zoneExternalId && $areaRecord) {
                    $area = $this->upsertArea($areaRecord, $record);
                    $station = $this->upsertPublicStation($area, $areaRecord, $record, $report);
                    $seenStationIds[$station->external_id] = true;
                    $seenAreaIds[$area->external_id] = true;
                } else {
                    $report->ignoredReason('unresolved_stop_area');
                }

                $stop = $this->upsertStop($record, $stopExternalId, $zoneExternalId, $area, $station);
                $seenStopIds[$stop->external_id] = true;

                if (! $station) {
                    return;
                }

                $report->matchedLineRecords++;
                $exists = $line->stations()->whereKey($station->id)->exists();
                $line->stations()->syncWithoutDetaching([
                    $station->id => [
                        'position' => 0,
                        'branch' => null,
                        'is_terminus' => false,
                    ],
                ]);

                if ($exists) {
                    $report->stationLineRelationsUpdated++;
                } else {
                    $report->stationLineRelationsCreated++;
                }
            });

        $report->uniqueStationIds = count($seenStationIds);
        $report->uniqueStationStops = count($seenStopIds);
        $report->uniqueStationAreas = count($seenAreaIds);
        $report->unresolvedStops = $report->ignoredReasons['unresolved_stop_area'] ?? 0;
        $report->sourceLineIds = array_slice(array_values(array_unique(array_filter($lineIds))), 0, 5);
        $report->unmatchedLineIds = array_slice(array_values(array_unique($report->unmatchedLineIds)), 0, 5);

        if ($report->uniqueStationIds > 0 && $report->uniqueStationStops > 0 && $report->uniqueStationIds / $report->uniqueStationStops > 0.8) {
            $report->warn('Coherence warning: public Stations count is close to StationStop count. Check stop-area grouping.');
        }

        if (($options['deactivate_absent'] ?? true) && $seenStationIds !== []) {
            Station::query()
                ->where('source', 'idfm')
                ->whereNotIn('external_id', array_keys($seenStationIds))
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        return $report;
    }

    private function areaRecords(array $payload): array
    {
        $areas = [];

        foreach ($this->records($payload) as $record) {
            $externalId = $this->bareId($this->value($record, ['zdaid', 'ZdAId', 'id', 'external_id']));

            if ($externalId !== null) {
                $areas[$externalId] = $record;
            }
        }

        return $areas;
    }

    private function stopToAreaMap(array $payload): array
    {
        $map = [];

        foreach ($this->records($payload) as $record) {
            $stopId = $this->bareId($this->value($record, ['arrid', 'ArrId', 'stop_id', 'id_stop']));
            $areaId = $this->bareId($this->value($record, ['zdaid', 'ZdAId', 'zone_external_id']));

            if ($stopId !== null && $areaId !== null) {
                $map[$stopId] = $areaId;
            }
        }

        return $map;
    }

    private function upsertArea(array $areaRecord, array $stopRecord): StationArea
    {
        $externalId = $this->bareId($this->value($areaRecord, ['zdaid', 'ZdAId', 'id', 'external_id']));
        $area = StationArea::query()->where('external_id', $externalId)->first();
        $latitude = $this->coordinate($this->value($stopRecord, ['stop_lat', 'latitude', 'lat']));
        $longitude = $this->coordinate($this->value($stopRecord, ['stop_lon', 'longitude', 'lon']));

        $attributes = [
            'external_id' => $externalId,
            'public_external_id' => $this->bareId($this->value($areaRecord, ['zdcid', 'ZdcId', 'pdeid', 'PdeId'])) ?? $externalId,
            'name' => (string) ($this->value($areaRecord, ['zdaname', 'name', 'stop_name']) ?: $this->value($stopRecord, ['stop_name'])),
            'latitude' => $area?->latitude ?? $latitude,
            'longitude' => $area?->longitude ?? $longitude,
            'city' => $this->value($areaRecord, ['zdatown', 'nom_commune', 'city']) ?: $this->value($stopRecord, ['nom_commune']),
            'postal_code' => $this->value($areaRecord, ['zdapostalregion', 'postal_code']),
            'area_type' => $this->value($areaRecord, ['zdatype', 'type']),
            'source' => 'idfm',
            'source_payload' => $areaRecord,
            'source_updated_at' => now(),
            'is_active' => true,
        ];

        if ($area) {
            $area->fill($attributes)->save();
            return $area;
        }

        return StationArea::query()->create($attributes);
    }

    private function upsertPublicStation(StationArea $area, array $areaRecord, array $stopRecord, ImportReport $report): Station
    {
        $publicKey = $area->public_external_id ?: $area->external_id;
        $externalId = "IDFM:PUBLIC:{$publicKey}";
        $station = Station::query()->where('external_id', $externalId)->first();
        $name = (string) ($this->value($areaRecord, ['zdaname', 'name']) ?: $this->value($stopRecord, ['stop_name']));

        $attributes = [
            'external_id' => $externalId,
            'name' => $name,
            'slug' => $station?->slug ?? $this->uniqueSlug(Str::slug($name)),
            'latitude' => $station?->latitude ?? $area->latitude ?? $this->coordinate($this->value($stopRecord, ['stop_lat', 'latitude', 'lat'])),
            'longitude' => $station?->longitude ?? $area->longitude ?? $this->coordinate($this->value($stopRecord, ['stop_lon', 'longitude', 'lon'])),
            'city' => $area->city ?: $this->value($stopRecord, ['nom_commune']),
            'postal_code' => $area->postal_code,
            'is_active' => true,
            'source' => 'idfm',
            'source_payload' => $areaRecord,
            'source_updated_at' => now(),
        ];

        if ($station) {
            $station->fill($attributes)->save();
            $report->updated();
        } else {
            $station = Station::query()->create([
                ...$attributes,
                'coverage_status' => CoverageStatus::NotStarted,
            ]);
            $report->created();
        }

        if ($area->station_id !== $station->id) {
            $area->forceFill(['station_id' => $station->id])->save();
        }

        return $station;
    }

    private function upsertStop(array $record, string $externalId, ?string $zoneExternalId, ?StationArea $area, ?Station $station): StationStop
    {
        $stop = StationStop::query()->where('external_id', $externalId)->first();
        $attributes = [
            'station_id' => $station?->id,
            'station_area_id' => $area?->id,
            'external_id' => $externalId,
            'zone_external_id' => $zoneExternalId,
            'name' => (string) $this->value($record, ['stop_name', 'name', 'station_name']),
            'latitude' => $this->coordinate($this->value($record, ['stop_lat', 'latitude', 'lat'])),
            'longitude' => $this->coordinate($this->value($record, ['stop_lon', 'longitude', 'lon'])),
            'source' => 'idfm',
            'source_payload' => $record,
            'source_updated_at' => now(),
            'is_active' => true,
        ];

        if ($stop) {
            $stop->fill($attributes)->save();
            return $stop;
        }

        return StationStop::query()->create($attributes);
    }

    private function isMetro(array $record): bool
    {
        $mode = Str::lower(Str::ascii((string) ($this->value($record, ['mode', 'route_type', 'transportmode', 'type']) ?? 'metro')));

        return str_contains($mode, 'metro') || $mode === '1';
    }

    private function lineLookup(): array
    {
        $lines = Line::query()->where('is_active', true)->get();
        $byExternalId = [];
        $byCode = [];

        foreach ($lines as $line) {
            $externalId = IdfmIdentifier::line($line->external_id);

            if ($externalId !== null) {
                $byExternalId[$externalId] = $line;
            }

            $byCode[Str::upper((string) $line->code)] = $line;
        }

        return [
            'count' => $lines->count(),
            'by_external_id' => $byExternalId,
            'by_code' => $byCode,
        ];
    }

    private function lineCode(array $record): string
    {
        return Str::upper(trim((string) $this->value($record, ['shortname', 'route_short_name', 'code', 'line_code'])));
    }

    private function bareId(mixed $value): ?string
    {
        $identifier = trim((string) $value, " \t\n\r\0\x0B\"'");
        $identifier = preg_replace('/^IDFM\s*:\s*/i', '', $identifier) ?? $identifier;

        return $identifier === '' ? null : $identifier;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base ?: 'station';
        $candidate = $slug;
        $index = 2;

        while (Station::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$index}";
            $index++;
        }

        return $candidate;
    }
}

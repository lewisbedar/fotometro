<?php

namespace App\Services\Idfm;

use App\Models\Line;
use App\Models\LineStationSequence;
use App\Models\Station;
use App\Models\StationStop;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ZipArchive;

class GtfsImporter
{
    public function import(array $archive, array $options = []): ImportReport
    {
        $report = new ImportReport(phase: 'gtfs');
        $path = $archive['path'] ?? null;

        if (! is_string($path) || ! is_file($path)) {
            $report->error('Cannot import GTFS order: archive file is missing.');

            return $report;
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            $report->error("Cannot open GTFS archive {$path}.");

            return $report;
        }

        try {
            $lines = $this->lineLookup();
            $routes = $this->metroRoutes($zip, $lines, $report);
            $trips = $this->tripsByRoute($zip, $routes, $report);
            $sequences = $this->stationSequences($zip, $trips, $report);
            $this->applySequences($sequences, $lines, $report);
        } finally {
            $zip->close();
        }

        return $report;
    }

    private function lineLookup(): array
    {
        return Line::query()
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (Line $line) => [IdfmIdentifier::line($line->external_id) => $line])
            ->filter()
            ->all();
    }

    private function metroRoutes(ZipArchive $zip, array $lines, ImportReport $report): array
    {
        $routes = [];

        foreach ($this->csvRows($zip, 'routes.txt') as $row) {
            $report->gtfsRoutesRead++;
            $routeId = IdfmIdentifier::line($row['route_id'] ?? null);

            if ($routeId !== null && isset($lines[$routeId])) {
                $routes[$row['route_id']] = $routeId;
            }
        }

        return $routes;
    }

    private function tripsByRoute(ZipArchive $zip, array $routes, ImportReport $report): array
    {
        $trips = [];

        foreach ($this->csvRows($zip, 'trips.txt') as $row) {
            $report->gtfsTripsRead++;
            $routeId = $row['route_id'] ?? null;

            if (! isset($routes[$routeId])) {
                continue;
            }

            $tripId = (string) ($row['trip_id'] ?? '');

            if ($tripId === '') {
                continue;
            }

            $trips[$tripId] = [
                'line_key' => $routes[$routeId],
                'direction' => (string) ($row['direction_id'] ?? '0'),
            ];
        }

        return $trips;
    }

    private function stationSequences(ZipArchive $zip, array $trips, ImportReport $report): array
    {
        $stopLookup = $this->stopLookup();
        $patterns = [];
        $currentTripId = null;
        $currentTimes = [];

        foreach ($this->stopTimeRows($zip, $trips) as $row) {
            $report->gtfsStopTimesRead++;
            $tripId = $row['trip_id'];

            if ($currentTripId !== null && $tripId !== $currentTripId) {
                $this->recordPattern($patterns, $trips, $currentTripId, $currentTimes);
                $currentTimes = [];
            }

            $currentTripId = $tripId;

            $stopKey = IdfmIdentifier::stop($row['stop_id'] ?? null);
            $stationId = $stopKey === null ? null : ($stopLookup[$stopKey] ?? null);

            if (! $stationId) {
                $report->gtfsUnresolvedStops++;
                continue;
            }

            $currentTimes[] = [
                'sequence' => (int) ($row['stop_sequence'] ?? 0),
                'station_id' => $stationId,
            ];
        }

        if ($currentTripId !== null) {
            $this->recordPattern($patterns, $trips, $currentTripId, $currentTimes);
        }

        return $patterns;
    }

    private function recordPattern(array &$patterns, array $trips, string $tripId, array $times): void
    {
        if (! isset($trips[$tripId])) {
            return;
        }

        usort($times, fn (array $a, array $b) => $a['sequence'] <=> $b['sequence']);
        $stationIds = $this->deduplicateStations(array_column($times, 'station_id'));

        if (count($stationIds) < 2) {
            return;
        }

        $lineKey = $trips[$tripId]['line_key'];
        $signature = implode('-', $stationIds);
        $reverseSignature = implode('-', array_reverse($stationIds));
        $canonical = strcmp($signature, $reverseSignature) <= 0 ? $signature : $reverseSignature;
        $patterns[$lineKey][$canonical] = [
            'stations' => strcmp($signature, $reverseSignature) <= 0 ? $stationIds : array_reverse($stationIds),
            'trip' => $tripId,
            'direction' => $trips[$tripId]['direction'],
        ];
    }

    private function applySequences(array $patterns, array $lines, ImportReport $report): void
    {
        foreach ($lines as $lineKey => $line) {
            $linePatterns = collect($patterns[$lineKey] ?? [])
                ->sortByDesc(fn (array $pattern) => count($pattern['stations']))
                ->values();

            if ($linePatterns->isEmpty()) {
                $report->gtfsLinesWithoutSequence[] = $line->code;
                continue;
            }

            $selected = $this->representativePatterns($linePatterns);
            $stationLookup = $this->stationLookup($selected);
            $selected = $selected
                ->map(fn (array $pattern) => $this->orientPattern($line, $pattern, $stationLookup, $report))
                ->values();

            $report->gtfsUniqueSequences += $linePatterns->count();
            $selected->count() > 1 ? $report->gtfsBranchedLines++ : $report->gtfsSimpleLines++;

            $topologyType = $this->topologyType($line, $selected);
            $sharedStationIds = $this->sharedStationIds($selected);
            $commonPrefixLength = $selected->count() > 1 ? $this->commonPrefixLength($selected->pluck('stations')->all()) : 0;
            $commonSuffixLength = $selected->count() > 1 ? $this->commonSuffixLength($selected->pluck('stations')->all()) : 0;

            if ($sharedStationIds !== []) {
                $report->gtfsTrunksDetected++;
            }

            if ($selected->count() > 1) {
                $report->gtfsBranchesDetected += $selected->count();
            }

            if (str_contains($topologyType, 'loop')) {
                $report->gtfsLoopsDetected++;
            }

            $line->stationSequences()->delete();
            $presence = [];

            foreach ($selected as $branchIndex => $pattern) {
                $sequenceKey = $selected->count() === 1 ? 'main' : 'branch-'.chr(97 + (int) $branchIndex);
                $branchKey = $selected->count() === 1 ? null : $sequenceKey;
                $stations = array_values($pattern['stations']);
                $length = count($stations);

                foreach ($stations as $position => $stationId) {
                    $isTerminus = $position === 0 || $position === $length - 1;
                    $isBranchStart = $selected->count() > 1 && (
                        ($commonPrefixLength > 0 && $position === $commonPrefixLength)
                        || ($commonPrefixLength === 0 && $position === 0)
                    );
                    $isBranchEnd = $selected->count() > 1 && $isTerminus;

                    LineStationSequence::query()->create([
                        'line_id' => $line->id,
                        'station_id' => $stationId,
                        'sequence_key' => $sequenceKey,
                        'branch_key' => $branchKey,
                        'direction_key' => $pattern['direction'],
                        'position' => $position + 1,
                        'is_terminus' => $isTerminus,
                        'is_branch_start' => $isBranchStart,
                        'is_branch_end' => $isBranchEnd,
                        'is_loop_entry' => str_contains($topologyType, 'loop') && $position === 0,
                        'is_loop_exit' => str_contains($topologyType, 'loop') && $position === $length - 1,
                        'is_shared_trunk' => in_array($stationId, $sharedStationIds, true),
                        'source' => 'gtfs',
                        'gtfs_pattern' => $pattern['trip'],
                    ]);
                    $report->gtfsTopologySequencesCreated++;

                    $presence[$stationId][] = [
                        'branch_index' => $branchIndex,
                        'position' => $position + 1,
                        'length' => $length,
                        'is_shared' => in_array($stationId, $sharedStationIds, true),
                    ];
                }
            }

            foreach ($presence as $stationId => $items) {
                $first = collect($items)->sortBy('position')->first();
                $branch = $selected->count() === 1
                    ? null
                    : ($first['is_shared'] ? 'main' : 'branch-'.chr(97 + (int) $first['branch_index']));
                $isTerminus = collect($items)->contains(fn (array $item) => $item['position'] === 1 || $item['position'] === $item['length']);

                $line->stations()->updateExistingPivot($stationId, [
                    'position' => $first['position'],
                    'branch' => $branch,
                    'is_terminus' => $isTerminus,
                ]);

                $report->gtfsRelationsUpdated++;
                $report->gtfsOrderedStations++;
            }

            $terminus = $selected
                ->flatMap(fn (array $pattern) => [reset($pattern['stations']), end($pattern['stations'])])
                ->unique()
                ->values();

            $report->gtfsLineSummaries[] = [
                'line' => $line->code,
                'type' => $topologyType,
                'orientation_start' => $this->stationName($selected->first()['stations'][0] ?? null, $stationLookup),
                'terminus' => $terminus->map(fn ($stationId) => $this->stationName($stationId, $stationLookup))->filter()->values()->all(),
                'stations' => count($presence),
                'branches' => $selected->count(),
                'lengths' => $selected->map(fn (array $pattern) => count($pattern['stations']))->all(),
                'shared_trunk_stations' => count($sharedStationIds),
            ];
        }
    }

    private function representativePatterns(Collection $patterns): Collection
    {
        $selected = collect();

        foreach ($patterns as $pattern) {
            $stations = $pattern['stations'];
            $isSubset = $selected->contains(fn (array $selectedPattern) => empty(array_diff($stations, $selectedPattern['stations'])));

            if (! $isSubset) {
                $selected->push($pattern);
            }
        }

        return $selected->take(4)->values();
    }

    private function orientPattern(Line $line, array $pattern, array $stationLookup, ImportReport $report): array
    {
        $rule = config("fotometro.line_orientation.{$line->code}");
        $stations = array_values($pattern['stations']);
        $reverse = false;

        if (is_array($rule)) {
            $startIndex = $this->firstConfiguredStationIndex($stations, $stationLookup, $rule, 'start', $line, $report);
            $endIndex = $this->firstConfiguredStationIndex($stations, $stationLookup, $rule, 'end', $line, $report);

            if ($startIndex !== null && $endIndex !== null) {
                $report->gtfsManualOrientationsUsed++;
                $reverse = $startIndex > $endIndex;
            } elseif ($startIndex !== null) {
                $report->gtfsManualOrientationsUsed++;
                $reverse = $startIndex > (count($stations) / 2);
            } elseif ($endIndex !== null) {
                $report->gtfsManualOrientationsUsed++;
                $reverse = $endIndex < (count($stations) / 2);
            } else {
                $report->gtfsUnresolvedOrientationRules++;
                $report->warn("GTFS orientation rule for line {$line->code} did not match selected stations.");
                $reverse = $this->shouldReverseByCoordinates($stations, $stationLookup);
            }
        } else {
            $reverse = $this->shouldReverseByCoordinates($stations, $stationLookup);
        }

        if ($reverse) {
            $pattern['stations'] = array_reverse($stations);
            $report->gtfsOrientationsReversed++;
            $report->gtfsReversedLines[] = $line->code;
        }

        return $pattern;
    }

    private function firstConfiguredStationIndex(array $stationIds, array $stationLookup, array $rule, string $side, Line $line, ImportReport $report): ?int
    {
        $externalIds = $this->configuredExternalIds($rule, $side);

        if ($externalIds !== []) {
            foreach ($stationIds as $index => $stationId) {
                $station = $stationLookup[$stationId] ?? null;

                if ($station && in_array($station->external_id, $externalIds, true)) {
                    return $index;
                }
            }
        }

        $names = $this->configuredNames($rule, $side);
        $normalizedNames = collect($names)
            ->map(fn (string $name) => $this->normalizeName($name))
            ->filter()
            ->values();

        foreach ($stationIds as $index => $stationId) {
            $station = $stationLookup[$stationId] ?? null;

            if (! $station) {
                continue;
            }

            $name = $this->normalizeName($station->name);

            if ($normalizedNames->contains($name)) {
                return $index;
            }
        }

        if ($externalIds !== [] || $names !== []) {
            $report->warn(sprintf(
                'GTFS orientation %s rule for line %s did not match any selected station. external_ids=%s names=%s',
                $side,
                $line->code,
                json_encode($externalIds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                json_encode($names, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ));
        }

        return null;
    }

    private function configuredExternalIds(array $rule, string $side): array
    {
        $keys = $side === 'start'
            ? ['start_external_id', 'start_external_ids']
            : ['end_external_id', 'end_external_ids'];

        return $this->configuredValues($rule, $keys);
    }

    private function configuredNames(array $rule, string $side): array
    {
        $keys = $side === 'start'
            ? ['start_name', 'start_names', 'start']
            : ['end_name', 'end_names', 'ends'];

        return $this->configuredValues($rule, $keys);
    }

    private function configuredValues(array $rule, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $value = $rule[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item) && $item !== '') {
                        $values[] = $item;
                    }
                }
            }
        }

        return array_values(array_unique($values));
    }

    private function shouldReverseByCoordinates(array $stationIds, array $stationLookup): bool
    {
        $first = $stationLookup[$stationIds[0] ?? null] ?? null;
        $last = $stationLookup[$stationIds[count($stationIds) - 1] ?? null] ?? null;

        if (! $first || ! $last || $first->latitude === null || $first->longitude === null || $last->latitude === null || $last->longitude === null) {
            return false;
        }

        $deltaLongitude = (float) $last->longitude - (float) $first->longitude;
        $deltaLatitude = (float) $last->latitude - (float) $first->latitude;

        if (abs($deltaLongitude) >= abs($deltaLatitude)) {
            return $deltaLongitude < 0;
        }

        return $deltaLatitude > 0;
    }

    private function topologyType(Line $line, Collection $patterns): string
    {
        $configured = config("fotometro.line_orientation.{$line->code}.type");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $patterns->count() > 1 ? 'branched' : 'simple';
    }

    private function sharedStationIds(Collection $patterns): array
    {
        if ($patterns->count() < 2) {
            return [];
        }

        $counts = [];

        foreach ($patterns as $pattern) {
            foreach (array_unique($pattern['stations']) as $stationId) {
                $counts[$stationId] = ($counts[$stationId] ?? 0) + 1;
            }
        }

        return collect($counts)
            ->filter(fn (int $count) => $count === $patterns->count())
            ->keys()
            ->map(fn ($stationId) => (int) $stationId)
            ->values()
            ->all();
    }

    private function commonPrefixLength(array $sequences): int
    {
        $length = 0;

        while (true) {
            $stationId = $sequences[0][$length] ?? null;

            if ($stationId === null) {
                return $length;
            }

            foreach ($sequences as $sequence) {
                if (($sequence[$length] ?? null) !== $stationId) {
                    return $length;
                }
            }

            $length++;
        }
    }

    private function commonSuffixLength(array $sequences): int
    {
        $length = 0;

        while (true) {
            $stationId = $sequences[0][count($sequences[0]) - 1 - $length] ?? null;

            if ($stationId === null) {
                return $length;
            }

            foreach ($sequences as $sequence) {
                if (($sequence[count($sequence) - 1 - $length] ?? null) !== $stationId) {
                    return $length;
                }
            }

            $length++;
        }
    }

    private function stationLookup(Collection $patterns): array
    {
        $ids = $patterns
            ->flatMap(fn (array $pattern) => $pattern['stations'])
            ->unique()
            ->values();

        return Station::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->all();
    }

    private function stationName(?int $stationId, array $stationLookup): ?string
    {
        return $stationId === null ? null : ($stationLookup[$stationId]->name ?? null);
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)
            ->ascii()
            ->lower()
            ->replace(['—', '–', '’'], ['-', '-', "'"])
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function stopLookup(): array
    {
        $lookup = [];

        StationStop::query()->whereNotNull('station_id')->select(['external_id', 'station_id'])->chunk(1000, function ($stops) use (&$lookup): void {
            foreach ($stops as $stop) {
                $key = IdfmIdentifier::stop($stop->external_id);

                if ($key !== null) {
                    $lookup[$key] = (int) $stop->station_id;
                }
            }
        });

        return $lookup;
    }

    private function deduplicateStations(array $stationIds): array
    {
        $result = [];

        foreach ($stationIds as $stationId) {
            if (end($result) === $stationId) {
                continue;
            }

            $result[] = $stationId;
        }

        return $result;
    }

    private function csvRows(ZipArchive $zip, string $file): \Generator
    {
        $stream = $zip->getStream($file);

        if ($stream === false) {
            return;
        }

        $headers = null;

        while (($row = fgetcsv($stream)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn (string $header) => ltrim($header, "\xEF\xBB\xBF"), $row);
                continue;
            }

            if (count($row) === count($headers)) {
                yield array_combine($headers, $row);
            }
        }

        fclose($stream);
    }

    private function stopTimeRows(ZipArchive $zip, array $trips): \Generator
    {
        $stream = $zip->getStream('stop_times.txt');

        if ($stream === false) {
            return;
        }

        $header = fgets($stream);

        if ($header === false) {
            fclose($stream);
            return;
        }

        $headers = array_map(fn (string $header) => ltrim(trim($header), "\xEF\xBB\xBF"), explode(',', trim($header)));
        $tripIndex = array_search('trip_id', $headers, true);
        $stopIndex = array_search('stop_id', $headers, true);
        $sequenceIndex = array_search('stop_sequence', $headers, true);

        if ($tripIndex === false || $stopIndex === false || $sequenceIndex === false) {
            fclose($stream);
            return;
        }

        while (($line = fgets($stream)) !== false) {
            $columns = explode(',', trim($line));
            $tripId = $columns[$tripIndex] ?? '';

            if (! isset($trips[$tripId])) {
                continue;
            }

            yield [
                'trip_id' => $tripId,
                'stop_id' => $columns[$stopIndex] ?? '',
                'stop_sequence' => $columns[$sequenceIndex] ?? '0',
            ];
        }

        fclose($stream);
    }
}

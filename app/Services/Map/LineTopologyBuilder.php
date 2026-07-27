<?php

namespace App\Services\Map;

use App\Enums\CoverageStatus;
use App\Models\Line;
use App\Models\LineStationSequence;
use App\Models\Station;
use Illuminate\Support\Collection;

class LineTopologyBuilder
{
    public function __construct(private readonly LineDiagramLayout $layout) {}

    public function build(Line $line): array
    {
        $sequences = $line->relationLoaded('stationSequences')
            ? $line->stationSequences
            : $line->stationSequences()->with(['station.lines'])->get();

        if ($sequences->isEmpty()) {
            return $this->fallbackTopology($line);
        }

        $groups = $sequences
            ->sortBy([['sequence_key', 'asc'], ['position', 'asc']])
            ->groupBy('sequence_key')
            ->map(fn (Collection $items, string $key) => $this->sequencePayload($key, $items, $line))
            ->values();

        $type = $this->configuredType($line, $groups);
        $sharedIds = $sequences
            ->where('is_shared_trunk', true)
            ->pluck('station_id')
            ->unique()
            ->values();

        $trunk = $sharedIds->isEmpty()
            ? []
            : $this->stationsInFirstSequenceOrder($sharedIds, $groups->first()['stations'] ?? []);

        $start = $groups->first()['stations'][0] ?? null;
        $termini = $groups
            ->flatMap(fn (array $sequence) => collect($sequence['stations'])->where('is_terminus', true))
            ->reject(fn (array $station) => $start !== null && $station['id'] === $start['id'])
            ->unique('id')
            ->values()
            ->all();

        $topology = [
            'type' => $type,
            'orientation' => [
                'start' => $start,
                'ends' => $termini,
            ],
            'trunk' => $trunk,
            'branches' => $groups->all(),
            'main' => $groups->first()['stations'] ?? [],
            'loop' => str_contains($type, 'loop') ? $groups->skip(1)->flatMap(fn (array $sequence) => $sequence['stations'])->values()->all() : [],
        ];

        return [
            ...$topology,
            'layout' => $this->layout->build($line, $topology),
        ];
    }

    private function sequencePayload(string $key, Collection $items, Line $line): array
    {
        return [
            'key' => $key,
            'label' => $key === 'main' ? 'Sequence principale' : ucfirst(str_replace('-', ' ', $key)),
            'branch_key' => $items->first()->branch_key,
            'direction_key' => $items->first()->direction_key,
            'stations' => $items
                ->sortBy('position')
                ->filter(fn (LineStationSequence $sequence) => $sequence->station !== null)
                ->map(fn (LineStationSequence $sequence) => $this->stationPayload($sequence->station, $line, $sequence))
                ->values()
                ->all(),
        ];
    }

    private function fallbackTopology(Line $line): array
    {
        $stations = $line->relationLoaded('stations') ? $line->stations : collect();
        $payload = $stations
            ->sortBy(fn (Station $station) => (int) $station->pivot->position)
            ->map(fn (Station $station) => $this->stationPayload($station, $line))
            ->values()
            ->all();

        $topology = [
            'type' => $this->configuredType($line, collect([$payload])),
            'orientation' => [
                'start' => $payload[0] ?? null,
                'ends' => collect($payload)->where('is_terminus', true)->values()->all(),
            ],
            'trunk' => $payload,
            'branches' => [[
                'key' => 'main',
                'label' => 'Sequence principale',
                'branch_key' => null,
                'direction_key' => null,
                'stations' => $payload,
            ]],
            'main' => $payload,
            'loop' => [],
        ];

        return [
            ...$topology,
            'layout' => $this->layout->build($line, $topology),
        ];
    }

    private function configuredType(Line $line, Collection $groups): string
    {
        $configured = config("fotometro.line_orientation.{$line->code}.type");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $groups->count() > 1 ? 'branched' : 'simple';
    }

    private function stationsInFirstSequenceOrder(Collection $sharedIds, array $firstSequence): array
    {
        return collect($firstSequence)
            ->filter(fn (array $station) => $sharedIds->contains($station['id']))
            ->values()
            ->all();
    }

    private function stationPayload(Station $station, Line $line, ?LineStationSequence $sequence = null): array
    {
        $status = $station->coverage_status instanceof CoverageStatus
            ? $station->coverage_status
            : CoverageStatus::from($station->coverage_status);

        $connections = $station->lines
            ->reject(fn ($candidate) => $candidate->id === $line->id)
            ->map(fn ($candidate) => [
                'id' => $candidate->id,
                'external_id' => $candidate->external_id,
                'code' => $candidate->code,
                'name' => $candidate->name,
                'slug' => $candidate->slug,
                'color' => $candidate->color,
                'text_color' => $candidate->text_color,
            ])
            ->values()
            ->all();

        return [
            'id' => $station->id,
            'external_id' => $station->external_id,
            'name' => $station->name,
            'slug' => $station->slug,
            'latitude' => $station->latitude === null ? null : (float) $station->latitude,
            'longitude' => $station->longitude === null ? null : (float) $station->longitude,
            'coordinates' => $station->latitude === null || $station->longitude === null
                ? null
                : [(float) $station->longitude, (float) $station->latitude],
            'position' => $sequence?->position ?? (int) ($station->pivot->position ?? 0),
            'sequence_key' => $sequence?->sequence_key,
            'branch' => $sequence?->branch_key ?? ($station->pivot->branch ?? null),
            'is_terminus' => $sequence?->is_terminus ?? (bool) ($station->pivot->is_terminus ?? false),
            'is_branch_start' => $sequence?->is_branch_start ?? false,
            'is_branch_end' => $sequence?->is_branch_end ?? false,
            'is_loop_entry' => $sequence?->is_loop_entry ?? false,
            'is_loop_exit' => $sequence?->is_loop_exit ?? false,
            'is_shared_trunk' => $sequence?->is_shared_trunk ?? false,
            'access_count' => $station->accesses_count,
            'coverage_status' => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->color(),
            ],
            'connections' => $connections,
            'correspondances' => $connections,
        ];
    }
}

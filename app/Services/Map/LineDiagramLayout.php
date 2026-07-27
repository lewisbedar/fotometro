<?php

namespace App\Services\Map;

use App\Models\Line;
use Illuminate\Support\Collection;

class LineDiagramLayout
{
    private const MARGIN_X = 96;
    private const SIMPLE_AXIS_Y = 112;
    private const LABEL_GAP = 46;

    public function build(Line $line, array $topology): array
    {
        if (is_array(config("line_diagrams.manual.{$line->code}"))) {
            return $this->finalizeLayout($this->layoutManualFromReference($line, $topology));
        }

        $layout = match ((string) $line->code) {
            '7' => $this->layoutLine7($line, $topology),
            '13' => $this->layoutLine13($line, $topology),
            default => ($topology['type'] ?? 'simple') === 'branched'
                ? $this->layoutBranchedLine($line, $topology)
                : $this->layoutSimpleLine($line, $topology),
        };

        return $this->finalizeLayout($layout);
    }

    private function layoutSimpleLine(Line $line, array $topology): array
    {
        $stations = collect($topology['main'] ?: ($topology['branches'][0]['stations'] ?? []))->values();
        $count = max(1, $stations->count());
        $spacing = $this->spacing($count);
        $width = $this->width($count, $spacing);
        $axisY = self::SIMPLE_AXIS_Y;

        return [
            'type' => 'simple',
            'width' => $width,
            'height' => 320,
            'axis_y' => $axisY,
            'segments' => [[
                'id' => 'main',
                'kind' => 'main',
                'x1' => self::MARGIN_X,
                'y1' => $axisY,
                'x2' => self::MARGIN_X + (($count - 1) * $spacing),
                'y2' => $axisY,
            ]],
            'stations' => $stations
                ->map(fn (array $station, int $index) => $this->stationNode(
                    $station,
                    self::MARGIN_X + ($index * $spacing),
                    $axisY,
                    branch: null,
                    isFirst: $index === 0,
                    isLast: $index === $count - 1,
                ))
                ->values()
                ->all(),
            'branches' => [],
            'terminus' => $this->terminusNames($stations),
        ];
    }

    private function layoutBranchedLine(Line $line, array $topology): array
    {
        $branches = collect($topology['branches'] ?? [])->values();

        if ($branches->count() <= 1) {
            return $this->layoutSimpleLine($line, $topology);
        }

        return $this->layoutSuffixBranchedLine($line, $topology, 'branched');
    }

    private function layoutLine7(Line $line, array $topology): array
    {
        return $this->layoutPrefixBranchedLine($line, $topology, 'line-7');
    }

    private function layoutLine13(Line $line, array $topology): array
    {
        return $this->layoutSuffixBranchedLine($line, $topology, 'line-13');
    }

    private function layoutManualFromReference(Line $line, array $topology): array
    {
        $config = config("line_diagrams.manual.{$line->code}");

        if (! is_array($config)) {
            return $this->layoutSimpleLine($line, $topology);
        }

        $stations = $this->uniqueStations($topology);
        $nodes = $stations
            ->map(function (array $station) use ($config): ?array {
                $point = $this->manualPointForStation($station, $config);

                if ($point === null) {
                    return null;
                }

                $node = $this->stationNode(
                    $station,
                    $point['x'],
                    $point['y'],
                    branch: $point['branch'] ?? ($config['type'] ?? 'manual'),
                    rotation: $point['rotation'] ?? -45,
                    anchor: $point['label_anchor'] ?? null,
                    labelDx: $point['label_dx'] ?? 0,
                    labelDy: $point['label_dy'] ?? 0,
                    isTerminusOverride: ($point['role'] ?? null) === 'terminus',
                );

                return [
                    ...$node,
                    'branch_key' => $point['branch'] ?? ($config['type'] ?? 'manual'),
                    'diagram_order' => $point['order'] ?? null,
                    'diagram_role' => $point['role'] ?? 'station',
                ];
            })
            ->filter()
            ->sortBy(function (array $station) use ($config): int {
                $index = array_search($station['external_id'] ?? null, array_keys($config['stations'] ?? []), true);

                return $station['diagram_order'] ?? ($index === false ? 999 : $index);
            })
            ->values();
        $segments = $this->manualSegments($config['segments'] ?? [], $nodes);

        return [
            'type' => $config['type'] ?? ($topology['type'] ?? 'manual'),
            'width' => 1,
            'height' => $config['height'] ?? 430,
            'axis_y' => collect($config['segments'] ?? [])->first()['y1'] ?? self::SIMPLE_AXIS_Y,
            'segments' => $segments,
            'debug_guides' => $config['debug_guides'] ?? [],
            'trunk_y' => $config['trunk_y'] ?? null,
            'convergence_x' => $config['convergence_x'] ?? null,
            'branch_vertical_gap' => $config['branch_vertical_gap'] ?? null,
            'aligned_pairs' => $config['aligned_pairs'] ?? [],
            'station_groups' => $config['station_groups'] ?? [],
            'convergence_station_id' => $config['convergence_station_id'] ?? null,
            'stations' => $nodes->values()->all(),
            'branches' => [['key' => $config['type'] ?? 'manual', 'label' => 'Layout de reference']],
            'terminus' => $this->terminusNames($stations),
        ];
    }

    private function manualPointForStation(array $station, array $config): ?array
    {
        $externalId = $station['external_id'] ?? null;

        if ($externalId && isset($config['stations'][$externalId])) {
            return $config['stations'][$externalId];
        }

        return collect($config['fallback_names'] ?? [])
            ->first(fn (array $point, string $name) => $this->normalize($name) === $this->normalize($station['name']));
    }

    private function layoutPrefixBranchedLine(Line $line, array $topology, string $type): array
    {
        $branches = collect($topology['branches'] ?? [])->values();
        $trunk = collect($topology['trunk'] ?? [])->values();
        $branchOnly = $branches->map(fn (array $branch) => [
            ...$branch,
            'stations' => collect($branch['stations'])->reject(fn (array $station) => $trunk->contains('id', $station['id']))->values(),
        ]);
        $trunkCount = max(2, $trunk->count());
        $spacing = $this->spacing($trunkCount);
        $forkX = self::MARGIN_X + (($trunkCount - 1) * $spacing);
        $trunkY = 200;
        $branchYs = [118, 292];
        $width = max(1300, $forkX + 520);
        $nodes = collect();
        $segments = [[
            'id' => 'trunk',
            'kind' => 'main',
            'x1' => self::MARGIN_X,
            'y1' => $trunkY,
            'x2' => $forkX,
            'y2' => $trunkY,
        ]];

        foreach ($trunk as $index => $station) {
            $nodes->push($this->stationNode($station, self::MARGIN_X + ($index * $spacing), $trunkY, branch: 'trunk', isFirst: $index === 0));
        }

        foreach ($branchOnly as $branchIndex => $branch) {
            $y = $branchYs[$branchIndex] ?? ($trunkY + (($branchIndex + 1) * 86));
            $startX = $forkX + 86;
            $branchStations = $branch['stations'];
            $segments[] = ['id' => $branch['key'].'-diagonal', 'kind' => 'branch', 'branch' => $branch['key'], 'x1' => $forkX, 'y1' => $trunkY, 'x2' => $startX, 'y2' => $y];
            $segments[] = ['id' => $branch['key'].'-horizontal', 'kind' => 'branch', 'branch' => $branch['key'], 'x1' => $startX, 'y1' => $y, 'x2' => $startX + (max(0, $branchStations->count() - 1) * 150), 'y2' => $y];

            foreach ($branchStations as $index => $station) {
                $nodes->push($this->stationNode($station, $startX + ($index * 150), $y, branch: $branch['key'], isLast: $index === $branchStations->count() - 1));
            }
        }

        return [
            'type' => $type,
            'width' => $width,
            'height' => 500,
            'axis_y' => $trunkY,
            'segments' => $segments,
            'stations' => $nodes->values()->all(),
            'branches' => $branchOnly->map(fn (array $branch) => ['key' => $branch['key'], 'label' => $branch['label'] ?? $branch['key']])->all(),
            'terminus' => $this->terminusNames($this->uniqueStations($topology)),
        ];
    }

    private function layoutSuffixBranchedLine(Line $line, array $topology, string $type): array
    {
        $branches = collect($topology['branches'] ?? [])->values();
        $shared = collect($topology['trunk'] ?? [])->values();
        $branchYs = [112, 254];
        $convergeX = 620;
        $trunkY = 200;
        $trunkSpacing = $this->spacing(max(2, $shared->count()));
        $width = max(1400, $convergeX + (($shared->count() + 3) * $trunkSpacing));
        $nodes = collect();
        $segments = [[
            'id' => 'trunk',
            'kind' => 'main',
            'x1' => $convergeX,
            'y1' => $trunkY,
            'x2' => $convergeX + (max(1, $shared->count() - 1) * $trunkSpacing),
            'y2' => $trunkY,
        ]];

        foreach ($branches as $branchIndex => $branch) {
            $branchStations = collect($branch['stations'])->reject(fn (array $station) => $shared->contains('id', $station['id']))->values();
            $y = $branchYs[$branchIndex] ?? ($trunkY + (($branchIndex + 1) * 86));
            $startX = self::MARGIN_X;
            $lastBranchX = $startX + (max(0, $branchStations->count() - 1) * 150);
            $segments[] = ['id' => $branch['key'].'-horizontal', 'kind' => 'branch', 'branch' => $branch['key'], 'x1' => $startX, 'y1' => $y, 'x2' => $lastBranchX, 'y2' => $y];
            $segments[] = ['id' => $branch['key'].'-diagonal', 'kind' => 'branch', 'branch' => $branch['key'], 'x1' => $lastBranchX, 'y1' => $y, 'x2' => $convergeX, 'y2' => $trunkY];

            foreach ($branchStations as $index => $station) {
                $nodes->push($this->stationNode($station, $startX + ($index * 150), $y, branch: $branch['key'], isFirst: $index === 0, rotation: 0));
            }
        }

        foreach ($shared as $index => $station) {
            $nodes->push($this->stationNode($station, $convergeX + ($index * $trunkSpacing), $trunkY, branch: 'trunk', isLast: $index === $shared->count() - 1));
        }

        return [
            'type' => $type,
            'width' => $width,
            'height' => 500,
            'axis_y' => $trunkY,
            'segments' => $segments,
            'stations' => $nodes->values()->all(),
            'branches' => $branches->map(fn (array $branch) => ['key' => $branch['key'], 'label' => $branch['label'] ?? $branch['key']])->all(),
            'terminus' => $this->terminusNames($this->uniqueStations($topology)),
        ];
    }

    private function stationNode(
        array $station,
        int|float $x,
        int|float $y,
        ?string $branch = null,
        bool $isFirst = false,
        bool $isLast = false,
        ?int $rotation = null,
        ?string $anchor = null,
        int|float $labelDx = 0,
        int|float $labelDy = 0,
        ?bool $isTerminusOverride = null,
    ): array
    {
        $isTerminus = $isTerminusOverride ?? ((bool) ($station['is_terminus'] ?? false) || $isFirst || $isLast);
        $labelRotation = $rotation ?? -45;
        $anchor = $anchor ?? 'end';
        $labelWidth = $this->labelWidth($station['name']);
        $labelX = $x + $labelDx;
        $labelY = $y + self::LABEL_GAP + $labelDy;
        $terminusBox = $isTerminus ? [
            'x' => round($labelX - $labelWidth + 8, 2),
            'y' => round($labelY - 5, 2),
            'width' => $labelWidth,
            'height' => 24,
            'rx' => 4,
        ] : null;

        $connections = collect($station['connections'] ?? [])
            ->values()
            ->map(fn (array $connection, int $index) => [
                ...$connection,
                'x' => round($x + (($index - max(0, count($station['connections'] ?? []) - 1) / 2) * 22), 2),
                'y' => round($y + self::LABEL_GAP + 78, 2),
            ])
            ->all();

        return [
            ...$station,
            'x' => round($x, 2),
            'y' => round($y, 2),
            'label_x' => round($labelX, 2),
            'label_y' => round($labelY, 2),
            'connections_y' => round($y + self::LABEL_GAP + 54, 2),
            'connection_badges' => $connections,
            'label_anchor' => $anchor,
            'label_rotation' => $labelRotation,
            'label_width' => $labelWidth,
            'terminus_label_box' => $terminusBox,
            'is_terminus' => $isTerminus,
            'branch' => $branch,
            'occurrence_key' => ($branch ?? 'main').'-'.$station['id'].'-'.$station['position'],
        ];
    }

    private function manualSegments(array $segments, Collection $nodes): array
    {
        $mainNodes = $nodes
            ->where('branch_key', 'main')
            ->sortBy('diagram_order')
            ->values();

        if ($mainNodes->count() < 2) {
            return $segments;
        }

        $first = $mainNodes->first();
        $last = $mainNodes->last();

        return collect($segments)
            ->map(function (array $segment) use ($first, $last): array {
                if (($segment['id'] ?? null) === 'main-east') {
                    return [
                        ...$segment,
                        'x1' => $first['x'],
                        'y1' => $first['y'],
                        'x2' => $last['x'],
                        'y2' => $last['y'],
                    ];
                }

                return $segment;
            })
            ->all();
    }

    private function finalizeLayout(array $layout): array
    {
        $bounds = $this->layoutBounds($layout);
        $padding = 80;
        $minX = floor($bounds['min_x'] - $padding);
        $minY = floor($bounds['min_y'] - $padding);
        $maxX = ceil($bounds['max_x'] + $padding);
        $maxY = ceil($bounds['max_y'] + $padding);
        $dx = $minX < 0 ? abs($minX) : -$minX;
        $dy = $minY < 0 ? abs($minY) : -$minY;

        foreach ($layout['segments'] as &$segment) {
            $segment['x1'] = round($segment['x1'] + $dx, 2);
            $segment['x2'] = round($segment['x2'] + $dx, 2);
            $segment['y1'] = round($segment['y1'] + $dy, 2);
            $segment['y2'] = round($segment['y2'] + $dy, 2);
        }
        unset($segment);

        foreach ($layout['stations'] as &$station) {
            foreach (['x', 'label_x'] as $key) {
                $station[$key] = round($station[$key] + $dx, 2);
            }
            foreach (['y', 'label_y', 'connections_y'] as $key) {
                $station[$key] = round($station[$key] + $dy, 2);
            }

            if ($station['terminus_label_box'] !== null) {
                $station['terminus_label_box']['x'] = round($station['terminus_label_box']['x'] + $dx, 2);
                $station['terminus_label_box']['y'] = round($station['terminus_label_box']['y'] + $dy, 2);
            }

            foreach ($station['connection_badges'] as &$connection) {
                $connection['x'] = round($connection['x'] + $dx, 2);
                $connection['y'] = round($connection['y'] + $dy, 2);
            }
            unset($connection);
        }
        unset($station);

        $width = max(1, $maxX - $minX);
        $height = max(1, $maxY - $minY);
        $layout['width'] = $width;
        $layout['height'] = $height;
        $layout['axis_y'] = round(($layout['axis_y'] ?? 0) + $dy, 2);
        $layout['view_box'] = [
            'min_x' => 0,
            'min_y' => 0,
            'width' => $width,
            'height' => $height,
            'value' => "0 0 {$width} {$height}",
            'padding' => $padding,
        ];
        $layout['debug_guides'] = collect($layout['debug_guides'] ?? [])
            ->map(fn (array $guide) => [
                ...$guide,
                'y' => round(($guide['y'] ?? 0) + $dy, 2),
            ])
            ->values()
            ->all();
        $layout['trunk_y'] = ($layout['trunk_y'] ?? null) === null ? null : round($layout['trunk_y'] + $dy, 2);
        $layout['branch_vertical_gap'] = $layout['branch_vertical_gap'] ?? null;
        $layout['convergence_x'] = $layout['convergence_x'] ?? null;
        $layout['aligned_pairs'] = $layout['aligned_pairs'] ?? [];
        $layout['station_groups'] = $layout['station_groups'] ?? [];
        $layout['convergence_station_id'] = $layout['convergence_station_id'] ?? null;

        return $layout;
    }

    private function layoutBounds(array $layout): array
    {
        $xs = [0];
        $ys = [0];

        foreach ($layout['segments'] as $segment) {
            array_push($xs, $segment['x1'], $segment['x2']);
            array_push($ys, $segment['y1'], $segment['y2']);
        }

        foreach ($layout['stations'] as $station) {
            array_push($xs, $station['x'] - 16, $station['x'] + 16);
            array_push($ys, $station['y'] - 16, $station['y'] + 16);

            $labelLeft = $station['label_anchor'] === 'end'
                ? $station['label_x'] - $station['label_width']
                : $station['label_x'] - ($station['label_width'] / 2);
            $labelRight = $station['label_anchor'] === 'end'
                ? $station['label_x'] + 8
                : $station['label_x'] + ($station['label_width'] / 2);
            array_push($xs, $labelLeft, $labelRight);
            array_push($ys, $station['label_y'] - 8, $station['label_y'] + 120);

            if ($station['terminus_label_box'] !== null) {
                $box = $station['terminus_label_box'];
                array_push($xs, $box['x'], $box['x'] + $box['width']);
                array_push($ys, $box['y'], $box['y'] + $box['height']);
            }

            foreach ($station['connection_badges'] as $connection) {
                array_push($xs, $connection['x'] - 12, $connection['x'] + 12);
                array_push($ys, $connection['y'] - 12, $connection['y'] + 12);
            }
        }

        return [
            'min_x' => min($xs),
            'min_y' => min($ys),
            'max_x' => max($xs),
            'max_y' => max($ys),
        ];
    }

    private function labelWidth(string $name): int
    {
        return max(76, min(230, (int) ceil(mb_strlen($name) * 7.2) + 18));
    }

    private function spacing(int $count): int
    {
        return match (true) {
            $count <= 12 => 110,
            $count <= 24 => 96,
            default => 84,
        };
    }

    private function width(int $count, int $spacing): int
    {
        return max(1200, (self::MARGIN_X * 2) + (($count - 1) * $spacing));
    }

    private function uniqueStations(array $topology): Collection
    {
        return collect([
            ...($topology['main'] ?? []),
            ...($topology['trunk'] ?? []),
            ...collect($topology['branches'] ?? [])
                ->flatMap(fn (array $branch) => $branch['stations'] ?? [])
                ->all(),
        ])
            ->unique('id')
            ->values();
    }

    private function terminusNames(Collection $stations): array
    {
        return $stations
            ->filter(fn (array $station) => (bool) ($station['is_terminus'] ?? false))
            ->pluck('name')
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        return str($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }
}

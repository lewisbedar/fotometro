<?php

namespace App\Services\Map;

use App\Models\Line;
use Illuminate\Support\Collection;

class LineDiagramLayout
{
    private const MARGIN_X = 96;
    private const SIMPLE_AXIS_Y = 112;
    private const LABEL_GAP = 28;
    private const CONNECTIONS_GAP = 30;
    private const LABEL_ROTATION_DEGREES = -45;
    private const WRAP_THRESHOLD = 13;

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
        $positions = $this->horizontalPositions($stations, self::MARGIN_X);
        $axisY = self::SIMPLE_AXIS_Y;

        return [
            'type' => 'simple',
            'width' => max(1200, ($positions->last() ?? self::MARGIN_X) + self::MARGIN_X),
            'height' => 320,
            'axis_y' => $axisY,
            'segments' => [[
                'id' => 'main',
                'kind' => 'main',
                'x1' => self::MARGIN_X,
                'y1' => $axisY,
                'x2' => $positions->last() ?? self::MARGIN_X,
                'y2' => $axisY,
            ]],
            'stations' => $stations
                ->map(fn (array $station, int $index) => $this->stationNode(
                    $station,
                    $positions[$index],
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

    /**
     * Diagonal labels only need their *projected* horizontal footprint
     * (width x cos(angle)), not their full width, to avoid colliding with
     * neighbouring stations - and wrapping long names onto two lines (see
     * wrapLabelLines()) keeps that footprint small even for long names.
     */
    private function horizontalPositions(Collection $stations, float $startX, float $minGap = 70, float $labelMargin = 16): Collection
    {
        $projection = abs(cos(deg2rad(self::LABEL_ROTATION_DEGREES)));
        $positions = collect();
        $previousHalfFootprint = 0.0;
        $x = $startX;

        foreach ($stations->values() as $index => $station) {
            $halfFootprint = ($this->labelWidth($station['name']) * $projection) / 2;

            if ($index === 0) {
                $x = $startX;
            } else {
                $x += max($minGap, $previousHalfFootprint + $halfFootprint + $labelMargin);
            }

            $positions->push(round($x, 2));
            $previousHalfFootprint = $halfFootprint;
        }

        return $positions;
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
                    rotation: $point['rotation'] ?? self::LABEL_ROTATION_DEGREES,
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
        $trunkPositions = $this->horizontalPositions($trunk, self::MARGIN_X);
        $forkX = $trunkPositions->last() ?? self::MARGIN_X;
        $trunkY = 200;
        $branchYs = [118, 292];
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
            $nodes->push($this->stationNode($station, $trunkPositions[$index], $trunkY, branch: 'trunk', isFirst: $index === 0));
        }

        $maxBranchX = $forkX;

        foreach ($branchOnly as $branchIndex => $branch) {
            $y = $branchYs[$branchIndex] ?? ($trunkY + (($branchIndex + 1) * 86));
            $startX = $forkX + 86;
            $branchStations = $branch['stations'];
            $positions = $this->horizontalPositions($branchStations, $startX);
            $lastX = $positions->last() ?? $startX;
            $maxBranchX = max($maxBranchX, $lastX);
            $segments[] = ['id' => $branch['key'].'-diagonal', 'kind' => 'branch', 'branch' => $branch['key'], 'x1' => $forkX, 'y1' => $trunkY, 'x2' => $startX, 'y2' => $y];
            $segments[] = ['id' => $branch['key'].'-horizontal', 'kind' => 'branch', 'branch' => $branch['key'], 'x1' => $startX, 'y1' => $y, 'x2' => $lastX, 'y2' => $y];

            foreach ($branchStations as $index => $station) {
                $nodes->push($this->stationNode($station, $positions[$index], $y, branch: $branch['key'], isLast: $index === $branchStations->count() - 1));
            }
        }

        return [
            'type' => $type,
            'width' => max(1300, $maxBranchX + self::MARGIN_X),
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
        $trunkY = 200;
        $nodes = collect();

        $branchLayouts = $branches->values()->map(function (array $branch, int $branchIndex) use ($shared, $branchYs, $trunkY) {
            $branchStations = collect($branch['stations'])->reject(fn (array $station) => $shared->contains('id', $station['id']))->values();

            return [
                'key' => $branch['key'],
                'label' => $branch['label'] ?? $branch['key'],
                'y' => $branchYs[$branchIndex] ?? ($trunkY + (($branchIndex + 1) * 86)),
                'stations' => $branchStations,
                'positions' => $this->horizontalPositions($branchStations, self::MARGIN_X),
            ];
        });

        $convergeX = max(620, ($branchLayouts->map(fn (array $branch) => ($branch['positions']->last() ?? self::MARGIN_X) + 140)->max() ?: 620));
        $trunkPositions = $this->horizontalPositions($shared, $convergeX);
        $width = max(1400, ($trunkPositions->last() ?? $convergeX) + self::MARGIN_X);
        $segments = [[
            'id' => 'trunk',
            'kind' => 'main',
            'x1' => $convergeX,
            'y1' => $trunkY,
            'x2' => $trunkPositions->last() ?? $convergeX,
            'y2' => $trunkY,
        ]];

        foreach ($branchLayouts as $branch) {
            $lastBranchX = $branch['positions']->last() ?? self::MARGIN_X;
            $segments[] = ['id' => $branch['key'].'-horizontal', 'kind' => 'branch', 'branch' => $branch['key'], 'x1' => self::MARGIN_X, 'y1' => $branch['y'], 'x2' => $lastBranchX, 'y2' => $branch['y']];
            $segments[] = ['id' => $branch['key'].'-diagonal', 'kind' => 'branch', 'branch' => $branch['key'], 'x1' => $lastBranchX, 'y1' => $branch['y'], 'x2' => $convergeX, 'y2' => $trunkY];

            foreach ($branch['stations'] as $index => $station) {
                $nodes->push($this->stationNode($station, $branch['positions'][$index], $branch['y'], branch: $branch['key'], isFirst: $index === 0));
            }
        }

        foreach ($shared as $index => $station) {
            $nodes->push($this->stationNode($station, $trunkPositions[$index], $trunkY, branch: 'trunk', isLast: $index === $shared->count() - 1));
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
        // RATP schematics write the station name diagonally above the point,
        // starting near it and reading up and to the right (text-anchor
        // "start"), wrapping long names onto a second line rather than
        // running one very long diagonal string.
        $labelRotation = $rotation ?? self::LABEL_ROTATION_DEGREES;
        $anchor = $anchor ?? 'start';
        $labelLines = $this->wrapLabelLines($station['name']);
        $labelWidth = $this->labelWidth($station['name']);
        $labelX = $x + $labelDx;
        $labelY = $y - self::LABEL_GAP + $labelDy;
        $boxLeft = match ($anchor) {
            'end' => $labelX - $labelWidth,
            'middle' => $labelX - ($labelWidth / 2),
            default => $labelX,
        };
        // Local (pre-rotation) box wrapping the label text; used to render
        // the terminus cartouche and, rotated, to size the diagram canvas
        // tightly around every label regardless of how long a name is.
        $labelBox = [
            'x' => round($boxLeft - 4, 2),
            'y' => round($labelY - 5, 2),
            'width' => round($labelWidth + 8, 2),
            'height' => count($labelLines) > 1 ? 40 : 24,
            'rx' => 4,
        ];

        $connections = collect($station['connections'] ?? [])
            ->values()
            ->map(fn (array $connection, int $index) => [
                ...$connection,
                'x' => round($x + (($index - max(0, count($station['connections'] ?? []) - 1) / 2) * 22), 2),
                'y' => round($y + self::CONNECTIONS_GAP, 2),
            ])
            ->all();

        return [
            ...$station,
            'x' => round($x, 2),
            'y' => round($y, 2),
            'label_x' => round($labelX, 2),
            'label_y' => round($labelY, 2),
            'connections_y' => round($y + self::CONNECTIONS_GAP, 2),
            'connection_badges' => $connections,
            'label_anchor' => $anchor,
            'label_rotation' => $labelRotation,
            'label_width' => $labelWidth,
            'label_lines' => $labelLines,
            'label_box' => $labelBox,
            'terminus_label_box' => $isTerminus ? $labelBox : null,
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
        // Bounds are now computed from each label's real rotated extent
        // (see rotatedBoundingBox()), not a worst-case guess, so this only
        // needs to be a small cosmetic margin rather than a safety net.
        $padding = 20;
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
        // No hardcoded 0 seed here: forcing the bounding box to always
        // include the coordinate origin manufactures empty margins whenever
        // a layout's real content doesn't happen to span near y=0 (e.g. line
        // 13's topmost branch sits at y=112), unrelated to actual content.
        $xs = [];
        $ys = [];

        foreach ($layout['segments'] as $segment) {
            array_push($xs, $segment['x1'], $segment['x2']);
            array_push($ys, $segment['y1'], $segment['y2']);
        }

        foreach ($layout['stations'] as $station) {
            array_push($xs, $station['x'] - 16, $station['x'] + 16);
            array_push($ys, $station['y'] - 16, $station['y'] + 16);

            $rotatedLabel = $this->rotatedBoundingBox(
                $station['label_x'],
                $station['label_y'],
                $station['label_box'],
                (int) $station['label_rotation'],
            );
            array_push($xs, $rotatedLabel['min_x'], $rotatedLabel['max_x']);
            array_push($ys, $rotatedLabel['min_y'], $rotatedLabel['max_y']);

            foreach ($station['connection_badges'] as $connection) {
                array_push($xs, $connection['x'] - 12, $connection['x'] + 12);
                array_push($ys, $connection['y'] - 12, $connection['y'] + 12);
            }
        }

        if ($xs === [] || $ys === []) {
            return ['min_x' => 0, 'min_y' => 0, 'max_x' => 1, 'max_y' => 1];
        }

        return [
            'min_x' => min($xs),
            'min_y' => min($ys),
            'max_x' => max($xs),
            'max_y' => max($ys),
        ];
    }

    /**
     * Rotates a label's local (pre-rotation) bounding box around its anchor
     * point and returns the resulting axis-aligned extent, mirroring the
     * `rotate(angle, anchorX, anchorY)` SVG transform applied at render time.
     * This is what lets the canvas be sized tightly around every label
     * instead of relying on a flat, worst-case guess.
     *
     * @param  array{x: float, y: float, width: float, height: float}  $box
     */
    private function rotatedBoundingBox(float $anchorX, float $anchorY, array $box, int $rotationDegrees): array
    {
        $theta = deg2rad($rotationDegrees);
        $cos = cos($theta);
        $sin = sin($theta);

        $corners = [
            [$box['x'], $box['y']],
            [$box['x'] + $box['width'], $box['y']],
            [$box['x'], $box['y'] + $box['height']],
            [$box['x'] + $box['width'], $box['y'] + $box['height']],
        ];

        $xs = [];
        $ys = [];

        foreach ($corners as [$pointX, $pointY]) {
            $dx = $pointX - $anchorX;
            $dy = $pointY - $anchorY;
            $xs[] = $anchorX + ($dx * $cos) - ($dy * $sin);
            $ys[] = $anchorY + ($dx * $sin) + ($dy * $cos);
        }

        return ['min_x' => min($xs), 'max_x' => max($xs), 'min_y' => min($ys), 'max_y' => max($ys)];
    }

    /**
     * Splits a long station name onto two lines at the most balanced word
     * boundary, the way RATP's own line diagrams do (e.g. "Malakoff" /
     * "Plateau de Vanves") instead of running one very long diagonal string.
     */
    private function wrapLabelLines(string $name): array
    {
        $name = trim($name);
        $words = preg_split('/\s+/u', $name) ?: [$name];

        if (mb_strlen($name) <= self::WRAP_THRESHOLD || count($words) < 2) {
            return [$name];
        }

        $bestIndex = 1;
        $bestDiff = PHP_INT_MAX;

        for ($i = 1; $i < count($words); $i++) {
            $line1 = implode(' ', array_slice($words, 0, $i));
            $line2 = implode(' ', array_slice($words, $i));
            $diff = abs(mb_strlen($line1) - mb_strlen($line2));

            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $bestIndex = $i;
            }
        }

        return [
            implode(' ', array_slice($words, 0, $bestIndex)),
            implode(' ', array_slice($words, $bestIndex)),
        ];
    }

    private function labelWidth(string $name): int
    {
        $longestLine = collect($this->wrapLabelLines($name))
            ->map(fn (string $line) => mb_strlen($line))
            ->max();

        return max(76, min(190, (int) ceil($longestLine * 7.2) + 18));
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

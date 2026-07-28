<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\Map\LineDiagramLayout;
use Tests\TestCase;

class LineDiagramLayoutTest extends TestCase
{
    public function test_simple_line_nodes_share_axis_and_labels_are_above_it(): void
    {
        $layout = $this->layout('1', 'simple', [
            ['key' => 'main', 'stations' => $this->stations(['Defense', 'Chatelet', 'Vincennes'])],
        ]);

        $this->assertSame('simple', $layout['type']);
        $this->assertCount(1, $layout['segments']);
        $this->assertCount(1, collect($layout['stations'])->pluck('y')->unique());
        $this->assertTrue(collect($layout['stations'])->every(fn (array $station) => $station['label_y'] <= $layout['axis_y'] - 26));
        $this->assertTrue(collect($layout['stations'])->first()['is_terminus']);
        $this->assertTrue(collect($layout['stations'])->last()['is_terminus']);
        $this->assertNotNull(collect($layout['stations'])->first()['terminus_label_box']);
    }

    public function test_line_seven_remains_branched_without_touching_validated_layout(): void
    {
        $layout = $this->layout('7', 'branched', [
            ['key' => 'branch-a', 'stations' => $this->stations(['La Courneuve', 'Chatelet', 'Maison Blanche', 'Mairie Ivry'])],
            ['key' => 'branch-b', 'stations' => $this->stations(['La Courneuve', 'Chatelet', 'Maison Blanche', 'Villejuif'])],
        ], $this->stations(['La Courneuve', 'Chatelet', 'Maison Blanche']));

        $branchSegments = collect($layout['segments'])->where('kind', 'branch');
        $branchStationYs = collect($layout['stations'])->whereIn('branch', ['branch-a', 'branch-b'])->pluck('y')->unique()->values();

        $this->assertSame('line-7', $layout['type']);
        $this->assertSame(4, $branchSegments->count());
        $this->assertSame(2, $branchStationYs->count());
        $this->assertLayoutInsideViewBox($layout);
    }

    public function test_line_ten_loop_branches_are_parallel_with_aligned_station_pairs(): void
    {
        $layout = $this->layout('10', 'partial-loop', $this->referenceBranches('10'));
        $stations = collect($layout['stations'])->keyBy('external_id');
        $pairs = [
            ['IDFM:PUBLIC:71169', 'IDFM:PUBLIC:73658'],
            ['IDFM:PUBLIC:71206', 'IDFM:PUBLIC:71141'],
            ['IDFM:PUBLIC:71166', 'IDFM:PUBLIC:71162'],
        ];
        $gaps = [];

        foreach ($pairs as [$topId, $bottomId]) {
            $top = $stations[$topId];
            $bottom = $stations[$bottomId];

            $this->assertLessThanOrEqual(0.01, abs($top['x'] - $bottom['x']));
            $gaps[] = round(abs($bottom['y'] - $top['y']), 2);
        }

        $this->assertCount(1, collect($gaps)->unique()->values());
        $this->assertEquals($layout['branch_vertical_gap'], $gaps[0]);

        $upper = collect($layout['segments'])->firstWhere('id', 'upper-branch');
        $lower = collect($layout['segments'])->firstWhere('id', 'lower-branch');

        $this->assertSame($upper['x1'], $lower['x1']);
        $this->assertSame($upper['x2'], $lower['x2']);
        $this->assertSame($upper['y1'], $upper['y2']);
        $this->assertSame($lower['y1'], $lower['y2']);
    }

    public function test_line_ten_station_assignments_follow_reference_table(): void
    {
        $layout = $this->layout('10', 'partial-loop', $this->referenceBranches('10'));
        $stations = collect($layout['stations'])->keyBy('external_id');

        $this->assertSame('west-link', $stations['IDFM:PUBLIC:70721']['branch_key']);
        $this->assertSame('connector_station', $stations['IDFM:PUBLIC:71147']['diagram_role']);
        $this->assertSame('upper-loop', $stations['IDFM:PUBLIC:71169']['branch_key']);
        $this->assertSame('upper-loop', $stations['IDFM:PUBLIC:71206']['branch_key']);
        $this->assertSame('upper-loop', $stations['IDFM:PUBLIC:71166']['branch_key']);
        $this->assertSame('lower-loop', $stations['IDFM:PUBLIC:73658']['branch_key']);
        $this->assertSame('lower-loop', $stations['IDFM:PUBLIC:71141']['branch_key']);
        $this->assertSame('lower-loop', $stations['IDFM:PUBLIC:71162']['branch_key']);
        $this->assertSame('main', $stations['IDFM:PUBLIC:71150']['branch_key']);

        $this->assertSame(
            ['IDFM:PUBLIC:71169', 'IDFM:PUBLIC:71206', 'IDFM:PUBLIC:71166'],
            $stations->where('branch_key', 'upper-loop')->sortBy('diagram_order')->pluck('external_id')->values()->all()
        );
        $this->assertSame(
            ['IDFM:PUBLIC:73658', 'IDFM:PUBLIC:71141', 'IDFM:PUBLIC:71162'],
            $stations->where('branch_key', 'lower-loop')->sortBy('diagram_order')->pluck('external_id')->values()->all()
        );
        $this->assertSame($stations->count(), $stations->pluck('occurrence_key')->unique()->count());
    }

    public function test_line_seven_bis_keeps_buttes_chaumont_and_botzaris_apart(): void
    {
        $layout = $this->layout('7B', 'loop', $this->referenceBranches('7B'));
        $stations = collect($layout['stations'])->keyBy('external_id');
        $buttes = $stations['IDFM:PUBLIC:71900'];
        $botzaris = $stations['IDFM:PUBLIC:71906'];
        $distance = hypot($botzaris['x'] - $buttes['x'], $botzaris['y'] - $buttes['y']);
        $anchorDistance = hypot($botzaris['label_x'] - $buttes['label_x'], $botzaris['label_y'] - $buttes['label_y']);

        $this->assertGreaterThanOrEqual(110, $distance);
        $this->assertGreaterThanOrEqual(110, $anchorDistance);
    }

    public function test_line_ten_chardon_lagache_is_a_single_normal_station(): void
    {
        $layout = $this->layout('10', 'partial-loop', $this->referenceBranches('10'));
        $stations = collect($layout['stations']);
        $chardonStations = $stations->where('external_id', 'IDFM:PUBLIC:71141')->values();
        $chardon = $chardonStations->first();

        $this->assertCount(1, $chardonStations);
        $this->assertFalse($chardon['is_terminus']);
        $this->assertSame('loop_station', $chardon['diagram_role']);
        $this->assertNull($chardon['terminus_label_box']);
    }

    public function test_line_ten_main_segment_reaches_gare_austerlitz(): void
    {
        $layout = $this->layout('10', 'partial-loop', $this->referenceBranches('10'));
        $stations = collect($layout['stations'])->keyBy('external_id');
        $jussieu = $stations['IDFM:PUBLIC:71148'];
        $austerlitz = $stations['IDFM:PUBLIC:71135'];
        $main = collect($layout['segments'])->firstWhere('id', 'main-east');

        $this->assertSame(15, $austerlitz['diagram_order']);
        $this->assertSame('terminus', $austerlitz['diagram_role']);
        $this->assertEquals($austerlitz['x'], $main['x2']);
        $this->assertEquals($austerlitz['y'], $main['y2']);
        $this->assertNotEquals($jussieu['x'], $main['x2']);
        $this->assertLessThanOrEqual($layout['width'], $austerlitz['x']);
        $this->assertLessThanOrEqual($layout['height'], $austerlitz['label_y'] + 24);
    }

    public function test_rotated_station_labels_are_anchored_to_the_station_node(): void
    {
        $layout = $this->layout('10', 'partial-loop', $this->referenceBranches('10'));
        $stations = collect($layout['stations']);
        $ordinary = $stations->firstWhere('external_id', 'IDFM:PUBLIC:71141');
        $terminus = $stations->firstWhere('external_id', 'IDFM:PUBLIC:71135');

        foreach ([$ordinary, $terminus] as $station) {
            $this->assertSame('start', $station['label_anchor']);
            $this->assertSame($station['x'], $station['label_x']);
            $this->assertLessThan($station['y'], $station['label_y']);
            $this->assertNotSame($station['y'], $station['label_y']);
        }

        $this->assertNotNull($terminus['terminus_label_box']);
        $this->assertGreaterThan($terminus['label_x'], $terminus['terminus_label_box']['x'] + $terminus['terminus_label_box']['width']);
        $this->assertSame($terminus['label_x'] - 4, $terminus['terminus_label_box']['x']);
    }

    public function test_line_thirteen_northern_branches_and_trunk_follow_reference_table(): void
    {
        $layout = $this->layout('13', 'branched', $this->referenceBranches('13'), $this->referenceTrunk('13'));
        $stations = collect($layout['stations'])->keyBy('external_id');

        foreach (['IDFM:PUBLIC:72358', 'IDFM:PUBLIC:72326', 'IDFM:PUBLIC:72285', 'IDFM:PUBLIC:72217', 'IDFM:PUBLIC:72168', 'IDFM:PUBLIC:72128', 'IDFM:PUBLIC:72078', 'IDFM:PUBLIC:71528'] as $id) {
            $this->assertSame('north-saint-denis', $stations[$id]['branch_key']);
            $this->assertContains($stations[$id]['diagram_role'], ['terminus', 'branch_station']);
        }

        foreach (['IDFM:PUBLIC:72286', 'IDFM:PUBLIC:72240', 'IDFM:PUBLIC:72203', 'IDFM:PUBLIC:72118', 'IDFM:PUBLIC:71545', 'IDFM:PUBLIC:73661'] as $id) {
            $this->assertSame('north-courtilles', $stations[$id]['branch_key']);
            $this->assertContains($stations[$id]['diagram_role'], ['terminus', 'branch_station']);
        }

        $this->assertSame('convergence_station', $stations['IDFM:PUBLIC:71474']['diagram_role']);
        $this->assertSame('trunk', $stations['IDFM:PUBLIC:71474']['branch_key']);
        $this->assertSame('trunk', $stations['IDFM:PUBLIC:71435']['branch_key']);
        $this->assertSame('trunk', $stations['IDFM:PUBLIC:71305']['branch_key']);
        $this->assertSame('north-saint-denis', $stations['IDFM:PUBLIC:71528']['branch_key']);
    }

    public function test_line_thirteen_common_trunk_is_single_horizontal_axis_after_la_fourche(): void
    {
        $layout = $this->layout('13', 'branched', $this->referenceBranches('13'), $this->referenceTrunk('13'));
        $trunkStations = collect($layout['stations'])->where('branch_key', 'trunk')->values();
        $trunkY = $layout['trunk_y'];

        $this->assertNotNull($trunkY);
        $this->assertSame('IDFM:PUBLIC:71474', $layout['convergence_station_id']);
        $this->assertTrue($trunkStations->every(fn (array $station) => $station['y'] === $trunkY));
        $this->assertSame(1, collect($layout['segments'])->where('id', 'trunk')->count());
        $this->assertTrue($trunkStations->pluck('name')->contains('La Fourche'));
        $this->assertTrue($trunkStations->pluck('name')->contains('Champs-Elysees - Clemenceau'));
        $this->assertFalse($trunkStations->pluck('name')->contains('Guy Moquet'));
    }

    public function test_manual_coordinates_are_resolved_by_external_id_before_name(): void
    {
        config(['line_diagrams.manual.88' => [
            'type' => 'manual',
            'segments' => [['id' => 'main', 'kind' => 'main', 'x1' => 0, 'y1' => 100, 'x2' => 300, 'y2' => 100]],
            'stations' => ['IDFM:TEST:STABLE' => ['x' => 300, 'y' => 100]],
            'fallback_names' => ['Renamed Station' => ['x' => 20, 'y' => 100]],
        ]]);

        $layout = $this->layout('88', 'manual', [[
            'key' => 'main',
            'stations' => [[...$this->stations(['Renamed Station'])[0], 'external_id' => 'IDFM:TEST:STABLE']],
        ]]);

        $this->assertGreaterThan(250, collect($layout['stations'])->first()['x']);
    }

    public function test_manual_coordinates_fall_back_to_name_when_external_id_is_absent(): void
    {
        config(['line_diagrams.manual.87' => [
            'type' => 'manual',
            'segments' => [['id' => 'main', 'kind' => 'main', 'x1' => 0, 'y1' => 100, 'x2' => 300, 'y2' => 100]],
            'stations' => [],
            'fallback_names' => ['Fallback Station' => ['x' => 180, 'y' => 100]],
        ]]);

        $layout = $this->layout('87', 'manual', [[
            'key' => 'main',
            'stations' => $this->stations(['Fallback Station']),
        ]]);

        $this->assertGreaterThan(160, collect($layout['stations'])->first()['x']);
    }

    public function test_every_segment_station_and_terminus_stays_inside_viewbox(): void
    {
        foreach (['1', '7', '7B', '10', '13'] as $code) {
            $layout = $this->layout(
                $code,
                in_array($code, ['7', '13'], true) ? 'branched' : ($code === '10' ? 'partial-loop' : ($code === '7B' ? 'loop' : 'simple')),
                $this->referenceBranches($code),
                $this->referenceTrunk($code)
            );

            $this->assertLayoutInsideViewBox($layout);
        }
    }

    private function layout(string $code, string $type, array $branches, array $trunk = []): array
    {
        return app(LineDiagramLayout::class)->build(new Line(['code' => $code]), [
            'type' => $type,
            'main' => $branches[0]['stations'] ?? [],
            'trunk' => $trunk,
            'branches' => $branches,
            'loop' => [],
            'orientation' => ['start' => $branches[0]['stations'][0] ?? null, 'ends' => []],
        ]);
    }

    private function stations(array $names): array
    {
        return collect($names)->map(fn (string $name, int $index) => [
            'id' => crc32($name),
            'name' => $name,
            'position' => $index + 1,
            'is_terminus' => $index === 0 || $index === count($names) - 1,
            'coverage_status' => ['value' => 'not_started', 'label' => 'Not started', 'description' => 'Not started', 'color' => '#9ca3af'],
            'connections' => [],
        ])->all();
    }

    private function referenceBranches(string $code): array
    {
        return match ($code) {
            '7' => [
                ['key' => 'branch-a', 'stations' => $this->stations(['La Courneuve', 'Chatelet', 'Maison Blanche', 'Mairie Ivry'])],
                ['key' => 'branch-b', 'stations' => $this->stations(['La Courneuve', 'Chatelet', 'Maison Blanche', 'Villejuif'])],
            ],
            '7B' => [['key' => 'main', 'stations' => [
                $this->station('Louis Blanc', 'IDFM:PUBLIC:71407'),
                $this->station('Jaures', 'IDFM:PUBLIC:71940'),
                $this->station('Bolivar', 'IDFM:PUBLIC:71920'),
                $this->station('Buttes Chaumont', 'IDFM:PUBLIC:71900'),
                $this->station('Botzaris', 'IDFM:PUBLIC:71906'),
                $this->station('Danube', 'IDFM:PUBLIC:71930'),
                $this->station('Place des Fetes', 'IDFM:PUBLIC:71885'),
                $this->station('Pre-Saint-Gervais', 'IDFM:PUBLIC:71911'),
            ]]],
            '10' => [['key' => 'main', 'stations' => [
                $this->station('Boulogne Pont de Saint-Cloud', 'IDFM:PUBLIC:70721'),
                $this->station('Boulogne Jean Jaures', 'IDFM:PUBLIC:71147'),
                $this->station('Porte Auteuil', 'IDFM:PUBLIC:71169'),
                $this->station('Michel-Ange - Auteuil', 'IDFM:PUBLIC:71206'),
                $this->station('Eglise Auteuil', 'IDFM:PUBLIC:71166'),
                $this->station('Michel-Ange - Molitor', 'IDFM:PUBLIC:73658'),
                $this->station('Chardon Lagache', 'IDFM:PUBLIC:71141'),
                $this->station('Mirabeau', 'IDFM:PUBLIC:71162'),
                $this->station('Javel - Andre Citroen', 'IDFM:PUBLIC:71150'),
                $this->station('Charles Michels', 'IDFM:PUBLIC:71156'),
                $this->station('Avenue Emile Zola', 'IDFM:PUBLIC:71158'),
                $this->station('La Motte-Picquet - Grenelle', 'IDFM:PUBLIC:71199'),
                $this->station('Segur', 'IDFM:PUBLIC:71157'),
                $this->station('Duroc', 'IDFM:PUBLIC:71159'),
                $this->station('Vaneau', 'IDFM:PUBLIC:71170'),
                $this->station('Sevres - Babylone', 'IDFM:PUBLIC:71203'),
                $this->station('Mabillon', 'IDFM:PUBLIC:73639'),
                $this->station('Odeon', 'IDFM:PUBLIC:73618'),
                $this->station('Cluny - La Sorbonne', 'IDFM:PUBLIC:73619'),
                $this->station('Maubert - Mutualite', 'IDFM:PUBLIC:71190'),
                $this->station('Cardinal Lemoine', 'IDFM:PUBLIC:71153'),
                $this->station('Jussieu', 'IDFM:PUBLIC:71148'),
                $this->station('Gare Austerlitz', 'IDFM:PUBLIC:71135'),
            ]]],
            '13' => [
                ['key' => 'branch-a', 'stations' => [
                    $this->station('Saint-Denis - Universite', 'IDFM:PUBLIC:72358'),
                    $this->station('Basilique de Saint-Denis', 'IDFM:PUBLIC:72326'),
                    $this->station('Saint-Denis - Porte de Paris', 'IDFM:PUBLIC:72285'),
                    $this->station('Carrefour Pleyel', 'IDFM:PUBLIC:72217'),
                    $this->station('Mairie de Saint-Ouen', 'IDFM:PUBLIC:72168'),
                    $this->station('Garibaldi', 'IDFM:PUBLIC:72128'),
                    $this->station('Porte de Saint-Ouen', 'IDFM:PUBLIC:72078'),
                    $this->station('Guy Moquet', 'IDFM:PUBLIC:71528'),
                ]],
                ['key' => 'branch-b', 'stations' => [
                    $this->station('Asnieres - Gennevilliers - Les Courtilles', 'IDFM:PUBLIC:72286'),
                    $this->station('Les Agnettes', 'IDFM:PUBLIC:72240'),
                    $this->station('Gabriel Peri', 'IDFM:PUBLIC:72203'),
                    $this->station('Mairie de Clichy', 'IDFM:PUBLIC:72118'),
                    $this->station('Porte de Clichy', 'IDFM:PUBLIC:71545'),
                    $this->station('Brochant', 'IDFM:PUBLIC:73661'),
                ]],
            ],
            default => [['key' => 'main', 'stations' => $this->stations(['A', 'B', 'C'])]],
        };
    }

    private function referenceTrunk(string $code): array
    {
        return $code === '13' ? [
            $this->station('La Fourche', 'IDFM:PUBLIC:71474'),
            $this->station('Place de Clichy', 'IDFM:PUBLIC:71435'),
            $this->station('Liege', 'IDFM:PUBLIC:71382'),
            $this->station('Saint-Lazare', 'IDFM:PUBLIC:71370'),
            $this->station('Miromesnil', 'IDFM:PUBLIC:71346'),
            $this->station('Champs-Elysees - Clemenceau', 'IDFM:PUBLIC:71305'),
            $this->station('Chatillon - Montrouge', 'IDFM:PUBLIC:461505'),
        ] : ($code === '7' ? $this->stations(['La Courneuve', 'Chatelet', 'Maison Blanche']) : []);
    }

    private function station(string $name, string $externalId): array
    {
        return [...$this->stations([$name])[0], 'external_id' => $externalId];
    }

    private function assertLayoutInsideViewBox(array $layout): void
    {
        foreach ($layout['segments'] as $segment) {
            foreach ([['x1', 'y1'], ['x2', 'y2']] as [$xKey, $yKey]) {
                $this->assertGreaterThanOrEqual(0, $segment[$xKey]);
                $this->assertGreaterThanOrEqual(0, $segment[$yKey]);
                $this->assertLessThanOrEqual($layout['width'], $segment[$xKey]);
                $this->assertLessThanOrEqual($layout['height'], $segment[$yKey]);
            }
        }

        foreach ($layout['stations'] as $station) {
            $this->assertGreaterThanOrEqual(0, $station['x']);
            $this->assertGreaterThanOrEqual(0, $station['y']);
            $this->assertLessThanOrEqual($layout['width'], $station['x']);
            $this->assertLessThanOrEqual($layout['height'], $station['y']);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\LineStationSequence;
use App\Models\Station;
use App\Models\StationStop;
use App\Services\Idfm\NetworkImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use ZipArchive;

class GtfsLineOrderImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_gtfs_import_orders_simple_line_and_ignores_short_service(): void
    {
        [$line, $stations] = $this->lineWithStops('C01371', '99', ['Alpha', 'Beta', 'Gamma', 'Delta']);
        $zip = $this->gtfsZip([
            ['route_id' => 'IDFM:C01371', 'route_short_name' => '1'],
        ], [
            ['route_id' => 'IDFM:C01371', 'service_id' => 'S', 'trip_id' => 'long-a', 'direction_id' => '0'],
            ['route_id' => 'IDFM:C01371', 'service_id' => 'S', 'trip_id' => 'long-b', 'direction_id' => '1'],
            ['route_id' => 'IDFM:C01371', 'service_id' => 'S', 'trip_id' => 'short', 'direction_id' => '0'],
        ], [
            'long-a' => ['STOP:Alpha', 'STOP:Beta', 'STOP:Gamma', 'STOP:Delta'],
            'long-b' => ['STOP:Delta', 'STOP:Gamma', 'STOP:Beta', 'STOP:Alpha'],
            'short' => ['STOP:Alpha', 'STOP:Beta'],
        ]);

        $report = app(NetworkImporter::class)->import([
            'gtfs_archive' => ['path' => $zip],
        ], ['only' => 'gtfs']);

        $ordered = $line->fresh()->stations->sortBy('pivot.position')->values();

        $this->assertSame(['Alpha', 'Beta', 'Gamma', 'Delta'], $ordered->pluck('name')->all());
        $this->assertSame([1, 2, 3, 4], $ordered->pluck('pivot.position')->all());
        $this->assertTrue((bool) $ordered[0]->pivot->is_terminus);
        $this->assertTrue((bool) $ordered[3]->pivot->is_terminus);
        $this->assertNull($ordered[1]->pivot->branch);
        $this->assertSame(1, $report->gtfsSimpleLines);
        $this->assertSame(4, $report->gtfsRelationsUpdated);
        $this->assertSame(4, $report->gtfsTopologySequencesCreated);
        $this->assertSame(['Alpha', 'Beta', 'Gamma', 'Delta'], $line->stationSequences()->with('station')->get()->pluck('station.name')->all());
    }

    public function test_gtfs_import_detects_branches_and_deduplicates_station_stops(): void
    {
        [$line] = $this->lineWithStops('C01372', '97', ['Trunk', 'Fork', 'North', 'South']);
        $fork = Station::query()->where('name', 'Fork')->firstOrFail();
        StationStop::query()->create([
            'station_id' => $fork->id,
            'external_id' => 'STOP:Fork-Platform-B',
            'name' => 'Fork quai B',
            'source' => 'idfm',
            'is_active' => true,
        ]);

        $zip = $this->gtfsZip([
            ['route_id' => 'IDFM:C01372', 'route_short_name' => '7'],
        ], [
            ['route_id' => 'IDFM:C01372', 'service_id' => 'S', 'trip_id' => 'north', 'direction_id' => '0'],
            ['route_id' => 'IDFM:C01372', 'service_id' => 'S', 'trip_id' => 'south', 'direction_id' => '0'],
        ], [
            'north' => ['STOP:Trunk', 'STOP:Fork', 'STOP:Fork-Platform-B', 'STOP:North'],
            'south' => ['STOP:Trunk', 'STOP:Fork', 'STOP:South'],
        ]);

        $report = app(NetworkImporter::class)->import([
            'gtfs_archive' => ['path' => $zip],
        ], ['only' => 'gtfs']);

        $stations = $line->fresh()->stations->keyBy('name');

        $this->assertSame('main', $stations['Trunk']->pivot->branch);
        $this->assertSame('main', $stations['Fork']->pivot->branch);
        $this->assertSame('branch-a', $stations['North']->pivot->branch);
        $this->assertSame('branch-b', $stations['South']->pivot->branch);
        $this->assertTrue((bool) $stations['North']->pivot->is_terminus);
        $this->assertTrue((bool) $stations['South']->pivot->is_terminus);
        $this->assertSame(1, $report->gtfsBranchedLines);
        $this->assertSame(4, $report->gtfsOrderedStations);
        $this->assertSame(6, $report->gtfsTopologySequencesCreated);
        $this->assertSame(2, $line->stationSequences()->distinct('sequence_key')->count('sequence_key'));
    }

    public function test_gtfs_dry_run_does_not_write_order(): void
    {
        [$line] = $this->lineWithStops('C01373', '96', ['One', 'Two']);
        $zip = $this->gtfsZip(
            [['route_id' => 'IDFM:C01373', 'route_short_name' => '2']],
            [['route_id' => 'IDFM:C01373', 'service_id' => 'S', 'trip_id' => 'trip', 'direction_id' => '0']],
            ['trip' => ['STOP:One', 'STOP:Two']]
        );

        app(NetworkImporter::class)->import([
            'gtfs_archive' => ['path' => $zip],
        ], ['only' => 'gtfs', 'dry_run' => true]);

        $this->assertSame([0, 0], $line->fresh()->stations->pluck('pivot.position')->all());
        $this->assertSame(0, LineStationSequence::query()->count());
    }

    public function test_map_api_returns_gtfs_sorted_stations(): void
    {
        [$line] = $this->lineWithStops('C01374', '95', ['First', 'Second', 'Third']);
        $zip = $this->gtfsZip(
            [['route_id' => 'IDFM:C01374', 'route_short_name' => '3']],
            [['route_id' => 'IDFM:C01374', 'service_id' => 'S', 'trip_id' => 'trip', 'direction_id' => '0']],
            ['trip' => ['STOP:First', 'STOP:Second', 'STOP:Third']]
        );

        app(NetworkImporter::class)->import([
            'gtfs_archive' => ['path' => $zip],
        ], ['only' => 'gtfs']);

        Cache::flush();
        $linePayload = collect($this->getJson('/api/map')->assertOk()->json('lines'))->firstWhere('id', $line->id);

        $this->assertSame(['First', 'Second', 'Third'], collect($linePayload['stations'])->pluck('name')->all());
        $this->assertSame([1, 2, 3], collect($linePayload['stations'])->pluck('position')->all());
        $this->assertSame('simple', $linePayload['topology']['type']);
        $this->assertSame(['First', 'Second', 'Third'], collect($linePayload['topology']['branches'][0]['stations'])->pluck('name')->all());
    }

    public function test_manual_orientation_reverses_line_three_to_pont_de_levallois_then_gallieni(): void
    {
        [$line] = $this->lineWithStops('C01375', '3', ['Gallieni', 'Anatole France', 'Pont de Levallois - Bécon'], [
            'Gallieni' => 'IDFM:PUBLIC:71817',
            'Pont de Levallois - Bécon' => 'IDFM:PUBLIC:72057',
        ]);
        $zip = $this->gtfsZip(
            [['route_id' => 'IDFM:C01375', 'route_short_name' => '3']],
            [['route_id' => 'IDFM:C01375', 'service_id' => 'S', 'trip_id' => 'trip', 'direction_id' => '0']],
            ['trip' => ['STOP:Gallieni', 'STOP:Anatole France', 'STOP:Pont de Levallois - Bécon']]
        );

        $report = app(NetworkImporter::class)->import([
            'gtfs_archive' => ['path' => $zip],
        ], ['only' => 'gtfs']);

        $this->assertSame(1, $report->gtfsOrientationsReversed);
        $this->assertSame(1, $report->gtfsManualOrientationsUsed);
        $this->assertSame(
            ['Pont de Levallois - Bécon', 'Anatole France', 'Gallieni'],
            $line->stationSequences()->with('station')->get()->pluck('station.name')->all()
        );
    }

    public function test_manual_orientation_prefers_external_id_over_station_name(): void
    {
        [$line] = $this->lineWithStops('C01381', '93', ['Renamed End', 'Middle', 'Renamed Start'], [
            'Renamed End' => 'IDFM:TEST:END',
            'Renamed Start' => 'IDFM:TEST:START',
        ]);
        config(['fotometro.line_orientation.93' => [
            'start_external_id' => 'IDFM:TEST:START',
            'end_external_ids' => ['IDFM:TEST:END'],
            'start_name' => 'Old Start Name',
            'end_names' => ['Old End Name'],
            'type' => 'simple',
        ]]);
        $zip = $this->gtfsZip(
            [['route_id' => 'IDFM:C01381', 'route_short_name' => '93']],
            [['route_id' => 'IDFM:C01381', 'service_id' => 'S', 'trip_id' => 'trip', 'direction_id' => '0']],
            ['trip' => ['STOP:Renamed End', 'STOP:Middle', 'STOP:Renamed Start']]
        );

        $report = app(NetworkImporter::class)->import(['gtfs_archive' => ['path' => $zip]], ['only' => 'gtfs']);

        $this->assertSame(1, $report->gtfsOrientationsReversed);
        $this->assertSame(['Renamed Start', 'Middle', 'Renamed End'], $line->stationSequences()->with('station')->get()->pluck('station.name')->all());
        $this->assertSame([], $report->warnings);
    }

    public function test_manual_orientation_falls_back_to_normalized_station_name(): void
    {
        [$line] = $this->lineWithStops('C01382', '92', ['Terminus Est', 'Centre', 'Terminus Ouest']);
        config(['fotometro.line_orientation.92' => [
            'start_name' => 'Terminus Ouest',
            'end_names' => ['Terminus Est'],
            'type' => 'simple',
        ]]);
        $zip = $this->gtfsZip(
            [['route_id' => 'IDFM:C01382', 'route_short_name' => '92']],
            [['route_id' => 'IDFM:C01382', 'service_id' => 'S', 'trip_id' => 'trip', 'direction_id' => '0']],
            ['trip' => ['STOP:Terminus Est', 'STOP:Centre', 'STOP:Terminus Ouest']]
        );

        $report = app(NetworkImporter::class)->import(['gtfs_archive' => ['path' => $zip]], ['only' => 'gtfs']);

        $this->assertSame(1, $report->gtfsOrientationsReversed);
        $this->assertSame(['Terminus Ouest', 'Centre', 'Terminus Est'], $line->stationSequences()->with('station')->get()->pluck('station.name')->all());
    }

    public function test_station_name_change_keeps_orientation_when_external_id_is_stable(): void
    {
        [$line] = $this->lineWithStops('C01383', '91', ['New East Label', 'Middle', 'New West Label'], [
            'New East Label' => 'IDFM:TEST:EAST',
            'New West Label' => 'IDFM:TEST:WEST',
        ]);
        config(['fotometro.line_orientation.91' => [
            'start_external_id' => 'IDFM:TEST:WEST',
            'end_external_ids' => ['IDFM:TEST:EAST'],
            'start_name' => 'Old West Label',
            'end_names' => ['Old East Label'],
            'type' => 'simple',
        ]]);
        $zip = $this->gtfsZip(
            [['route_id' => 'IDFM:C01383', 'route_short_name' => '91']],
            [['route_id' => 'IDFM:C01383', 'service_id' => 'S', 'trip_id' => 'trip', 'direction_id' => '0']],
            ['trip' => ['STOP:New East Label', 'STOP:Middle', 'STOP:New West Label']]
        );

        app(NetworkImporter::class)->import(['gtfs_archive' => ['path' => $zip]], ['only' => 'gtfs']);

        $this->assertSame(['New West Label', 'Middle', 'New East Label'], $line->stationSequences()->with('station')->get()->pluck('station.name')->all());
    }

    public function test_missing_orientation_external_id_reports_clear_warning(): void
    {
        [$line] = $this->lineWithStops('C01384', '90', ['West', 'East']);
        config(['fotometro.line_orientation.90' => [
            'start_external_id' => 'IDFM:TEST:MISSING-START',
            'end_external_ids' => ['IDFM:TEST:MISSING-END'],
            'type' => 'simple',
        ]]);
        $zip = $this->gtfsZip(
            [['route_id' => 'IDFM:C01384', 'route_short_name' => '90']],
            [['route_id' => 'IDFM:C01384', 'service_id' => 'S', 'trip_id' => 'trip', 'direction_id' => '0']],
            ['trip' => ['STOP:West', 'STOP:East']]
        );

        $report = app(NetworkImporter::class)->import(['gtfs_archive' => ['path' => $zip]], ['only' => 'gtfs']);

        $this->assertSame(1, $report->gtfsUnresolvedOrientationRules);
        $this->assertTrue(collect($report->warnings)->contains(fn (string $warning) => str_contains($warning, 'orientation start rule for line 90')));
        $this->assertTrue(collect($report->warnings)->contains(fn (string $warning) => str_contains($warning, 'orientation end rule for line 90')));
    }

    public function test_line_seven_api_topology_exposes_two_southern_branches(): void
    {
        [$line] = $this->lineWithStops('C01376', '7', [
            'La Courneuve - 8 Mai 1945',
            'Châtelet',
            'Maison Blanche',
            'Mairie d\'Ivry',
            'Villejuif - Louis Aragon',
        ]);
        $zip = $this->gtfsZip(
            [['route_id' => 'IDFM:C01376', 'route_short_name' => '7']],
            [
                ['route_id' => 'IDFM:C01376', 'service_id' => 'S', 'trip_id' => 'ivry', 'direction_id' => '0'],
                ['route_id' => 'IDFM:C01376', 'service_id' => 'S', 'trip_id' => 'villejuif', 'direction_id' => '0'],
            ],
            [
                'ivry' => ['STOP:La Courneuve - 8 Mai 1945', 'STOP:Châtelet', 'STOP:Maison Blanche', 'STOP:Mairie d\'Ivry'],
                'villejuif' => ['STOP:La Courneuve - 8 Mai 1945', 'STOP:Châtelet', 'STOP:Maison Blanche', 'STOP:Villejuif - Louis Aragon'],
            ]
        );

        app(NetworkImporter::class)->import([
            'gtfs_archive' => ['path' => $zip],
        ], ['only' => 'gtfs']);

        Cache::flush();
        $linePayload = collect($this->getJson('/api/map')->assertOk()->json('lines'))->firstWhere('id', $line->id);

        $this->assertSame('branched', $linePayload['topology']['type']);
        $this->assertSame('La Courneuve - 8 Mai 1945', $linePayload['topology']['orientation']['start']['name']);
        $this->assertCount(2, $linePayload['topology']['branches']);
        $this->assertSame(
            ['Mairie d\'Ivry', 'Villejuif - Louis Aragon'],
            collect($linePayload['topology']['orientation']['ends'])->pluck('name')->sort()->values()->all()
        );
        $this->assertSame(['La Courneuve - 8 Mai 1945', 'Châtelet', 'Maison Blanche'], collect($linePayload['topology']['trunk'])->pluck('name')->all());
    }

    public function test_line_thirteen_api_topology_keeps_two_northern_branches_towards_chatillon(): void
    {
        [$line] = $this->lineWithStops('C01377', '13', [
            'Châtillon - Montrouge',
            'La Fourche',
            'Saint-Denis - Université',
            'Asnières - Gennevilliers - Les Courtilles',
        ]);
        $zip = $this->gtfsZip(
            [['route_id' => 'IDFM:C01377', 'route_short_name' => '13']],
            [
                ['route_id' => 'IDFM:C01377', 'service_id' => 'S', 'trip_id' => 'saint-denis', 'direction_id' => '0'],
                ['route_id' => 'IDFM:C01377', 'service_id' => 'S', 'trip_id' => 'courtilles', 'direction_id' => '0'],
            ],
            [
                'saint-denis' => ['STOP:Châtillon - Montrouge', 'STOP:La Fourche', 'STOP:Saint-Denis - Université'],
                'courtilles' => ['STOP:Châtillon - Montrouge', 'STOP:La Fourche', 'STOP:Asnières - Gennevilliers - Les Courtilles'],
            ]
        );

        app(NetworkImporter::class)->import([
            'gtfs_archive' => ['path' => $zip],
        ], ['only' => 'gtfs']);

        Cache::flush();
        $linePayload = collect($this->getJson('/api/map')->assertOk()->json('lines'))->firstWhere('id', $line->id);

        $this->assertSame('branched', $linePayload['topology']['type']);
        $this->assertContains($linePayload['topology']['orientation']['start']['name'], [
            'Saint-Denis - Université',
            'Asnières - Gennevilliers - Les Courtilles',
        ]);
        $this->assertContains('Châtillon - Montrouge', collect($linePayload['topology']['orientation']['ends'])->pluck('name')->all());
        $this->assertCount(2, $linePayload['topology']['branches']);
    }

    public function test_line_seven_bis_and_ten_expose_business_loop_types(): void
    {
        [$sevenBis] = $this->lineWithStops('C01378', '7B', ['Louis Blanc', 'Botzaris', 'Place des Fêtes', 'Pré-Saint-Gervais']);
        [$ten] = $this->lineWithStops('C01379', '10', ['Boulogne Pont de Saint-Cloud', 'Porte d\'Auteuil', 'Gare d\'Austerlitz']);
        $zip = $this->gtfsZip(
            [
                ['route_id' => 'IDFM:C01378', 'route_short_name' => '7B'],
                ['route_id' => 'IDFM:C01379', 'route_short_name' => '10'],
            ],
            [
                ['route_id' => 'IDFM:C01378', 'service_id' => 'S', 'trip_id' => 'seven-bis', 'direction_id' => '0'],
                ['route_id' => 'IDFM:C01379', 'service_id' => 'S', 'trip_id' => 'ten', 'direction_id' => '0'],
            ],
            [
                'seven-bis' => ['STOP:Louis Blanc', 'STOP:Botzaris', 'STOP:Place des Fêtes', 'STOP:Pré-Saint-Gervais'],
                'ten' => ['STOP:Boulogne Pont de Saint-Cloud', 'STOP:Porte d\'Auteuil', 'STOP:Gare d\'Austerlitz'],
            ]
        );

        app(NetworkImporter::class)->import([
            'gtfs_archive' => ['path' => $zip],
        ], ['only' => 'gtfs']);

        Cache::flush();
        $lines = collect($this->getJson('/api/map')->assertOk()->json('lines'));

        $this->assertSame('loop', $lines->firstWhere('id', $sevenBis->id)['topology']['type']);
        $this->assertSame('partial-loop', $lines->firstWhere('id', $ten->id)['topology']['type']);
    }

    public function test_second_gtfs_import_replaces_topology_without_duplication(): void
    {
        [$line] = $this->lineWithStops('C01380', '94', ['One', 'Two', 'Three']);
        $zip = $this->gtfsZip(
            [['route_id' => 'IDFM:C01380', 'route_short_name' => '94']],
            [['route_id' => 'IDFM:C01380', 'service_id' => 'S', 'trip_id' => 'trip', 'direction_id' => '0']],
            ['trip' => ['STOP:One', 'STOP:Two', 'STOP:Three']]
        );

        app(NetworkImporter::class)->import(['gtfs_archive' => ['path' => $zip]], ['only' => 'gtfs']);
        app(NetworkImporter::class)->import(['gtfs_archive' => ['path' => $zip]], ['only' => 'gtfs']);

        $this->assertSame(3, $line->stationSequences()->count());
    }

    private function lineWithStops(string $externalId, string $code, array $names, array $stationExternalIds = []): array
    {
        $line = Line::factory()->create([
            'external_id' => $externalId,
            'code' => $code,
            'slug' => 'ligne-'.strtolower($code),
            'is_active' => true,
        ]);
        $stations = collect($names)->map(function (string $name, int $index) use ($line, $stationExternalIds): Station {
            $station = Station::factory()->create([
                'external_id' => $stationExternalIds[$name] ?? null,
                'name' => $name,
                'slug' => strtolower($name),
                'latitude' => 48.90 - ($index * 0.01),
                'longitude' => 2.20 + ($index * 0.01),
                'source' => 'idfm',
                'is_active' => true,
            ]);
            StationStop::query()->create([
                'station_id' => $station->id,
                'external_id' => "STOP:{$name}",
                'name' => "{$name} stop",
                'source' => 'idfm',
                'is_active' => true,
            ]);
            $line->stations()->attach($station, ['position' => 0, 'is_terminus' => false]);

            return $station;
        });

        return [$line, $stations];
    }

    private function gtfsZip(array $routes, array $trips, array $stopTimesByTrip): string
    {
        $path = storage_path('app/testing-gtfs-'.uniqid().'.zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('routes.txt', $this->csv(['route_id', 'route_short_name'], $routes));
        $zip->addFromString('trips.txt', $this->csv(['route_id', 'service_id', 'trip_id', 'direction_id'], $trips));

        $stopRows = [];
        foreach ($stopTimesByTrip as $tripId => $stops) {
            foreach ($stops as $index => $stopId) {
                $stopRows[] = ['trip_id' => $tripId, 'arrival_time' => '00:00:00', 'departure_time' => '00:00:00', 'stop_id' => $stopId, 'stop_sequence' => $index + 1];
            }
        }

        $zip->addFromString('stop_times.txt', $this->csv(['trip_id', 'arrival_time', 'departure_time', 'stop_id', 'stop_sequence'], $stopRows));
        $zip->addFromString('stops.txt', $this->csv(['stop_id', 'stop_name'], []));
        $zip->close();

        return $path;
    }

    private function csv(array $headers, array $rows): string
    {
        return implode(',', $headers)."\n".collect($rows)
            ->map(fn (array $row) => implode(',', array_map(fn (string $header) => $row[$header] ?? '', $headers)))
            ->implode("\n");
    }
}

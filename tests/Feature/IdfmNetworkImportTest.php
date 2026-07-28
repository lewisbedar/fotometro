<?php

namespace Tests\Feature;

use App\Enums\CoverageStatus;
use App\Models\Line;
use App\Models\Station;
use App\Models\StationAccess;
use App\Models\StationStop;
use App\Services\Idfm\IdfmIdentifier;
use App\Services\Idfm\NetworkImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IdfmNetworkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_lines_stations_correspondances_and_ordered_pivot_metadata(): void
    {
        app(NetworkImporter::class)->import($this->dataset(), ['skip_traces' => true, 'skip_accesses' => true]);

        $lineOne = Line::query()->where('external_id', 'LINE:1')->firstOrFail();
        $lineSix = Line::query()->where('external_id', 'LINE:6')->firstOrFail();
        $chatelet = Station::query()->where('name', 'Chatelet')->firstOrFail();

        $this->assertSame('1', $lineOne->code);
        $this->assertSame('Ligne 1', $lineOne->name);
        $this->assertSame('#FFCD00', $lineOne->color);
        $this->assertSame(['1', '6'], $chatelet->lines->pluck('code')->all());
        $this->assertSame(['La Defense', 'Chatelet'], $lineOne->stations->pluck('name')->all());
        $this->assertFalse((bool) $lineOne->stations->first()->pivot->is_terminus);
        $this->assertNull($lineOne->stations->last()->pivot->branch);
        $this->assertSame(['Chatelet', 'Nation'], $lineSix->stations->pluck('name')->all());
        $this->assertSame(3, StationStop::query()->count());
    }

    public function test_import_updates_existing_line_and_preserves_station_editorial_fields(): void
    {
        Line::factory()->create([
            'external_id' => 'LINE:1',
            'code' => '1',
            'name' => 'Ancienne ligne',
            'slug' => 'ligne-1',
        ]);
        Station::factory()->create([
            'external_id' => 'IDFM:STOP:CHATELET',
            'name' => 'Ancien Chatelet',
            'slug' => 'chatelet',
            'description' => 'Description editoriale',
            'coverage_status' => CoverageStatus::Complete,
        ]);

        app(NetworkImporter::class)->import($this->dataset(), ['skip_traces' => true, 'skip_accesses' => true]);

        $line = Line::query()->where('external_id', 'LINE:1')->firstOrFail();
        $station = Station::query()->where('external_id', 'IDFM:STOP:CHATELET')->firstOrFail();

        $this->assertSame('Ligne 1', $line->name);
        $this->assertSame('Description editoriale', $station->description);
        $this->assertSame(CoverageStatus::Complete, $station->coverage_status);
    }

    public function test_import_accepts_valid_line_geojson_and_ignores_invalid_trace_without_overwriting(): void
    {
        app(NetworkImporter::class)->import($this->dataset(), ['skip_traces' => false, 'skip_accesses' => true]);

        $lineOne = Line::query()->where('external_id', 'LINE:1')->firstOrFail();
        $lineSix = Line::query()->where('external_id', 'LINE:6')->firstOrFail();

        $this->assertSame('LineString', $lineOne->path_geojson['type']);
        $this->assertNull($lineSix->path_geojson);

        $lineOne->forceFill(['path_geojson' => ['type' => 'LineString', 'coordinates' => [[2.1, 48.8], [2.2, 48.9]]]])->save();

        app(NetworkImporter::class)->import([
            'traces' => [
                ['route_id' => 'IDFM:LINE:1', 'geometry' => ['type' => 'Point', 'coordinates' => [2.35, 48.85]]],
            ],
        ], ['only' => null, 'skip_accesses' => true]);

        $this->assertSame([[2.1, 48.8], [2.2, 48.9]], $lineOne->fresh()->path_geojson['coordinates']);
    }

    public function test_access_import_creates_accesses_and_multi_station_relations(): void
    {
        app(NetworkImporter::class)->import($this->dataset());

        $access = StationAccess::query()->where('external_id', 'IDFM:ACCESS:1')->firstOrFail();

        $this->assertSame('Sortie 1', $access->name);
        $this->assertTrue($access->wheelchair_accessible);
        $this->assertSame(['Chatelet', 'Nation'], $access->stations->pluck('name')->sort()->values()->all());
    }

    public function test_dry_run_reports_changes_without_committing(): void
    {
        $report = app(NetworkImporter::class)->import($this->dataset(), ['dry_run' => true]);

        $this->assertGreaterThan(0, $report->created);
        $this->assertDatabaseCount('lines', 0);
        $this->assertDatabaseCount('stations', 0);
        $this->assertContains('Dry-run mode: no database changes were committed.', $report->warnings);
    }

    public function test_only_stations_uses_preexisting_database_lines(): void
    {
        Line::factory()->create([
            'external_id' => 'C01371',
            'code' => '1',
            'name' => 'Ligne 1',
            'slug' => 'ligne-1',
        ]);

        $report = app(NetworkImporter::class)->import([
            'arrets_lignes' => [
                [
                    'id' => 'IDFM:C01371',
                    'shortname' => '1',
                    'route_long_name' => 'Ligne 1',
                    'mode' => 'metro',
                    'stop_id' => 'IDFM:STOP:ONLY',
                    'stop_name' => 'Station Only',
                    'stop_lon' => '',
                    'stop_lat' => '',
                ],
            ],
            'stop_areas' => [
                ['zdaid' => '100', 'zdcid' => '900', 'zdaname' => 'Station Only', 'zdatown' => 'Paris'],
            ],
            'stop_relations' => [
                ['arrid' => 'STOP:ONLY', 'zdaid' => '100'],
            ],
        ], ['only' => 'stations']);

        $this->assertSame(1, $report->created);
        $this->assertSame(1, $report->stationLineRelationsCreated);
        $this->assertSame(1, $report->linesLoadedFromDatabase);
        $this->assertDatabaseHas('stations', ['external_id' => 'IDFM:PUBLIC:900', 'latitude' => null, 'longitude' => null]);
        $this->assertDatabaseHas('station_stops', ['external_id' => 'IDFM:STOP:ONLY', 'zone_external_id' => '100']);
    }

    public function test_only_stations_reports_explicit_error_without_database_lines(): void
    {
        $report = app(NetworkImporter::class)->import($this->dataset(), [
            'only' => 'stations',
            'force' => true,
        ]);

        $this->assertSame('Cannot import stations: no metro lines are available in the database. Run --only=lines first.', $report->errors[0]);
        $this->assertSame(0, $report->created);
    }

    public function test_line_identifier_prefixes_share_the_same_canonical_form(): void
    {
        $this->assertSame('C01371', IdfmIdentifier::line('IDFM:C01371'));
        $this->assertSame('C01371', IdfmIdentifier::line(' C01371 '));
        $this->assertSame('C01371', IdfmIdentifier::line('"01371"'));
    }

    public function test_absent_imported_records_are_deactivated_not_deleted(): void
    {
        app(NetworkImporter::class)->import($this->dataset(), ['skip_traces' => true, 'skip_accesses' => true]);

        app(NetworkImporter::class)->import([
            'arrets_lignes' => [
                $this->dataset()['arrets_lignes'][0],
            ],
            'stop_areas' => $this->dataset()['stop_areas'],
            'stop_relations' => $this->dataset()['stop_relations'],
        ], ['skip_traces' => true, 'skip_accesses' => true]);

        $this->assertFalse(Line::query()->where('external_id', 'LINE:6')->firstOrFail()->is_active);
        $this->assertFalse(Station::query()->where('external_id', 'IDFM:PUBLIC:9003')->firstOrFail()->is_active);
        $this->assertDatabaseHas('lines', ['external_id' => 'LINE:6']);
        $this->assertDatabaseHas('stations', ['external_id' => 'IDFM:PUBLIC:9003']);
    }

    public function test_second_import_does_not_create_duplicates_and_invalid_only_option_fails(): void
    {
        app(NetworkImporter::class)->import($this->dataset());
        app(NetworkImporter::class)->import($this->dataset());

        $this->assertDatabaseCount('lines', 2);
        $this->assertDatabaseCount('stations', 3);
        $this->assertDatabaseCount('station_stops', 3);
        $this->assertDatabaseCount('station_accesses', 1);

        $this->artisan('fotometro:import-network --only=bad')->assertExitCode(1);
    }

    public function test_successful_import_invalidates_public_map_cache(): void
    {
        Cache::put('fotometro.public-map.v1', ['stale' => true], 300);

        app(NetworkImporter::class)->import($this->dataset());

        $this->assertFalse(Cache::has('fotometro.public-map.v1'));
    }

    public function test_debug_database_route_is_local_only(): void
    {
        $this->get('/debug/database')->assertNotFound();

        $this->app->detectEnvironment(fn () => 'local');

        $this->getJson('/debug/database')
            ->assertOk()
            ->assertJsonStructure(['driver', 'database', 'lines', 'stations', 'station_line_relations']);
    }

    public function test_access_import_reports_missing_configuration_or_stations(): void
    {
        config([
            'fotometro.idfm.accesses_url' => '',
            'fotometro.idfm.access_relations_url' => '',
        ]);

        $missingUrls = app(NetworkImporter::class)->import(['accesses' => [], 'access_station' => []], ['only' => 'accesses']);

        $this->assertContains('Skipping accesses: FOTOMETRO_IDFM_ACCESSES_URL and FOTOMETRO_IDFM_ACCESS_RELATIONS_URL must both be configured.', $missingUrls->warnings);

        config([
            'fotometro.idfm.accesses_url' => 'file://dummy.csv',
            'fotometro.idfm.access_relations_url' => 'file://relations.csv',
        ]);

        $missingStations = app(NetworkImporter::class)->import(['accesses' => [], 'access_station' => []], ['only' => 'accesses']);

        $this->assertContains('Skipping accesses: no stations are available in the database.', $missingStations->warnings);
    }

    public function test_access_dataset_defaults_are_configured(): void
    {
        $this->assertSame(
            'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/acces/exports/csv?limit=-1',
            config('fotometro.idfm.accesses_url')
        );
        $this->assertSame(
            'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/relations-acces/exports/csv?limit=-1',
            config('fotometro.idfm.access_relations_url')
        );
    }

    public function test_map_api_contains_imported_network_fields_without_source_payload(): void
    {
        app(NetworkImporter::class)->import($this->dataset());

        $response = $this->getJson('/api/map')->assertOk();
        $line = collect($response->json('lines'))->firstWhere('external_id', 'LINE:1');
        $station = collect($response->json('stations'))->firstWhere('external_id', 'IDFM:PUBLIC:9002');

        $this->assertSame('1', $line['code']);
        $this->assertSame([2.347, 48.8586], $station['coordinates']);
        $this->assertSame(1, $station['access_count']);
        $this->assertArrayNotHasKey('source_payload', $line);
        $this->assertArrayNotHasKey('source_payload', $station);
    }

    public function test_line_colors_are_imported_from_idfm_colourweb_fields(): void
    {
        app(NetworkImporter::class)->import([
            'lines' => [
                [
                    'id_line' => 'IDFM:C01371',
                    'shortname_line' => '1',
                    'name_line' => 'Ligne 1',
                    'transportmode' => 'metro',
                    'colourweb_hexa' => 'ffcd00',
                    'textcolourweb_hexa' => '111111',
                ],
                [
                    'id_line' => 'IDFM:C02874',
                    'shortname_line' => '15',
                    'name_line' => 'Ligne 15',
                    'transportmode' => 'metro',
                    'colourweb_hexa' => 'AAAAAA',
                    'textcolourweb_hexa' => '000000',
                ],
            ],
        ], ['only' => 'lines']);

        $line = Line::query()->where('code', '1')->firstOrFail();

        $this->assertSame('#FFCD00', $line->color);
        $this->assertSame('#111111', $line->text_color);
        $this->assertDatabaseMissing('lines', ['code' => '15']);
        $this->getJson('/api/map')
            ->assertOk()
            ->assertJsonPath('lines.0.color', '#FFCD00')
            ->assertJsonPath('lines.0.text_color', '#111111');
    }

    public function test_line_color_import_keeps_existing_invalid_values_and_falls_back_last(): void
    {
        Line::factory()->create([
            'external_id' => 'C01075',
            'code' => '5',
            'name' => 'Ligne 5',
            'slug' => 'ligne-5',
            'color' => '#123456',
            'text_color' => '#654321',
            'source' => 'idfm',
        ]);

        $report = app(NetworkImporter::class)->import([
            'lines' => [
                [
                    'id_line' => 'IDFM:C01075',
                    'shortname_line' => '5',
                    'name_line' => 'Ligne 5',
                    'transportmode' => 'metro',
                    'colourweb_hexa' => 'not-a-color',
                    'textcolourweb_hexa' => '',
                ],
                [
                    'id_line' => 'IDFM:C01390',
                    'shortname_line' => '14',
                    'name_line' => 'Ligne 14',
                    'transportmode' => 'metro',
                    'colourweb_hexa' => '',
                    'textcolourweb_hexa' => '',
                ],
            ],
        ], ['only' => 'lines']);

        $lineFive = Line::query()->where('code', '5')->firstOrFail();
        $lineFourteen = Line::query()->where('code', '14')->firstOrFail();

        $this->assertSame('#123456', $lineFive->color);
        $this->assertSame('#654321', $lineFive->text_color);
        $this->assertSame('#62259D', $lineFourteen->color);
        $this->assertSame('#FFFFFF', $lineFourteen->text_color);
        $this->assertSame(1, $report->lineInvalidColorsIgnored);
        $this->assertGreaterThanOrEqual(1, $report->lineColorsKept);
        $this->assertGreaterThanOrEqual(2, $report->lineColorFallbacksUsed);
    }

    public function test_line_without_geolocated_station_remains_safe_for_api(): void
    {
        app(NetworkImporter::class)->import([
            'arrets_lignes' => [
                [
                    'route_id' => 'IDFM:LINE:2',
                    'shortname' => '2',
                    'route_long_name' => 'Ligne 2',
                    'mode' => 'metro',
                    'stop_id' => 'IDFM:STOP:VOID',
                    'stop_name' => 'Station sans coordonnees',
                    'position' => 1,
                    'is_terminus' => true,
                ],
            ],
        ], ['skip_traces' => true, 'skip_accesses' => true]);

        $this->getJson('/api/map')
            ->assertOk()
            ->assertJsonPath('lines.0.code', '2')
            ->assertJsonPath('lines.0.progress.total', 0)
            ->assertJsonPath('progress.stations_without_coordinates', 0);
        $this->assertDatabaseHas('station_stops', ['external_id' => 'IDFM:STOP:VOID', 'station_id' => null]);
    }

    public function test_homepage_uses_horizontal_logo_path_and_diagram_names_below_line(): void
    {
        $this->get('/')->assertOk()->assertSee('map-logo-block', false);

        $logo = file_get_contents(resource_path('views/components/fotometro-logo.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString("asset('images/logo_fotometro.png')", $logo);
        $this->assertStringContainsString('max-w-[240px] object-contain', $logo);
        $this->assertStringContainsString('margin-top: 1.45rem;', $css);
        $this->assertStringContainsString('padding: 2.75rem 1rem 6rem;', $css);
    }

    private function dataset(): array
    {
        return [
            'arrets_lignes' => [
                [
                    'route_id' => 'IDFM:LINE:1',
                    'shortname' => '1',
                    'route_long_name' => 'Ligne 1',
                    'route_color' => 'FFCD00',
                    'route_text_color' => '111111',
                    'mode' => 'metro',
                    'stop_id' => 'IDFM:STOP:DEFENSE',
                    'stop_name' => 'La Defense',
                    'stop_lon' => 2.2384,
                    'stop_lat' => 48.8922,
                    'nom_commune' => 'Puteaux',
                    'position' => 1,
                    'branch' => 'main',
                    'is_terminus' => true,
                ],
                [
                    'route_id' => 'IDFM:LINE:1',
                    'shortname' => '1',
                    'route_long_name' => 'Ligne 1',
                    'route_color' => 'FFCD00',
                    'route_text_color' => '111111',
                    'mode' => 'metro',
                    'stop_id' => 'IDFM:STOP:CHATELET',
                    'stop_name' => 'Chatelet',
                    'stop_lon' => 2.347,
                    'stop_lat' => 48.8586,
                    'nom_commune' => 'Paris',
                    'position' => 8,
                    'branch' => 'main',
                    'is_terminus' => false,
                ],
                [
                    'route_id' => 'IDFM:LINE:6',
                    'shortname' => '6',
                    'route_long_name' => 'Ligne 6',
                    'route_color' => '79BB92',
                    'route_text_color' => '111111',
                    'mode' => 'metro',
                    'stop_id' => 'IDFM:STOP:CHATELET',
                    'stop_name' => 'Chatelet',
                    'stop_lon' => 2.347,
                    'stop_lat' => 48.8586,
                    'nom_commune' => 'Paris',
                    'position' => 12,
                    'branch' => 'main',
                    'is_terminus' => false,
                ],
                [
                    'route_id' => 'IDFM:LINE:6',
                    'shortname' => '6',
                    'route_long_name' => 'Ligne 6',
                    'route_color' => '79BB92',
                    'route_text_color' => '111111',
                    'mode' => 'metro',
                    'stop_id' => 'IDFM:STOP:NATION',
                    'stop_name' => 'Nation',
                    'stop_lon' => 2.3959,
                    'stop_lat' => 48.8484,
                    'nom_commune' => 'Paris',
                    'position' => 28,
                    'branch' => 'main',
                    'is_terminus' => true,
                ],
            ],
            'traces' => [
                [
                    'route_id' => 'IDFM:LINE:1',
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[2.2384, 48.8922], [2.347, 48.8586]],
                    ],
                ],
                [
                    'route_id' => 'IDFM:LINE:6',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [2.3959, 48.8484],
                    ],
                ],
            ],
            'accesses' => [
                [
                    'access_id' => 'IDFM:ACCESS:1',
                    'name' => 'Sortie 1',
                    'reference' => '1',
                    'lat' => 48.8587,
                    'lon' => 2.3471,
                    'type' => 'stairs',
                    'street' => 'Rue de Rivoli',
                    'wheelchair' => 'yes',
                ],
            ],
            'access_station' => [
                ['access_id' => 'IDFM:ACCESS:1', 'zdaid' => '200'],
                ['access_id' => 'IDFM:ACCESS:1', 'zdaid' => '300'],
            ],
            'stop_areas' => [
                ['zdaid' => '100', 'zdcid' => '9001', 'zdaname' => 'La Defense', 'zdatown' => 'Puteaux', 'zdapostalregion' => '92062'],
                ['zdaid' => '200', 'zdcid' => '9002', 'zdaname' => 'Chatelet', 'zdatown' => 'Paris', 'zdapostalregion' => '75056'],
                ['zdaid' => '300', 'zdcid' => '9003', 'zdaname' => 'Nation', 'zdatown' => 'Paris', 'zdapostalregion' => '75056'],
            ],
            'stop_relations' => [
                ['arrid' => 'STOP:DEFENSE', 'zdaid' => '100'],
                ['arrid' => 'STOP:CHATELET', 'zdaid' => '200'],
                ['arrid' => 'STOP:NATION', 'zdaid' => '300'],
            ],
        ];
    }
}

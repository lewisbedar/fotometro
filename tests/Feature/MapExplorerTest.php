<?php

namespace Tests\Feature;

use App\Enums\CoverageStatus;
use App\Models\Line;
use App\Models\Station;
use Database\Seeders\LineStationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MapExplorerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_homepage_displays_map_explorer_and_progress(): void
    {
        $this->seed(LineStationSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Catalogue photographique des stations du métro parisien')
            ->assertSee('Progression globale : 40 %')
            ->assertSee('Ligne 1');
    }

    public function test_raster_driver_is_the_default_basemap_configuration(): void
    {
        $mapConfig = config('fotometro.map');

        $this->assertSame('raster', $mapConfig['basemap_driver']);
        $this->assertSame('https://tile.openstreetmap.org/{z}/{x}/{y}.png', $mapConfig['raster_url']);
        $this->assertSame(256, $mapConfig['raster_tile_size']);
        $this->assertEquals(19, $mapConfig['center']['max_zoom']);
    }

    public function test_homepage_receives_raster_basemap_configuration(): void
    {
        $this->seed(LineStationSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-basemap-driver="raster"', false)
            ->assertSee('data-raster-url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"', false)
            ->assertSee('data-raster-tile-size="256"', false)
            ->assertSee('data-map-max-zoom="19"', false);
    }

    public function test_homepage_displays_configuration_message_when_raster_url_is_empty(): void
    {
        $this->seed(LineStationSeeder::class);

        config([
            'fotometro.map.basemap_driver' => 'raster',
            'fotometro.map.raster_url' => '',
            'fotometro.map.style_url' => 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-basemap-driver="raster"', false)
            ->assertSee('data-raster-url=""', false)
            ->assertSee('FOTOMETRO_MAP_RASTER_URL');
    }

    public function test_style_driver_remains_available(): void
    {
        $this->seed(LineStationSeeder::class);

        config([
            'fotometro.map.basemap_driver' => 'style',
            'fotometro.map.style_url' => 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json',
            'fotometro.map.raster_url' => '',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-basemap-driver="style"', false)
            ->assertSee('data-map-style="https://basemaps.cartocdn.com/gl/positron-gl-style/style.json"', false);
    }

    public function test_invalid_basemap_driver_is_normalized_to_raster(): void
    {
        $original = getenv('FOTOMETRO_MAP_BASEMAP_DRIVER');

        putenv('FOTOMETRO_MAP_BASEMAP_DRIVER=invalid');
        $_ENV['FOTOMETRO_MAP_BASEMAP_DRIVER'] = 'invalid';
        $_SERVER['FOTOMETRO_MAP_BASEMAP_DRIVER'] = 'invalid';

        $mapConfig = require config_path('fotometro.php');

        $this->assertSame('raster', $mapConfig['map']['basemap_driver']);

        if ($original === false) {
            putenv('FOTOMETRO_MAP_BASEMAP_DRIVER');
            unset($_ENV['FOTOMETRO_MAP_BASEMAP_DRIVER'], $_SERVER['FOTOMETRO_MAP_BASEMAP_DRIVER']);
        } else {
            putenv("FOTOMETRO_MAP_BASEMAP_DRIVER={$original}");
            $_ENV['FOTOMETRO_MAP_BASEMAP_DRIVER'] = $original;
            $_SERVER['FOTOMETRO_MAP_BASEMAP_DRIVER'] = $original;
        }
    }

    public function test_public_pages_use_the_same_raster_map_configuration(): void
    {
        $this->seed(LineStationSeeder::class);

        $expected = [
            'data-basemap-driver="raster"',
            'data-raster-url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"',
            'data-raster-tile-size="256"',
            'data-map-max-zoom="19"',
        ];

        foreach (['/', '/stations/chatelet', '/lignes/ligne-1'] as $uri) {
            $response = $this->get($uri)->assertOk();

            foreach ($expected as $html) {
                $response->assertSee($html, false);
            }
        }
    }

    public function test_map_endpoint_returns_public_lines_and_unique_active_stations_with_coordinates(): void
    {
        $this->seed(LineStationSeeder::class);

        $inactiveStation = Station::factory()->create([
            'name' => 'Station inactive',
            'slug' => 'station-inactive',
            'is_active' => false,
        ]);
        $line = Line::first();
        $inactiveStation->lines()->attach($line, ['position' => 99, 'is_terminus' => false]);

        $missingCoordinates = Station::factory()->create([
            'name' => 'Station sans coordonnées',
            'slug' => 'station-sans-coordonnees',
            'latitude' => null,
            'longitude' => null,
            'is_active' => true,
        ]);
        $missingCoordinates->lines()->attach($line, ['position' => 100, 'is_terminus' => false]);

        $response = $this->getJson('/api/map')
            ->assertOk()
            ->assertJsonPath('progress.stations_without_coordinates', 1)
            ->assertJsonMissing(['slug' => 'station-inactive'])
            ->assertJsonMissing(['slug' => 'station-sans-coordonnees']);

        $stations = collect($response->json('stations'));

        $this->assertSame(5, $stations->count());
        $this->assertSame(1, $stations->where('slug', 'chatelet')->count());
        $this->assertCount(3, $stations->firstWhere('slug', 'chatelet')['lines']);
        $this->assertNull($response->json('lines.0.path_geojson'));
    }

    public function test_each_demo_line_exposes_a_distinct_station_list(): void
    {
        $this->seed(LineStationSeeder::class);

        $stations = collect($this->getJson('/api/map')->assertOk()->json('stations'));

        $stationSlugsByLine = collect(['1', '4', '6', '14'])->mapWithKeys(fn (string $code) => [
            $code => $stations
                ->filter(fn (array $station) => collect($station['lines'])->contains('code', $code))
                ->pluck('slug')
                ->values()
                ->all(),
        ]);

        $this->assertSame(['bastille', 'chatelet', 'nation'], $stationSlugsByLine['1']);
        $this->assertSame(['chatelet', 'montparnasse-bienvenue'], $stationSlugsByLine['4']);
        $this->assertSame(['montparnasse-bienvenue', 'nation'], $stationSlugsByLine['6']);
        $this->assertSame(['chatelet', 'olympiades'], $stationSlugsByLine['14']);
        $this->assertSame(4, $stationSlugsByLine->unique(fn (array $slugs) => implode('|', $slugs))->count());
    }

    public function test_line_station_coordinates_are_distinct_between_lines_one_and_six(): void
    {
        $this->seed(LineStationSeeder::class);

        $stations = collect($this->getJson('/api/map')->assertOk()->json('stations'));

        $coordinatesForLine = fn (string $code) => $stations
            ->filter(fn (array $station) => collect($station['lines'])->contains('code', $code))
            ->map(fn (array $station) => $station['coordinates'])
            ->values()
            ->all();

        $lineOneCoordinates = $coordinatesForLine('1');
        $lineSixCoordinates = $coordinatesForLine('6');

        $this->assertSame([[2.3691, 48.853], [2.347, 48.8586], [2.3959, 48.8484]], $lineOneCoordinates);
        $this->assertSame([[2.3226, 48.8437], [2.3959, 48.8484]], $lineSixCoordinates);
        $this->assertNotSame($lineOneCoordinates, $lineSixCoordinates);
    }

    public function test_demo_lines_without_geojson_fallback_to_station_coordinates(): void
    {
        $this->seed(LineStationSeeder::class);

        $payload = $this->getJson('/api/map')->assertOk()->json();
        $line = collect($payload['lines'])->firstWhere('code', '6');
        $coordinates = collect($payload['stations'])
            ->filter(fn (array $station) => collect($station['lines'])->contains('id', $line['id']))
            ->map(fn (array $station) => $station['coordinates'])
            ->values()
            ->all();

        $this->assertNull($line['path_geojson']);
        $this->assertSame([[2.3226, 48.8437], [2.3959, 48.8484]], $coordinates);
    }

    public function test_line_without_geolocated_station_does_not_break_map_endpoint(): void
    {
        $line = Line::factory()->create(['code' => '99', 'slug' => 'ligne-99', 'path_geojson' => null]);
        $station = Station::factory()->create([
            'name' => 'Station sans coordonnees',
            'slug' => 'station-sans-coordonnees',
            'latitude' => null,
            'longitude' => null,
            'is_active' => true,
        ]);

        $station->lines()->attach($line, ['position' => 1, 'is_terminus' => true]);

        $this->getJson('/api/map')
            ->assertOk()
            ->assertJsonPath('lines.0.code', '99')
            ->assertJsonPath('lines.0.path_geojson', null)
            ->assertJsonCount(0, 'stations')
            ->assertJsonPath('progress.stations_without_coordinates', 1);
    }

    public function test_map_endpoint_returns_station_coordinates_as_longitude_then_latitude(): void
    {
        $this->seed(LineStationSeeder::class);

        $station = collect($this->getJson('/api/map')->assertOk()->json('stations'))
            ->firstWhere('slug', 'chatelet');

        $this->assertSame([2.347, 48.8586], $station['coordinates']);
        $this->assertSame(48.8586, $station['latitude']);
        $this->assertSame(2.347, $station['longitude']);
    }

    public function test_station_search_is_case_and_accent_insensitive(): void
    {
        $this->seed(LineStationSeeder::class);

        $this->getJson('/api/map/search?q=châtelet')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'chatelet')
            ->assertJsonPath('data.0.lines.0.code', '1');
    }

    public function test_map_endpoint_always_returns_station_lines_as_zero_indexed_arrays(): void
    {
        $this->seed(LineStationSeeder::class);

        $stationWithoutLine = Station::factory()->create([
            'name' => 'Station orpheline',
            'slug' => 'station-orpheline',
            'latitude' => 48.86,
            'longitude' => 2.35,
            'is_active' => true,
        ]);

        $stations = collect($this->getJson('/api/map')->assertOk()->json('stations'));
        $interchangeLines = $stations->firstWhere('slug', 'chatelet')['lines'];
        $orphanLines = $stations->firstWhere('slug', $stationWithoutLine->slug)['lines'];

        $this->assertIsArray($interchangeLines);
        $this->assertTrue(array_is_list($interchangeLines));
        $this->assertSame([0, 1, 2], array_keys($interchangeLines));
        $this->assertIsArray($orphanLines);
        $this->assertSame([], $orphanLines);
    }

    public function test_search_endpoint_always_returns_station_lines_as_arrays(): void
    {
        $stationWithoutLine = Station::factory()->create([
            'name' => 'Station Orpheline',
            'slug' => 'station-orpheline',
            'latitude' => 48.86,
            'longitude' => 2.35,
            'is_active' => true,
        ]);

        $lines = $this->getJson('/api/map/search?q=orpheline')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $stationWithoutLine->slug)
            ->json('data.0.lines');

        $this->assertIsArray($lines);
        $this->assertTrue(array_is_list($lines));
        $this->assertSame([], $lines);
    }

    public function test_station_page_is_public_and_unknown_slug_returns_404(): void
    {
        $this->seed(LineStationSeeder::class);

        $this->get('/stations/chatelet')
            ->assertOk()
            ->assertSee('Chatelet')
            ->assertSee('Aucune photographie publiée pour cette station.');

        $this->get('/stations/inconnue')->assertNotFound();
    }

    public function test_line_page_displays_ordered_stations_and_progress(): void
    {
        $this->seed(LineStationSeeder::class);

        $response = $this->get('/lignes/ligne-1')
            ->assertOk()
            ->assertSee('Ligne 1')
            ->assertSee('67 % de couverture');

        $response->assertSeeInOrder(['Chatelet', 'Bastille', 'Nation']);
    }

    public function test_inactive_station_page_returns_404(): void
    {
        $station = Station::factory()->create([
            'slug' => 'station-inactive',
            'is_active' => false,
            'coverage_status' => CoverageStatus::NotStarted,
        ]);

        $this->get(route('stations.show', $station))->assertNotFound();
    }
}

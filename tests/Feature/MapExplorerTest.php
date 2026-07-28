<?php

namespace Tests\Feature;

use App\Enums\CoverageStatus;
use App\Models\Line;
use App\Models\Station;
use Database\Seeders\LineStationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MapExplorerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_homepage_displays_fullscreen_map_explorer(): void
    {
        $this->seed(LineStationSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('fullscreen-map-shell', false)
            ->assertSee('id="metro-map"', false)
            ->assertSee('fullscreen-map-topbar', false)
            ->assertSee('Progression globale')
            ->assertSee('Rechercher une station')
            ->assertSee('Lignes')
            ->assertSee('Filtres')
            ->assertSee('À propos');
    }

    public function test_homepage_renders_floating_panels_and_diagram_shell(): void
    {
        $this->seed(LineStationSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('map-progress-panel', false)
            ->assertSee('id="filters-panel"', false)
            ->assertSee('id="lines-panel"', false)
            ->assertSee('map-context-panel', false)
            ->assertSee('line-diagram-panel', false)
            ->assertSee('map-about-modal', false)
            ->assertSee('Île-de-France Mobilités')
            ->assertSee('OpenStreetMap contributors')
            ->assertSee('Entrees et sorties')
            ->assertSee('Bientot disponible');
    }

    public function test_line_diagram_svg_is_guarded_by_layout_and_not_rendered_with_svg_x_for(): void
    {
        $partial = file_get_contents(resource_path('views/partials/map/line-diagram-svg.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('x-if="hasSelectedLineLayout"', $partial);
        $this->assertStringContainsString('x-ref="lineDiagramSvgHost"', $partial);
        $this->assertStringNotContainsString('selectedLine.topology.layout.segments', $partial);
        $this->assertStringNotContainsString('selectedLine.topology.layout.stations', $partial);
        $this->assertStringNotContainsString('<template x-for="segment', $partial);
        $this->assertStringContainsString('get hasSelectedLineLayout()', $js);
        $this->assertStringContainsString('renderSelectedLineDiagram()', $js);
        $this->assertStringContainsString("document.createElementNS('http://www.w3.org/2000/svg'", $js);
    }

    public function test_map_search_route_uses_dedicated_rate_limiter(): void
    {
        $middleware = Route::getRoutes()->getByName('api.map.search')->gatherMiddleware();
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertContains('throttle:map-search', $middleware);
        $this->assertStringContainsString("RateLimiter::for('map-search'", $provider);
        $this->assertStringContainsString('Limit::perMinute(120)', $provider);
    }

    public function test_map_search_allows_normal_fast_typing_without_legacy_thirty_request_limit(): void
    {
        $this->seed(LineStationSeeder::class);

        for ($attempt = 0; $attempt < 35; $attempt++) {
            $this->getJson('/api/map/search?q=nation')->assertOk();
        }
    }

    public function test_map_search_ui_uses_local_debounced_search(): void
    {
        $partial = file_get_contents(resource_path('views/partials/map/topbar.blade.php'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('x-on:input="queueSearch()"', $partial);
        $this->assertStringContainsString('searchTimer', $js);
        $this->assertStringContainsString('setTimeout(() =>', $js);
        $this->assertStringContainsString('}, 300)', $js);
        $this->assertStringContainsString('normalizeSearchText(value)', $js);
        $this->assertStringContainsString(".replace(/\\p{Diacritic}/gu, '')", $js);
        $this->assertStringContainsString('finally {', $js);
        $this->assertStringContainsString('this.searchLoading = false;', $js);
        $this->assertStringNotContainsString('new URL(this.searchEndpoint', $js);
    }

    public function test_line_geojson_normalizer_outputs_valid_maplibre_feature_collection(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $explorerJs = str($js)->before('window.fotometroPhotoForm = function fotometroPhotoForm')->toString();

        $this->assertStringContainsString('let mapInstance = null;', $explorerJs);
        $this->assertStringContainsString("const maplibreWorkerUrl = '/vendor/maplibre-gl/maplibre-gl-worker.mjs';", $js);
        $this->assertStringContainsString('maplibregl.setWorkerUrl?.(maplibreWorkerUrl);', $explorerJs);
        $this->assertStringContainsString('getMap()', $explorerJs);
        $this->assertStringNotContainsString('map: null,', $explorerJs);
        $this->assertStringNotContainsString('maplibregl: null,', $explorerJs);
        $this->assertStringContainsString("type: 'FeatureCollection'", $js);
        $this->assertStringContainsString('normalizeLineGeoJsonFeatures(pathGeojson)', $js);
        $this->assertStringContainsString("pathGeojson.type === 'FeatureCollection'", $js);
        $this->assertStringContainsString("['LineString', 'MultiLineString'].includes", $js);
        $this->assertStringContainsString('flatMap((line) => this.normalizeLineGeoJsonFeatures', $js);
        $this->assertStringContainsString(".map((coordinates) => ({ type: 'LineString', coordinates }))", $js);
        $this->assertStringContainsString("'line-cap': 'round'", $js);
        $this->assertStringContainsString("'line-join': 'round'", $js);
        $this->assertStringContainsString("'line-color': this.debugLinesEnabled ? '#ff0000' : ['coalesce', ['get', 'color'], '#ff0000']", $js);
        $this->assertStringContainsString("'line-width': this.debugLinesEnabled ? 12 : ['case', ['==', ['get', 'selected'], true], 8, 5]", $js);
        $this->assertStringContainsString('[fotometro] line feature count', $js);
        $this->assertStringContainsString("console.group('[fotometro] line layer diagnostic')", $js);
        $this->assertStringContainsString('rendered line features', $js);
        $this->assertStringContainsString('isPlausibleParisCoordinate(coordinate)', $js);
        $this->assertStringContainsString("this.getMap().moveLayer('fotometro-lines-layer', 'fotometro-stations-layer')", $js);
        $this->assertStringContainsString("this.getMap().moveLayer('fotometro-stations-layer')", $js);
        $this->assertStringContainsString('const maplibregl = this.getMapLibre();', $explorerJs);
        $this->assertStringContainsString('new maplibregl.LngLatBounds', $explorerJs);
        $this->assertStringContainsString('new maplibregl.Popup', $explorerJs);
        $this->assertStringNotContainsString('new this.getMapLibre().', $explorerJs);
        $this->assertStringContainsString('container.__fotometroMapInstance', $explorerJs);
    }

    public function test_isolated_line_diagnostic_page_is_available_only_locally(): void
    {
        $this->seed(LineStationSeeder::class);
        $this->app->detectEnvironment(fn () => 'local');

        $this->get('/map-line-diagnostic')
            ->assertOk()
            ->assertSee('map-line-diagnostic-canvas', false);

        $this->assertFileExists(resource_path('js/map-line-diagnostic.js'));

        $diagnosticJs = file_get_contents(resource_path('js/map-line-diagnostic.js'));

        $this->assertStringContainsString("params.get('dataset') || 'all'", $diagnosticJs);
        $this->assertStringContainsString("mode === 'minimal'", $diagnosticJs);
        $this->assertStringContainsString('line1-single', $diagnosticJs);
        $this->assertStringContainsString('line1-multi', $diagnosticJs);
        $this->assertStringContainsString('line1-no-properties', $diagnosticJs);
        $this->assertStringContainsString("params.get('basemap') === 'none'", $diagnosticJs);
        $this->assertStringContainsString('validationReport(payload)', $diagnosticJs);
        $this->assertStringContainsString("finalize('timeout')", $diagnosticJs);
        $this->assertStringContainsString('visible: renderedFeatures > 0', $diagnosticJs);
        $this->assertStringContainsString("console.log('[fotometro diagnostic] maplibre version'", $diagnosticJs);
        $this->assertStringContainsString('maplibregl.getVersion?.()', $diagnosticJs);
        $this->assertStringContainsString("const maplibreWorkerUrl = '/vendor/maplibre-gl/maplibre-gl-worker.mjs';", $diagnosticJs);
        $this->assertStringContainsString('maplibregl.setWorkerUrl?.(maplibreWorkerUrl);', $diagnosticJs);
        $this->assertStringContainsString("recordError('[fotometro diagnostic] MAP ERROR'", $diagnosticJs);

        $this->get('/debug/diagram-container')
            ->assertOk()
            ->assertSee('line-diagram-scroll', false)
            ->assertSee('width="3000"', false);
    }

    public function test_line_diagram_svg_is_constrained_to_horizontal_scroll_panel(): void
    {
        $partial = file_get_contents(resource_path('views/partials/map/line-diagram-svg.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('class="line-diagram-scroll', $partial);
        $this->assertStringContainsString('class="line-diagram-content"', $partial);
        $this->assertStringContainsString('x-ref="lineDiagramScroll"', $partial);
        $this->assertStringContainsString('class="line-diagram-host"', $partial);
        $this->assertStringContainsString('.line-diagram-panel', $css);
        $this->assertStringContainsString('overflow: hidden;', $css);
        $this->assertStringContainsString('display: flex;', $css);
        $this->assertStringContainsString('flex-direction: column;', $css);
        $this->assertStringContainsString('.line-diagram-content', $css);
        $this->assertStringContainsString('.line-diagram-scroll', $css);
        $this->assertStringContainsString('min-width: 0;', $css);
        $this->assertStringContainsString('overflow-x: auto;', $css);
        $this->assertStringContainsString('overflow-y: auto;', $css);
        $this->assertStringContainsString('scrollbar-width: auto !important;', $css);
        $this->assertStringContainsString('display: block !important;', $css);
        $this->assertStringContainsString('.line-diagram-host', $css);
        $this->assertStringContainsString('width: max-content;', $css);
        $this->assertStringContainsString('min-width: max-content;', $css);
        $this->assertStringContainsString('max-width: none;', $css);
        $this->assertStringContainsString('const scroller = this.$refs.lineDiagramScroll;', $js);
        $this->assertStringContainsString('scroller.scrollTo({', $js);
        $this->assertStringContainsString('left: Math.max(0, left)', $js);
        $this->assertStringContainsString('[fotometro] diagram scroll', $js);
        $this->assertStringContainsString('debugRealDiagram()', $js);
        $this->assertStringContainsString("[fotometro] real diagram diagnostic", $js);
        $this->assertStringContainsString('elementFromPoint', $js);
        $this->assertStringNotContainsString('x-transition', $partial);
        $this->assertStringNotContainsString('lineDiagramScroller', $js);
    }

    public function test_line_diagram_layout_coordinates_are_numeric_when_exposed(): void
    {
        $this->seed(LineStationSeeder::class);

        collect($this->getJson('/api/map')->assertOk()->json('lines'))->each(function (array $line): void {
            $layout = $line['topology']['layout'] ?? null;

            if (! $layout) {
                return;
            }

            foreach ($layout['segments'] ?? [] as $segment) {
                $this->assertIsNumeric($segment['x1']);
                $this->assertIsNumeric($segment['y1']);
                $this->assertIsNumeric($segment['x2']);
                $this->assertIsNumeric($segment['y2']);
            }

            foreach ($layout['stations'] ?? [] as $station) {
                $this->assertIsNumeric($station['x']);
                $this->assertIsNumeric($station['y']);
                $this->assertIsNumeric($station['label_x']);
                $this->assertIsNumeric($station['label_y']);
            }
        });
    }

    public function test_homepage_logo_container_is_not_stretched(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.map-logo-block', $css);
        $this->assertStringContainsString('width: fit-content;', $css);
        $this->assertStringContainsString('display: inline-flex;', $css);
        $this->assertStringContainsString('grid-template-columns: auto minmax(18rem, 34rem) auto;', $css);
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

        $staticMapAttributes = [
            'data-basemap-driver="raster"',
            'data-raster-url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"',
            'data-raster-tile-size="256"',
            'data-map-max-zoom="19"',
        ];

        foreach (['/', '/lignes/ligne-1'] as $uri) {
            $response = $this->get($uri)->assertOk();

            foreach ($staticMapAttributes as $html) {
                $response->assertSee($html, false);
            }
        }

        // The station page has no standalone static map (merged into the
        // accesses map), so its raster config only appears in the Alpine
        // component's JSON payload. Js::from() escapes quotes as "
        // (built here via chr(92) to dodge string-escaping gymnastics) for
        // safe embedding in a double-quoted HTML attribute; normalize that
        // back to plain quotes before matching.
        $unicodeQuote = chr(92).'u0022';
        $content = str_replace($unicodeQuote, '"', $this->get('/stations/chatelet')->assertOk()->getContent());

        foreach ([
            '"basemapDriver":"raster"',
            '"rasterUrl":"https:',
            '"rasterTileSize":256',
            '"maxZoom":19',
        ] as $json) {
            $this->assertStringContainsString($json, $content);
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
            'name' => 'Station sans coordonnees',
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

    public function test_map_endpoint_exposes_line_geometry_when_available(): void
    {
        $line = Line::factory()->create([
            'code' => '42',
            'slug' => 'ligne-42',
            'path_geojson' => [
                'type' => 'LineString',
                'coordinates' => [[2.34, 48.85], [2.36, 48.86]],
            ],
        ]);
        $station = Station::factory()->create([
            'latitude' => 48.85,
            'longitude' => 2.34,
            'is_active' => true,
        ]);
        $station->lines()->attach($line, ['position' => 1]);

        $payload = collect($this->getJson('/api/map')->assertOk()->json('lines'))->firstWhere('code', '42');

        $this->assertSame('LineString', $payload['path_geojson']['type']);
        $this->assertSame([[2.34, 48.85], [2.36, 48.86]], $payload['path_geojson']['coordinates']);
        $this->assertArrayHasKey('layout', $payload['topology']);
    }

    public function test_map_api_contains_ordered_line_stations_for_diagram(): void
    {
        $this->seed(LineStationSeeder::class);

        $line = collect($this->getJson('/api/map')->assertOk()->json('lines'))->firstWhere('code', '1');

        $this->assertSame(['Chatelet', 'Bastille', 'Nation'], collect($line['stations'])->pluck('name')->all());
        $this->assertSame([8, 12, 18], collect($line['stations'])->pluck('position')->all());
    }

    public function test_map_api_marks_terminus_and_omits_active_line_from_connections(): void
    {
        $this->seed(LineStationSeeder::class);

        $line = collect($this->getJson('/api/map')->assertOk()->json('lines'))->firstWhere('code', '6');
        $nation = collect($line['stations'])->firstWhere('slug', 'nation');

        $this->assertTrue($nation['is_terminus']);
        $this->assertSame(['1'], collect($nation['connections'])->pluck('code')->all());
        $this->assertNotContains('6', collect($nation['connections'])->pluck('code')->all());
    }

    public function test_map_api_exposes_coverage_statuses_for_diagram_nodes(): void
    {
        $this->seed(LineStationSeeder::class);

        $station = collect($this->getJson('/api/map')->assertOk()->json('lines'))
            ->firstWhere('code', '14')['stations'][0];

        $this->assertArrayHasKey('coverage_status', $station);
        $this->assertContains($station['coverage_status']['value'], [
            'not_started',
            'planned',
            'in_progress',
            'documented',
            'complete',
        ]);
    }

    public function test_map_api_handles_line_without_station_for_diagram(): void
    {
        Line::factory()->create(['code' => '99', 'name' => 'Ligne 99', 'slug' => 'ligne-99']);

        $line = collect($this->getJson('/api/map')->assertOk()->json('lines'))->firstWhere('code', '99');

        $this->assertSame([], $line['stations']);
        $this->assertSame(0, $line['progress']['total']);
        $this->assertSame(0, $line['progress']['percentage']);
    }

    public function test_map_api_handles_line_with_single_station_for_diagram(): void
    {
        $line = Line::factory()->create(['code' => '98', 'name' => 'Ligne 98', 'slug' => 'ligne-98']);
        $station = Station::factory()->create([
            'name' => 'Station unique',
            'slug' => 'station-unique',
            'latitude' => 48.86,
            'longitude' => 2.35,
            'is_active' => true,
        ]);

        $station->lines()->attach($line, ['position' => 1, 'is_terminus' => true]);

        $linePayload = collect($this->getJson('/api/map')->assertOk()->json('lines'))->firstWhere('code', '98');

        $this->assertCount(1, $linePayload['stations']);
        $this->assertSame('Station unique', $linePayload['stations'][0]['name']);
        $this->assertTrue($linePayload['stations'][0]['is_terminus']);
    }

    public function test_seeded_line_colors_are_hex_values_for_safe_rendering(): void
    {
        $this->seed(LineStationSeeder::class);

        collect($this->getJson('/api/map')->assertOk()->json('lines'))->each(function (array $line): void {
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $line['color']);
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $line['text_color']);
        });
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

        $station = collect($this->getJson('/api/map')->assertOk()->json('stations'))->firstWhere('slug', 'chatelet');

        $this->assertSame([2.347, 48.8586], $station['coordinates']);
        $this->assertSame(48.8586, $station['latitude']);
        $this->assertSame(2.347, $station['longitude']);
    }

    public function test_station_search_is_case_and_accent_insensitive(): void
    {
        $this->seed(LineStationSeeder::class);

        $this->getJson('/api/map/search?q=chatelet')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'chatelet')
            ->assertJsonPath('data.0.lines.0.code', '1');

        $this->getJson('/api/map/search?q=cha')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'chatelet');

        $this->getJson('/api/map/search?q=ch%C3%A2telet')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'chatelet');
    }

    public function test_search_endpoint_returns_line_results_separately(): void
    {
        $this->seed(LineStationSeeder::class);

        $this->getJson('/api/map/search?q=ligne%201')
            ->assertOk()
            ->assertJsonPath('lines.0.code', '1')
            ->assertJsonPath('lines.0.name', 'Ligne 1');
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
            ->assertSee('Aucune photographie');

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

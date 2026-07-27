<?php

namespace Tests\Feature;

use App\Services\Idfm\IdfmClient;
use App\Services\Idfm\NetworkImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class IdfmClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_pagination_below_ten_thousand_is_supported(): void
    {
        Http::fake([
            'https://example.test/datasets/small/records?limit=2' => Http::response([
                'total_count' => 4,
                'results' => [
                    ['route_id' => 'IDFM:LINE:1'],
                    ['route_id' => 'IDFM:LINE:2'],
                ],
            ]),
            'https://example.test/datasets/small/records?limit=2&offset=2' => Http::response([
                'total_count' => 4,
                'results' => [
                    ['route_id' => 'IDFM:LINE:3'],
                    ['route_id' => 'IDFM:LINE:4'],
                ],
            ]),
        ]);

        $payload = app(IdfmClient::class)->fetch('https://example.test/datasets/small/records?limit=2');

        $this->assertCount(4, $payload['results']);
    }

    public function test_records_pagination_stops_before_ten_thousand_limit(): void
    {
        Http::fake([
            'https://example.test/datasets/large/records?limit=100' => Http::response([
                'total_count' => 10001,
                'results' => array_fill(0, 100, ['route_id' => 'IDFM:LINE:1']),
            ]),
            '*' => Http::response([
                'total_count' => 10001,
                'results' => array_fill(0, 100, ['route_id' => 'IDFM:LINE:1']),
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('IDFM records pagination reached the 10,000-record API limit. Use the exports endpoint.');

        app(IdfmClient::class)->fetch('https://example.test/datasets/large/records?limit=100');
    }

    public function test_arrets_lignes_uses_exports_csv_automatically(): void
    {
        config(['fotometro.idfm.temp_dir' => storage_path('app/testing-idfm')]);

        Http::fake([
            'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/exports/csv?limit=-1' => Http::response($this->csv([
                ['route_id', 'shortname', 'route_long_name', 'mode', 'stop_id', 'stop_name', 'stop_lon', 'stop_lat'],
                ['IDFM:LINE:1', '1', 'Ligne 1', 'metro', 'IDFM:STOP:A', 'Station A', '2.35', '48.85'],
            ]), 200),
        ]);

        $payload = app(IdfmClient::class)->fetchComplete('https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?limit=100');

        $this->assertSame('IDFM:LINE:1', $payload['results']->first()['route_id']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/exports/csv?limit=-1'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'offset=10000'));
    }

    public function test_local_json_and_csv_exports_are_processed(): void
    {
        $jsonPath = storage_path('app/testing-idfm-export.json');
        $csvPath = storage_path('app/testing-idfm-export.csv');

        file_put_contents($jsonPath, json_encode([['route_id' => 'IDFM:LINE:1']], JSON_THROW_ON_ERROR));
        file_put_contents($csvPath, $this->csv([
            ['route_id', 'shortname', 'route_long_name', 'mode', 'stop_id', 'stop_name'],
            ['IDFM:LINE:6', '6', 'Ligne 6', 'metro', 'IDFM:STOP:B', 'Station B'],
        ]));

        $client = app(IdfmClient::class);

        $this->assertSame('IDFM:LINE:1', $client->fetchComplete("file://{$jsonPath}")['results'][0]['route_id']);
        $this->assertSame('IDFM:LINE:6', $client->fetchComplete("file://{$csvPath}")['results']->first()['route_id']);
    }

    public function test_real_arrets_lignes_headers_are_supported(): void
    {
        $path = storage_path('app/testing-idfm-real-headers.csv');
        file_put_contents($path, $this->csv([
            ['id', 'route_long_name', 'stop_id', 'stop_name', 'stop_lon', 'stop_lat', 'operatorname', 'shortname', 'bookingrules', 'mode', 'pointgeo', 'nom_commune', 'code_insee'],
            ['IDFM:C01371', 'Ligne 1', 'IDFM:STOP:A', 'Station A', '2.35', '48.85', 'RATP', '1', '', 'metro', '', 'Paris', '75056'],
        ]));

        $payload = app(IdfmClient::class)->fetchComplete("file://{$path}");

        $this->assertSame(['id', 'route_long_name', 'stop_id', 'stop_name', 'stop_lon', 'stop_lat', 'operatorname', 'shortname', 'bookingrules', 'mode', 'pointgeo', 'nom_commune', 'code_insee'], $payload['_headers']);
        $this->assertSame('IDFM:C01371', $payload['results']->first()['id']);
    }

    public function test_import_more_than_ten_thousand_records_from_local_csv_and_keeps_only_metro(): void
    {
        $path = storage_path('app/testing-idfm-large.csv');
        $rows = [['route_id', 'shortname', 'route_long_name', 'mode', 'stop_id', 'stop_name', 'stop_lon', 'stop_lat', 'position']];

        for ($index = 1; $index <= 10005; $index++) {
            $isMetro = $index <= 3;
            $rows[] = [
                $isMetro ? 'IDFM:LINE:1' : 'IDFM:BUS:'.$index,
                $isMetro ? '1' : 'BUS',
                $isMetro ? 'Ligne 1' : 'Bus '.$index,
                $isMetro ? 'metro' : 'bus',
                'IDFM:STOP:'.$index,
                'Station '.$index,
                '2.35',
                '48.85',
                (string) $index,
            ];
        }

        file_put_contents($path, $this->csv($rows));

        $report = app(NetworkImporter::class)->import([
            'arrets_lignes' => app(IdfmClient::class)->fetchComplete("file://{$path}"),
            'stop_areas' => [
                'results' => collect([
                    ['zdaid' => '1', 'zdcid' => '9001', 'zdaname' => 'Station 1'],
                    ['zdaid' => '2', 'zdcid' => '9002', 'zdaname' => 'Station 2'],
                    ['zdaid' => '3', 'zdcid' => '9003', 'zdaname' => 'Station 3'],
                ]),
            ],
            'stop_relations' => [
                'results' => collect([
                    ['arrid' => 'STOP:1', 'zdaid' => '1'],
                    ['arrid' => 'STOP:2', 'zdaid' => '2'],
                    ['arrid' => 'STOP:3', 'zdaid' => '3'],
                ]),
            ],
        ], ['skip_traces' => true, 'skip_accesses' => true]);

        $this->assertSame(10005, $report->rawRecords);
        $this->assertSame(3, $report->retainedRecords);
        $this->assertDatabaseCount('lines', 1);
        $this->assertDatabaseCount('stations', 3);
        $this->assertDatabaseMissing('lines', ['code' => 'BUS']);
    }

    public function test_invalid_export_reports_explicit_error_with_force(): void
    {
        $path = storage_path('app/testing-idfm-invalid.csv');
        file_put_contents($path, "foo;bar\nbaz;qux\n");

        $valid = storage_path('app/testing-idfm-valid-rel.csv');
        file_put_contents($valid, "zdaid;zdcid;zdaname\n1;1;One\n");

        config([
            'fotometro.idfm.arrets_lignes_url' => "file://{$path}",
            'fotometro.idfm.stop_areas_url' => "file://{$valid}",
            'fotometro.idfm.stop_relations_url' => "file://{$valid}",
        ]);

        $report = app(NetworkImporter::class)->import(options: [
            'force' => true,
            'skip_traces' => true,
            'skip_accesses' => true,
        ]);

        $this->assertStringContainsString('missing expected headers', $report->errors[0]);
    }

    private function csv(array $rows): string
    {
        return collect($rows)
            ->map(fn (array $row) => implode(';', array_map(fn ($value) => str_replace(';', ',', (string) $value), $row)))
            ->implode("\n");
    }
}

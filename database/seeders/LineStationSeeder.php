<?php

namespace Database\Seeders;

use App\Enums\CoverageStatus;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Database\Seeder;

class LineStationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') && (
            Line::query()->whereNotNull('external_id')->exists()
            || Station::query()->whereNotNull('external_id')->exists()
        )) {
            return;
        }

        $lines = collect([
            ['code' => '1', 'name' => 'Ligne 1', 'slug' => 'ligne-1', 'color' => '#FFCD00', 'text_color' => '#111111', 'sort_order' => 1, 'path_geojson' => null, 'is_active' => true],
            ['code' => '4', 'name' => 'Ligne 4', 'slug' => 'ligne-4', 'color' => '#A0006E', 'text_color' => '#FFFFFF', 'sort_order' => 4, 'path_geojson' => null, 'is_active' => true],
            ['code' => '6', 'name' => 'Ligne 6', 'slug' => 'ligne-6', 'color' => '#79BB92', 'text_color' => '#111111', 'sort_order' => 6, 'path_geojson' => null, 'is_active' => true],
            ['code' => '14', 'name' => 'Ligne 14', 'slug' => 'ligne-14', 'color' => '#62259D', 'text_color' => '#FFFFFF', 'sort_order' => 14, 'path_geojson' => null, 'is_active' => true],
        ])->mapWithKeys(fn (array $line) => [
            $line['code'] => Line::updateOrCreate(['code' => $line['code']], $line),
        ]);

        $stations = collect([
            [
                'name' => 'Chatelet',
                'slug' => 'chatelet',
                'latitude' => 48.8586,
                'longitude' => 2.3470,
                'city' => 'Paris',
                'postal_code' => '75001',
                'district' => '1er arrondissement',
                'opening_date' => '1900-08-06',
                'description' => 'Grande station de correspondance au coeur de Paris.',
                'coverage_status' => CoverageStatus::Documented,
                'lines' => [
                    '1' => ['position' => 8, 'is_terminus' => false],
                    '4' => ['position' => 11, 'is_terminus' => false],
                    '14' => ['position' => 5, 'is_terminus' => false],
                ],
            ],
            [
                'name' => 'Bastille',
                'slug' => 'bastille',
                'latitude' => 48.8530,
                'longitude' => 2.3691,
                'city' => 'Paris',
                'postal_code' => '75004',
                'district' => '4e arrondissement',
                'opening_date' => '1900-07-19',
                'description' => 'Station historique proche de la place de la Bastille.',
                'coverage_status' => CoverageStatus::InProgress,
                'lines' => ['1' => ['position' => 12, 'is_terminus' => false]],
            ],
            [
                'name' => 'Montparnasse - Bienvenue',
                'slug' => 'montparnasse-bienvenue',
                'latitude' => 48.8437,
                'longitude' => 2.3226,
                'city' => 'Paris',
                'postal_code' => '75015',
                'district' => '15e arrondissement',
                'opening_date' => '1910-01-09',
                'description' => 'Pole de correspondance de la rive gauche.',
                'coverage_status' => CoverageStatus::Planned,
                'lines' => [
                    '4' => ['position' => 18, 'is_terminus' => false],
                    '6' => ['position' => 17, 'is_terminus' => false],
                ],
            ],
            [
                'name' => 'Nation',
                'slug' => 'nation',
                'latitude' => 48.8484,
                'longitude' => 2.3959,
                'city' => 'Paris',
                'postal_code' => '75012',
                'district' => '12e arrondissement',
                'opening_date' => '1900-07-19',
                'description' => "Station majeure de l'est parisien.",
                'coverage_status' => CoverageStatus::Complete,
                'lines' => [
                    '1' => ['position' => 18, 'is_terminus' => false],
                    '6' => ['position' => 28, 'is_terminus' => true],
                ],
            ],
            [
                'name' => 'Olympiades',
                'slug' => 'olympiades',
                'latitude' => 48.8272,
                'longitude' => 2.3674,
                'city' => 'Paris',
                'postal_code' => '75013',
                'district' => '13e arrondissement',
                'opening_date' => '2007-06-26',
                'description' => 'Station du sud-est parisien sur la ligne automatique.',
                'coverage_status' => CoverageStatus::NotStarted,
                'lines' => ['14' => ['position' => 1, 'is_terminus' => false]],
            ],
        ]);

        $stations->each(function (array $data) use ($lines): void {
            $stationLines = $data['lines'];
            unset($data['lines']);

            $station = Station::updateOrCreate(['slug' => $data['slug']], $data);

            $station->lines()->sync(
                collect($stationLines)->mapWithKeys(fn (array $pivot, string $code) => [
                    $lines[$code]->id => [
                        'position' => $pivot['position'],
                        'branch' => $pivot['branch'] ?? null,
                        'is_terminus' => $pivot['is_terminus'],
                    ],
                ])->all()
            );
        });
    }
}

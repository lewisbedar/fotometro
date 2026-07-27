<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineStationRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_station_can_belong_to_multiple_lines(): void
    {
        $station = Station::factory()->create();
        $lineOne = Line::factory()->create(['code' => '1', 'slug' => 'ligne-1', 'sort_order' => 1]);
        $lineFour = Line::factory()->create(['code' => '4', 'slug' => 'ligne-4', 'sort_order' => 4]);

        $station->lines()->attach($lineOne, ['position' => 8, 'is_terminus' => false]);
        $station->lines()->attach($lineFour, ['position' => 11, 'is_terminus' => false]);

        $this->assertCount(2, $station->fresh()->lines);
        $this->assertTrue($station->fresh()->lines->contains($lineOne));
        $this->assertTrue($station->fresh()->lines->contains($lineFour));
    }

    public function test_line_serves_multiple_stations_with_pivot_metadata(): void
    {
        $line = Line::factory()->create(['code' => '14', 'slug' => 'ligne-14']);
        $stationA = Station::factory()->create(['slug' => 'olympiades']);
        $stationB = Station::factory()->create(['slug' => 'chatelet']);

        $line->stations()->attach($stationA, ['position' => 1, 'branch' => null, 'is_terminus' => true]);
        $line->stations()->attach($stationB, ['position' => 5, 'branch' => 'central', 'is_terminus' => false]);

        $stations = $line->fresh()->stations;

        $this->assertCount(2, $stations);
        $this->assertSame('olympiades', $stations->first()->slug);
        $this->assertTrue((bool) $stations->first()->pivot->is_terminus);
        $this->assertSame('central', $stations->last()->pivot->branch);
    }
}

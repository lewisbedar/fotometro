<?php

namespace Tests\Unit;

use App\Models\Station;
use App\Services\Stations\NearestStationLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NearestStationLocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_locate_returns_the_closest_active_station_within_radius(): void
    {
        Station::factory()->create(['name' => 'Bastille', 'latitude' => 48.8531, 'longitude' => 2.3692, 'is_active' => true]);
        Station::factory()->create(['name' => 'Nation', 'latitude' => 48.8483, 'longitude' => 2.3958, 'is_active' => true]);

        $match = app(NearestStationLocator::class)->locate(48.85305, 2.36925);

        $this->assertNotNull($match);
        $this->assertSame('Bastille', $match['station']->name);
        $this->assertLessThan(50, $match['distance_meters']);
    }

    public function test_locate_returns_null_when_nothing_is_within_the_configured_radius(): void
    {
        config(['fotometro.photos.exif_station_match_radius_meters' => 200]);

        Station::factory()->create(['name' => 'Nation', 'latitude' => 48.8483, 'longitude' => 2.3958, 'is_active' => true]);

        // Bastille, several kilometers from Nation.
        $match = app(NearestStationLocator::class)->locate(48.8531, 2.3692);

        $this->assertNull($match);
    }

    public function test_locate_ignores_inactive_and_uncoordinated_stations(): void
    {
        Station::factory()->create(['name' => 'Inactive proche', 'latitude' => 48.85305, 'longitude' => 2.36925, 'is_active' => false]);
        Station::factory()->create(['name' => 'Sans coordonnees', 'latitude' => null, 'longitude' => null, 'is_active' => true]);
        Station::factory()->create(['name' => 'Bastille', 'latitude' => 48.8531, 'longitude' => 2.3692, 'is_active' => true]);

        $match = app(NearestStationLocator::class)->locate(48.85305, 2.36925);

        $this->assertNotNull($match);
        $this->assertSame('Bastille', $match['station']->name);
    }
}

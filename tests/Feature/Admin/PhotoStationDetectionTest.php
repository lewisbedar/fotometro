<?php

namespace Tests\Feature\Admin;

use App\Models\Line;
use App\Models\Station;
use App\Models\User;
use App\Services\Photos\ExifReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PhotoStationDetectionTest extends TestCase
{
    use RefreshDatabase;

    private function fakeExifCoordinates(?float $latitude, ?float $longitude): void
    {
        $this->app->bind(ExifReader::class, fn () => new class($latitude, $longitude) extends ExifReader
        {
            public function __construct(private readonly ?float $lat, private readonly ?float $lon) {}

            public function read(string $path): array
            {
                return ['latitude' => $this->lat, 'longitude' => $this->lon];
            }
        });
    }

    public function test_detects_the_nearest_station_and_its_line_within_radius(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $station = Station::factory()->create(['name' => 'Bastille', 'latitude' => 48.8531, 'longitude' => 2.3692, 'is_active' => true]);
        $station->lines()->attach($line->id, ['position' => 1, 'branch' => null, 'is_terminus' => false]);

        $this->fakeExifCoordinates(48.85305, 2.36925);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('admin.api.photos.detect-station'), [
                'file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            ]);

        $response->assertOk()->assertJson([
            'matched' => true,
            'station' => ['id' => $station->id, 'name' => 'Bastille'],
            'line' => ['id' => $line->id],
        ]);
    }

    public function test_returns_not_matched_when_the_photo_has_no_gps_data(): void
    {
        $this->fakeExifCoordinates(null, null);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('admin.api.photos.detect-station'), [
                'file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            ]);

        $response->assertOk()->assertJson(['matched' => false]);
    }

    public function test_returns_not_matched_when_gps_is_present_but_too_far_from_any_station(): void
    {
        Station::factory()->create(['name' => 'Bastille', 'latitude' => 48.8531, 'longitude' => 2.3692, 'is_active' => true]);

        // Roughly 6km away, well outside the 200m default radius.
        $this->fakeExifCoordinates(48.90, 2.40);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('admin.api.photos.detect-station'), [
                'file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            ]);

        $response->assertOk()->assertJson(['matched' => false]);
    }

    public function test_guests_cannot_call_the_detection_endpoint(): void
    {
        $this->post(route('admin.api.photos.detect-station'), [
            'file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ])->assertRedirect(route('login'));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BadgeTier;
use App\Models\Line;
use App\Models\Photo;
use App\Models\Station;
use App\Models\User;
use App\Services\Badges\BadgeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calculator(): BadgeCalculator
    {
        return app(BadgeCalculator::class);
    }

    public function test_user_without_published_photos_has_no_badges(): void
    {
        $user = User::factory()->regularUser()->create();

        $this->assertTrue($this->calculator()->forUser($user)->isEmpty());
    }

    public function test_milestone_badge_reflects_the_highest_threshold_reached(): void
    {
        $user = User::factory()->regularUser()->create();
        Photo::factory()->count(10)->create(['user_id' => $user->id]);

        $badges = $this->calculator()->forUser($user);
        $milestone = $badges->firstWhere('key', 'milestone');

        $this->assertNotNull($milestone);
        $this->assertSame('10 photos publiées', $milestone->label);
        $this->assertSame(BadgeTier::Bronze, $milestone->tier);
    }

    public function test_first_photo_gets_a_dedicated_milestone_badge(): void
    {
        $user = User::factory()->regularUser()->create();
        Photo::factory()->create(['user_id' => $user->id]);

        $milestone = $this->calculator()->forUser($user)->firstWhere('key', 'milestone');

        $this->assertSame('Première photo', $milestone->label);
    }

    public function test_no_milestone_badge_between_the_first_photo_and_the_first_threshold(): void
    {
        $user = User::factory()->regularUser()->create();
        Photo::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertNull($this->calculator()->forUser($user)->firstWhere('key', 'milestone'));
    }

    public function test_line_coverage_badge_is_awarded_when_every_active_station_of_a_line_is_photographed(): void
    {
        $user = User::factory()->regularUser()->create();
        $line = Line::factory()->create(['code' => '4']);
        $stationA = Station::factory()->create(['is_active' => true]);
        $stationB = Station::factory()->create(['is_active' => true]);
        $line->stations()->attach([$stationA->id, $stationB->id]);

        Photo::factory()->create(['user_id' => $user->id, 'station_id' => $stationA->id]);
        Photo::factory()->create(['user_id' => $user->id, 'station_id' => $stationB->id]);

        $badges = $this->calculator()->forUser($user);

        $this->assertNotNull($badges->firstWhere('key', "line-coverage-{$line->id}"));
        $this->assertSame('Ligne 4 couverte', $badges->firstWhere('key', "line-coverage-{$line->id}")->label);
    }

    public function test_line_coverage_badge_is_not_awarded_when_a_station_is_missing(): void
    {
        $user = User::factory()->regularUser()->create();
        $line = Line::factory()->create();
        $stationA = Station::factory()->create(['is_active' => true]);
        $stationB = Station::factory()->create(['is_active' => true]);
        $line->stations()->attach([$stationA->id, $stationB->id]);

        Photo::factory()->create(['user_id' => $user->id, 'station_id' => $stationA->id]);

        $badges = $this->calculator()->forUser($user);

        $this->assertNull($badges->firstWhere('key', "line-coverage-{$line->id}"));
    }

    public function test_station_fan_badge_is_awarded_when_a_station_dominates_published_photos(): void
    {
        $user = User::factory()->regularUser()->create();
        $favoriteStation = Station::factory()->create(['name' => 'Châtelet']);

        Photo::factory()->count(4)->create(['user_id' => $user->id, 'station_id' => $favoriteStation->id]);
        Photo::factory()->count(2)->create(['user_id' => $user->id]);

        $badge = $this->calculator()->forUser($user)->firstWhere('key', 'fan-station');

        $this->assertNotNull($badge);
        $this->assertSame('Fan de la station Châtelet', $badge->label);
    }

    public function test_line_fan_badge_is_awarded_when_a_line_dominates_published_photos(): void
    {
        $user = User::factory()->regularUser()->create();
        $line = Line::factory()->create(['code' => '1']);
        $stations = Station::factory()->count(5)->create(['is_active' => true]);
        $line->stations()->attach($stations->pluck('id')->all());

        foreach ($stations as $station) {
            Photo::factory()->create(['user_id' => $user->id, 'station_id' => $station->id]);
        }

        Photo::factory()->count(2)->create(['user_id' => $user->id]);

        $badge = $this->calculator()->forUser($user)->firstWhere('key', 'fan-line');

        $this->assertNotNull($badge);
        $this->assertSame('Fan de la ligne 1', $badge->label);
    }

    public function test_loyalty_badge_reflects_months_since_account_approval(): void
    {
        $user = User::factory()->regularUser()->create(['approved_at' => now()->subMonths(7)]);
        Photo::factory()->create(['user_id' => $user->id]);

        $badge = $this->calculator()->forUser($user)->firstWhere('key', 'loyalty');

        $this->assertNotNull($badge);
        $this->assertSame(BadgeTier::Argent, $badge->tier);
    }

    public function test_line_coverage_badges_are_capped_even_when_more_lines_qualify(): void
    {
        $user = User::factory()->regularUser()->create();

        for ($i = 0; $i < 4; $i++) {
            $line = Line::factory()->create();
            $station = Station::factory()->create(['is_active' => true]);
            $line->stations()->attach($station->id);
            Photo::factory()->create(['user_id' => $user->id, 'station_id' => $station->id]);
        }

        $badges = $this->calculator()->forUser($user);
        $lineCoverageBadges = $badges->filter(fn ($badge) => str_starts_with($badge->key, 'line-coverage-'));

        $this->assertCount(3, $lineCoverageBadges);
    }

    public function test_total_badges_are_capped_across_all_families(): void
    {
        $user = User::factory()->regularUser()->create(['approved_at' => now()->subMonths(7)]);

        $dominantLine = Line::factory()->create();
        $dominantStation = Station::factory()->create(['is_active' => true]);
        $dominantLine->stations()->attach($dominantStation->id);
        Photo::factory()->count(5)->create(['user_id' => $user->id, 'station_id' => $dominantStation->id]);

        for ($i = 0; $i < 3; $i++) {
            $line = Line::factory()->create();
            $station = Station::factory()->create(['is_active' => true]);
            $line->stations()->attach($station->id);
            Photo::factory()->create(['user_id' => $user->id, 'station_id' => $station->id]);
        }

        Photo::factory()->count(2)->create(['user_id' => $user->id]);

        $badges = $this->calculator()->forUser($user);

        $this->assertCount(6, $badges);
    }

    public function test_line_coverage_and_fan_line_badges_use_the_lines_own_colors(): void
    {
        $user = User::factory()->regularUser()->create();
        $line = Line::factory()->create(['color' => '#FFCE00', 'text_color' => '#000000']);
        $stations = Station::factory()->count(5)->create(['is_active' => true]);
        $line->stations()->attach($stations->pluck('id')->all());

        foreach ($stations as $station) {
            Photo::factory()->create(['user_id' => $user->id, 'station_id' => $station->id]);
        }

        $badges = $this->calculator()->forUser($user);
        $coverageBadge = $badges->firstWhere('key', "line-coverage-{$line->id}");
        $fanBadge = $badges->firstWhere('key', 'fan-line');

        $this->assertSame('#FFCE00', $coverageBadge->displayBackground());
        $this->assertSame('#000000', $coverageBadge->displayTextColor());
        $this->assertSame('#FFCE00', $fanBadge->displayBackground());
        $this->assertSame('#000000', $fanBadge->displayTextColor());
    }
}

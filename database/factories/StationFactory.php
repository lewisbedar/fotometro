<?php

namespace Database\Factories;

use App\Enums\CoverageStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Station>
 */
class StationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'external_id' => fake()->optional()->uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'latitude' => fake()->latitude(48.80, 48.92),
            'longitude' => fake()->longitude(2.25, 2.42),
            'city' => 'Paris',
            'postal_code' => fake()->postcode(),
            'district' => fake()->numberBetween(1, 20).'e arrondissement',
            'opening_date' => fake()->optional()->date(),
            'description' => fake()->optional()->sentence(),
            'coverage_status' => fake()->randomElement(CoverageStatus::cases()),
            'is_active' => true,
        ];
    }
}

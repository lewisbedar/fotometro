<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Line>
 */
class LineFactory extends Factory
{
    public function definition(): array
    {
        $code = (string) fake()->unique()->numberBetween(1, 14);

        return [
            'code' => $code,
            'name' => "Ligne {$code}",
            'slug' => Str::slug("ligne {$code}"),
            'color' => fake()->hexColor(),
            'text_color' => '#111111',
            'sort_order' => (int) $code,
        ];
    }
}

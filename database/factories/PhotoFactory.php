<?php

namespace Database\Factories;

use App\Enums\PhotoLicense;
use App\Enums\PhotoModerationStatus;
use App\Enums\PhotoProcessingStatus;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\Photo> */
class PhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'station_id' => Station::factory(),
            'station_access_id' => null,
            'title' => fake()->optional()->sentence(3),
            'slug' => Str::slug(fake()->unique()->sentence(3)),
            'description' => fake()->optional()->paragraph(),
            'original_filename' => 'station.jpg',
            'original_path' => 'photos/originals/'.Str::uuid().'.jpg',
            'web_path' => 'photos/web/'.Str::uuid().'.jpg',
            'thumbnail_path' => 'photos/thumbnails/'.Str::uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1200,
            'width' => 1200,
            'height' => 800,
            'copyright_holder' => 'fotometro',
            'copyright_notice' => '© fotometro — Tous droits réservés',
            'license' => PhotoLicense::AllRightsReserved,
            'processing_status' => PhotoProcessingStatus::Ready,
            'is_featured' => false,
            'is_published' => true,
            'publish_when_ready' => false,
            'published_at' => now(),
            'sort_order' => 0,
            // Every pre-existing test predates the moderation concept and
            // implicitly assumes admin-trusted, already-approved content —
            // default here matches that so existing tests keep passing.
            'moderation_status' => PhotoModerationStatus::Approved,
        ];
    }

    public function withCategories(PhotoCategory|int ...$categories): static
    {
        $ids = collect($categories)->map(fn (PhotoCategory|int $category) => $category instanceof PhotoCategory ? $category->id : $category);

        return $this->afterCreating(fn (Photo $photo) => $photo->categories()->attach($ids));
    }
}

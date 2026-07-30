<?php

namespace Tests\Feature;

use App\Livewire\ProfileGallery;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_page_is_accessible_without_authentication(): void
    {
        $user = User::factory()->regularUser()->create(['bio' => 'Passionné de métro parisien.']);

        $this->get(route('profiles.show', $user))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Passionné de métro parisien.');
    }

    public function test_profile_page_wires_up_the_photo_lightbox(): void
    {
        $user = User::factory()->regularUser()->create();

        $this->get(route('profiles.show', $user))
            ->assertOk()
            ->assertSee('fotometroLightbox()', false)
            ->assertSee('lightbox-overlay', false);
    }

    public function test_profile_stats_and_gallery_only_reflect_the_users_own_published_photos(): void
    {
        $user = User::factory()->regularUser()->create();
        $otherUser = User::factory()->regularUser()->create();

        $mine = Photo::factory()->count(2)->create(['user_id' => $user->id]);
        Photo::factory()->create(['user_id' => $otherUser->id]);
        Photo::factory()->create(['user_id' => $user->id, 'is_published' => false]);

        $this->get(route('profiles.show', $user))
            ->assertOk()
            ->assertSee('>2<', false)
            ->assertSeeText('Photo(s) publiée(s)');

        Livewire::test(ProfileGallery::class, ['user' => $user])
            ->assertSee($mine->first()->publicLabel())
            ->assertDontSee(Photo::query()->where('user_id', $otherUser->id)->first()->publicLabel());
    }

    public function test_gallery_sort_toggles_between_recent_and_oldest(): void
    {
        $user = User::factory()->regularUser()->create();
        $older = Photo::factory()->create(['user_id' => $user->id, 'title' => 'Vieille photo', 'taken_at' => now()->subDays(10)]);
        $newer = Photo::factory()->create(['user_id' => $user->id, 'title' => 'Nouvelle photo', 'taken_at' => now()->subDay()]);

        Livewire::test(ProfileGallery::class, ['user' => $user])
            ->assertSeeInOrder([$newer->publicLabel(), $older->publicLabel()]);

        Livewire::test(ProfileGallery::class, ['user' => $user])
            ->set('sort', 'oldest')
            ->assertSeeInOrder([$older->publicLabel(), $newer->publicLabel()]);
    }

    public function test_gallery_sort_by_popularity_orders_by_view_count(): void
    {
        $user = User::factory()->regularUser()->create();
        $lessViewed = Photo::factory()->create(['user_id' => $user->id, 'title' => 'Peu vue', 'views_count' => 2]);
        $mostViewed = Photo::factory()->create(['user_id' => $user->id, 'title' => 'Tres vue', 'views_count' => 50]);

        Livewire::test(ProfileGallery::class, ['user' => $user])
            ->set('sort', 'popular')
            ->assertSeeInOrder([$mostViewed->publicLabel(), $lessViewed->publicLabel()]);
    }

    public function test_gallery_filters_by_root_and_subcategory(): void
    {
        $user = User::factory()->regularUser()->create();
        $root = PhotoCategory::factory()->create(['name' => 'Architecture', 'sort_order' => 0]);
        $child = PhotoCategory::factory()->create(['name' => 'Carrelage', 'parent_id' => $root->id, 'sort_order' => 0]);

        $tagged = Photo::factory()->create(['user_id' => $user->id, 'title' => 'Photo carrelage']);
        $tagged->categories()->attach($child->id);
        $untagged = Photo::factory()->create(['user_id' => $user->id, 'title' => 'Photo sans categorie']);

        $component = Livewire::test(ProfileGallery::class, ['user' => $user])
            ->assertSee($tagged->publicLabel())
            ->assertSee($untagged->publicLabel());

        $component->call('selectCategory', $root->slug)
            ->assertSee($tagged->publicLabel())
            ->assertDontSee($untagged->publicLabel());

        $component->call('selectCategory', $child->slug)
            ->assertSee($tagged->publicLabel())
            ->assertDontSee($untagged->publicLabel());
    }

    public function test_owner_can_update_their_bio(): void
    {
        $user = User::factory()->regularUser()->create(['bio' => 'Ancienne bio']);

        $this->actingAs($user)
            ->patch(route('profile.bio.update'), ['bio' => 'Nouvelle bio'])
            ->assertRedirect(route('profiles.show', $user));

        $this->assertSame('Nouvelle bio', $user->fresh()->bio);
    }

    public function test_owner_can_upload_and_then_remove_an_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->regularUser()->create();

        $this->actingAs($user)
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('moi.jpg', 800, 800),
            ])
            ->assertRedirect(route('profiles.show', $user));

        $user->refresh();
        $this->assertSame("avatars/{$user->id}.jpg", $user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        $this->actingAs($user)
            ->delete(route('profile.avatar.destroy'))
            ->assertRedirect(route('profiles.show', $user));

        $user->refresh();
        $this->assertNull($user->avatar_path);
        Storage::disk('public')->assertMissing("avatars/{$user->id}.jpg");
    }
}

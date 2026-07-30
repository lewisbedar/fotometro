<?php

namespace Tests\Feature;

use App\Enums\PhotoModerationStatus;
use App\Enums\PhotoProcessingStatus;
use App\Livewire\PhotoModerationQueue;
use App\Models\Line;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\PhotoRejectionReason;
use App\Models\Station;
use App\Models\User;
use App\Notifications\PhotoRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UserPhotoUploadAndModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_user_can_upload_a_photo_which_stays_pending_and_hidden(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->regularUser()->create();
        $station = Station::factory()->create();

        $this->actingAs($user)->post(route('photos.upload.store'), [
            'file' => UploadedFile::fake()->image('ma-photo.jpg', 1200, 800),
            'station_id' => $station->id,
            'description' => 'Une belle photo',
        ])->assertRedirect(route('photos.upload.thanks'));

        $photo = Photo::query()->where('station_id', $station->id)->firstOrFail();

        $this->assertSame(PhotoModerationStatus::Pending, $photo->moderation_status);
        $this->assertFalse($photo->is_published);
        $this->assertSame($user->id, $photo->user_id);
        $this->assertSame($user->name, $photo->copyright_holder);

        $this->assertFalse(Photo::query()->publiclyVisible()->whereKey($photo->id)->exists());
    }

    public function test_upload_ignores_a_user_id_or_moderation_status_supplied_in_the_request(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $submitter = User::factory()->regularUser()->create();
        $otherUser = User::factory()->regularUser()->create();
        $station = Station::factory()->create();

        $this->actingAs($submitter)->post(route('photos.upload.store'), [
            'file' => UploadedFile::fake()->image('attaque.jpg', 1200, 800),
            'station_id' => $station->id,
            'user_id' => $otherUser->id,
            'moderation_status' => 'approved',
        ]);

        $photo = Photo::query()->where('station_id', $station->id)->firstOrFail();

        $this->assertSame($submitter->id, $photo->user_id);
        $this->assertSame(PhotoModerationStatus::Pending, $photo->moderation_status);
    }

    public function test_regular_user_cannot_reach_the_moderation_queue(): void
    {
        $user = User::factory()->regularUser()->create();

        $this->actingAs($user)
            ->get(route('admin.moderation.index'))
            ->assertForbidden();
    }

    public function test_moderator_can_approve_a_pending_photo_from_the_queue(): void
    {
        $moderator = User::factory()->moderator()->create();
        $photo = Photo::factory()->create(['moderation_status' => PhotoModerationStatus::Pending, 'is_published' => false]);

        Livewire::actingAs($moderator)
            ->test(PhotoModerationQueue::class)
            ->assertSet('currentPhotoId', $photo->id)
            ->call('approve');

        $photo->refresh();
        $this->assertSame(PhotoModerationStatus::Approved, $photo->moderation_status);
        $this->assertTrue($photo->is_published);
        $this->assertSame($moderator->id, $photo->moderated_by);
    }

    public function test_moderator_can_reject_a_pending_photo_with_a_reason_and_notify_the_submitter(): void
    {
        Notification::fake();

        $moderator = User::factory()->moderator()->create();
        $submitter = User::factory()->regularUser()->create();
        $reason = PhotoRejectionReason::query()->create(['label' => 'Photo floue', 'slug' => 'photo-floue']);
        $photo = Photo::factory()->create([
            'moderation_status' => PhotoModerationStatus::Pending,
            'is_published' => false,
            'user_id' => $submitter->id,
        ]);

        Livewire::actingAs($moderator)
            ->test(PhotoModerationQueue::class)
            ->call('startReject')
            ->set('rejection_reason_id', $reason->id)
            ->call('reject');

        $photo->refresh();
        $this->assertSame(PhotoModerationStatus::Rejected, $photo->moderation_status);
        $this->assertFalse($photo->is_published);
        $this->assertSame($reason->id, $photo->photo_rejection_reason_id);

        Notification::assertSentTo($submitter, PhotoRejectedNotification::class);
    }

    public function test_rejecting_without_any_reason_shows_a_validation_error(): void
    {
        $moderator = User::factory()->moderator()->create();
        $photo = Photo::factory()->create(['moderation_status' => PhotoModerationStatus::Pending, 'is_published' => false]);

        Livewire::actingAs($moderator)
            ->test(PhotoModerationQueue::class)
            ->call('startReject')
            ->call('reject')
            ->assertHasErrors('custom_rejection_note');

        $this->assertSame(PhotoModerationStatus::Pending, $photo->fresh()->moderation_status);
    }

    public function test_editing_a_photo_in_the_queue_auto_approves_it(): void
    {
        $moderator = User::factory()->moderator()->create();
        $otherStation = Station::factory()->create();
        $category = PhotoCategory::factory()->create();
        $photo = Photo::factory()->create(['moderation_status' => PhotoModerationStatus::Pending, 'is_published' => false]);

        Livewire::actingAs($moderator)
            ->test(PhotoModerationQueue::class)
            ->call('startEdit')
            ->set('station_id', $otherStation->id)
            ->set('category_ids', [$category->id])
            ->set('description', 'Description corrigée')
            ->call('saveEdit');

        $photo->refresh();
        $this->assertSame($otherStation->id, $photo->station_id);
        $this->assertSame('Description corrigée', $photo->description);
        $this->assertTrue($photo->categories->pluck('id')->contains($category->id));
        $this->assertSame(PhotoModerationStatus::Approved, $photo->moderation_status);
        $this->assertTrue($photo->is_published);
    }

    public function test_start_edit_preselects_the_photos_current_line(): void
    {
        $moderator = User::factory()->moderator()->create();
        $line = Line::factory()->create();
        $station = Station::factory()->create();
        $line->stations()->attach($station->id);
        $photo = Photo::factory()->create(['station_id' => $station->id, 'moderation_status' => PhotoModerationStatus::Pending, 'is_published' => false]);

        Livewire::actingAs($moderator)
            ->test(PhotoModerationQueue::class)
            ->call('startEdit')
            ->assertSet('line_id', $line->id);
    }

    public function test_available_stations_are_filtered_by_the_selected_line(): void
    {
        $moderator = User::factory()->moderator()->create();
        $lineA = Line::factory()->create();
        $lineB = Line::factory()->create();
        $stationOnA = Station::factory()->create(['name' => 'Sur ligne A']);
        $stationOnB = Station::factory()->create(['name' => 'Sur ligne B']);
        $lineA->stations()->attach($stationOnA->id);
        $lineB->stations()->attach($stationOnB->id);
        Photo::factory()->create(['moderation_status' => PhotoModerationStatus::Pending, 'is_published' => false]);

        Livewire::actingAs($moderator)
            ->test(PhotoModerationQueue::class)
            ->call('startEdit')
            ->set('line_id', $lineA->id)
            ->assertSee('Sur ligne A')
            ->assertDontSee('Sur ligne B');
    }

    public function test_changing_the_line_resets_the_selected_station_and_access(): void
    {
        $moderator = User::factory()->moderator()->create();
        $line = Line::factory()->create();
        $station = Station::factory()->create();
        $line->stations()->attach($station->id);
        Photo::factory()->create(['station_id' => $station->id, 'moderation_status' => PhotoModerationStatus::Pending, 'is_published' => false]);

        Livewire::actingAs($moderator)
            ->test(PhotoModerationQueue::class)
            ->call('startEdit')
            ->set('station_id', $station->id)
            ->assertSet('station_id', $station->id)
            ->set('line_id', Line::factory()->create()->id)
            ->assertSet('station_id', null)
            ->assertSet('station_access_id', null);
    }

    public function test_queue_only_shows_photos_that_finished_processing(): void
    {
        $moderator = User::factory()->moderator()->create();
        Photo::factory()->create([
            'moderation_status' => PhotoModerationStatus::Pending,
            'is_published' => false,
            'processing_status' => PhotoProcessingStatus::Pending,
        ]);

        Livewire::actingAs($moderator)
            ->test(PhotoModerationQueue::class)
            ->assertSet('currentPhotoId', null);
    }

    public function test_admin_bulk_import_wizard_still_auto_approves_photos(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create();
        $station = Station::factory()->create();

        $this->actingAs($admin)->post(route('admin.photos.store'), [
            'license' => 'all_rights_reserved',
            'publish_mode' => 'draft',
            'files' => [UploadedFile::fake()->image('admin.jpg', 800, 600)],
            'photos' => [['station_id' => $station->id]],
        ])->assertRedirect(route('admin.photos.index'));

        $photo = Photo::query()->where('station_id', $station->id)->firstOrFail();
        $this->assertSame(PhotoModerationStatus::Approved, $photo->moderation_status);
        $this->assertSame($admin->id, $photo->user_id);
    }
}

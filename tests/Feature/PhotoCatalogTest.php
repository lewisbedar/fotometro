<?php

namespace Tests\Feature;

use App\Enums\CoverageStatus;
use App\Enums\PhotoModerationStatus;
use App\Enums\PhotoProcessingStatus;
use App\Models\Line;
use App\Models\Photo;
use App\Models\PhotoCategory;
use App\Models\Station;
use App\Models\StationAccess;
use App\Models\User;
use Database\Seeders\PhotoCategorySeeder;
use App\Services\Photos\PhotoImporter;
use App\Services\Photos\PhotoProcessor;
use App\Services\Photos\StationCoverageUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PhotoCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_model_relations_and_category_tree(): void
    {
        $station = Station::factory()->create();
        $access = StationAccess::query()->create([
            'external_id' => 'ACCESS:1',
            'name' => 'Sortie 1',
            'is_active' => true,
        ]);
        $station->accesses()->attach($access->id);
        $parent = PhotoCategory::factory()->create(['name' => 'Interieur']);
        $child = PhotoCategory::factory()->create(['parent_id' => $parent->id, 'name' => 'Quai']);
        $photo = Photo::factory()->withCategories($child)->create([
            'station_id' => $station->id,
            'station_access_id' => $access->id,
        ]);

        $this->assertTrue($photo->station->is($station));
        $this->assertTrue($photo->stationAccess->is($access));
        $this->assertTrue($photo->categories->pluck('id')->contains($child->id));
        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_import_valid_jpeg_creates_pending_private_original_and_defaults(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config([
            'fotometro.photos.default_copyright_holder' => 'Lewis Bedar',
            'fotometro.photos.process_synchronously' => false,
        ]);

        $station = Station::factory()->create();
        $photo = app(PhotoImporter::class)->import(
            UploadedFile::fake()->image('republique.jpg', 1200, 800),
            ['station_id' => $station->id, 'license' => 'all_rights_reserved']
        );

        $this->assertSame(PhotoProcessingStatus::Pending, $photo->processing_status);
        $this->assertTrue($photo->publish_when_ready);
        $this->assertFalse($photo->is_published);
        $this->assertSame('Lewis Bedar', $photo->copyright_holder);
        $this->assertStringContainsString('Lewis Bedar', $photo->copyright_notice);
        $this->assertNotSame('republique.jpg', basename($photo->original_path));
        Storage::disk('local')->assertExists($photo->original_path);
        Storage::disk('public')->assertMissing($photo->original_path);
    }

    public function test_publish_when_ready_auto_publishes_only_after_successful_processing(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not available.');
        }

        Storage::fake('local');
        Storage::fake('public');
        Cache::put('fotometro.public-map.v1', ['stale' => true], 300);
        $station = Station::factory()->create();
        $photo = app(PhotoImporter::class)->import(
            UploadedFile::fake()->image('auto.jpg', 1200, 800),
            [
                'station_id' => $station->id,
                'license' => 'all_rights_reserved',
                'publish_when_ready' => true,
                // publish_when_ready is only ever true for admin-imported
                // photos in practice (Admin\PhotoController::store() always
                // sets this), which are already moderation-approved at
                // creation time — this mirrors that real usage.
                'moderation_status' => PhotoModerationStatus::Approved,
            ]
        );

        $this->get(route('photos.show', $photo))->assertNotFound();

        app(PhotoProcessor::class)->process($photo);
        $photo->refresh();

        $this->assertSame(PhotoProcessingStatus::Ready, $photo->processing_status);
        $this->assertTrue($photo->is_published);
        $this->assertNotNull($photo->published_at);
        $this->get(route('photos.show', $photo))->assertOk();
        $this->assertFalse(Cache::has('fotometro.public-map.v1'));
    }

    public function test_draft_import_becomes_ready_without_publication_and_failed_never_publishes(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not available.');
        }

        Storage::fake('local');
        Storage::fake('public');
        $station = Station::factory()->create();
        $draft = app(PhotoImporter::class)->import(
            UploadedFile::fake()->image('draft.jpg', 900, 600),
            ['station_id' => $station->id, 'license' => 'all_rights_reserved', 'publish_when_ready' => false]
        );
        app(PhotoProcessor::class)->process($draft);
        $draft->refresh();

        $this->assertSame(PhotoProcessingStatus::Ready, $draft->processing_status);
        $this->assertFalse($draft->is_published);
        $this->get(route('photos.show', $draft))->assertNotFound();

        $failed = Photo::factory()->create([
            'station_id' => $station->id,
            'processing_status' => PhotoProcessingStatus::Pending,
            'publish_when_ready' => true,
            'is_published' => false,
            'published_at' => null,
            'original_path' => 'photos/originals/missing.jpg',
        ]);
        app(PhotoProcessor::class)->process($failed, true);

        $this->assertSame(PhotoProcessingStatus::Failed, $failed->fresh()->processing_status);
        $this->assertFalse($failed->fresh()->is_published);
    }

    public function test_import_rejects_invalid_mime_large_file_and_wrong_access(): void
    {
        Storage::fake('local');
        $station = Station::factory()->create();
        $otherStation = Station::factory()->create();
        $access = StationAccess::query()->create(['external_id' => 'ACCESS:2', 'is_active' => true]);
        $otherStation->accesses()->attach($access->id);

        try {
            app(PhotoImporter::class)->import(UploadedFile::fake()->create('vector.svg', 4, 'image/svg+xml'), [
                'station_id' => $station->id,
                'license' => 'all_rights_reserved',
            ]);
            $this->fail('Invalid SVG was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('photos', 0);
        }

        config(['fotometro.photos.max_upload_mb' => 1]);

        $this->expectException(ValidationException::class);
        app(PhotoImporter::class)->import(UploadedFile::fake()->image('large.jpg')->size(2048), [
            'station_id' => $station->id,
            'station_access_id' => $access->id,
            'license' => 'all_rights_reserved',
        ]);
    }

    public function test_processor_generates_web_thumbnail_ready_and_retry_failed(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not available.');
        }

        Storage::fake('local');
        Storage::fake('public');
        config(['fotometro.photos.process_synchronously' => false]);

        $station = Station::factory()->create();
        $photo = app(PhotoImporter::class)->import(
            UploadedFile::fake()->image('photo.jpg', 1600, 1000),
            ['station_id' => $station->id, 'license' => 'all_rights_reserved', 'is_published' => true]
        );

        app(PhotoProcessor::class)->process($photo);
        $photo->refresh();

        $this->assertSame(PhotoProcessingStatus::Ready, $photo->processing_status);
        $this->assertNotNull($photo->web_path);
        $this->assertNotNull($photo->thumbnail_path);
        Storage::disk('public')->assertExists($photo->web_path);
        Storage::disk('public')->assertExists($photo->thumbnail_path);
        $this->artisan('fotometro:process-photos --photo='.$photo->id.' --force')->assertExitCode(0);
    }

    public function test_public_photo_urls_use_app_url_and_never_original_path(): void
    {
        config([
            'app.url' => 'https://fotometro.test',
            'filesystems.disks.public.url' => 'https://fotometro.test/storage',
        ]);
        Storage::forgetDisk('public');

        $photo = Photo::factory()->create([
            'original_path' => 'photos/originals/private.jpg',
            'web_path' => 'photos/web/public.jpg',
            'thumbnail_path' => 'photos/thumbnails/thumb.jpg',
        ]);

        $this->assertSame('https://fotometro.test/storage/photos/web/public.jpg', $photo->web_url);
        $this->assertSame('https://fotometro.test/storage/photos/thumbnails/thumb.jpg', $photo->thumbnail_url);
        $this->assertStringNotContainsString('localhost', $photo->web_url);
        $this->assertStringNotContainsString('originals/private.jpg', $photo->web_url);
        $this->assertNull((new Photo(['web_path' => 'photos/originals/private.jpg']))->web_url);

        config(['filesystems.disks.public.url' => 'http://localhost/storage']);
        Storage::forgetDisk('public');
        $this->assertSame('http://localhost/storage/photos/thumbnails/thumb.jpg', $photo->thumbnail_url);

        config(['filesystems.disks.public.url' => 'http://127.0.0.1:8001/storage']);
        Storage::forgetDisk('public');
        $this->assertSame('http://127.0.0.1:8001/storage/photos/web/public.jpg', $photo->web_url);
    }

    public function test_processing_failure_marks_failed_and_cleans_partials(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $photo = Photo::factory()->create([
            'processing_status' => PhotoProcessingStatus::Pending,
            'original_path' => 'photos/originals/missing.jpg',
            'web_path' => null,
            'thumbnail_path' => null,
        ]);

        app(PhotoProcessor::class)->process($photo, true);

        $this->assertSame(PhotoProcessingStatus::Failed, $photo->fresh()->processing_status);
    }

    public function test_admin_routes_are_protected_and_import_page_is_available(): void
    {
        $this->get('/admin/photos')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())
            ->get(route('photos.upload.create'))
            ->assertOk()
            ->assertSee('Importer des photos');
    }

    public function test_admin_location_api_is_protected_and_filters_stations_and_accesses(): void
    {
        $line = Line::factory()->create(['code' => '1', 'sort_order' => 1]);
        $otherLine = Line::factory()->create(['code' => '6', 'sort_order' => 6]);
        $stationA = Station::factory()->create(['name' => 'Châtelet', 'latitude' => 48.858, 'longitude' => 2.347]);
        $stationB = Station::factory()->create(['name' => 'Louvre', 'latitude' => 48.860, 'longitude' => 2.336]);
        $stationOther = Station::factory()->create(['name' => 'Nation']);
        $line->stations()->attach($stationB->id, ['position' => 2, 'branch' => 'main', 'is_terminus' => false]);
        $line->stations()->attach($stationA->id, ['position' => 1, 'branch' => 'main', 'is_terminus' => true]);
        $otherLine->stations()->attach($stationA->id, ['position' => 9]);
        $otherLine->stations()->attach($stationOther->id, ['position' => 1]);

        $access = StationAccess::query()->create([
            'external_id' => 'ACCESS:CHATELET:1',
            'name' => 'Accès Rivoli',
            'reference' => '1',
            'latitude' => 48.8581,
            'longitude' => 2.3471,
            'is_active' => true,
            'source_payload' => ['secret' => true],
        ]);
        $otherAccess = StationAccess::query()->create(['external_id' => 'ACCESS:OTHER', 'name' => 'Autre accès', 'is_active' => true]);
        $stationA->accesses()->attach($access->id);
        $stationOther->accesses()->attach($otherAccess->id);

        $this->get(route('admin.api.lines.stations', $line))->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.api.lines.stations', $line))
            ->assertOk()
            ->assertJsonPath('data.0.id', $stationA->id)
            ->assertJsonPath('data.0.name', 'Châtelet')
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.0.lines.1.id', $otherLine->id)
            ->assertJsonMissing(['source_payload' => ['secret' => true]])
            ->assertJsonMissing(['id' => $stationOther->id]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.api.stations.accesses', $stationA))
            ->assertOk()
            ->assertJsonPath('data.0.id', $access->id)
            ->assertJsonPath('data.0.name', 'Accès Rivoli')
            ->assertJsonMissing(['id' => $otherAccess->id])
            ->assertJsonMissing(['source_payload' => ['secret' => true]]);
    }

    public function test_admin_photo_forms_use_line_station_access_filtering_with_edit_preselection(): void
    {
        $line = Line::factory()->create(['code' => '4']);
        $station = Station::factory()->create(['name' => 'République']);
        $access = StationAccess::query()->create(['external_id' => 'ACCESS:REP:1', 'name' => 'Accès Temple', 'is_active' => true]);
        $line->stations()->attach($station->id, ['position' => 1]);
        $station->accesses()->attach($access->id);
        $photo = Photo::factory()->create(['station_id' => $station->id, 'station_access_id' => $access->id]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('photos.upload.create'))
            ->assertOk()
            ->assertSee('fotometroPhotoImportWizard', false)
            ->assertSee('Ligne '.$line->code, false)
            ->assertDontSee('<option value="'.$station->id.'">'.$station->name.'</option>', false);

        $this->actingAs($user)
            ->get(route('admin.photos.show', $photo))
            ->assertOk()
            ->assertSee('initialLineId', false)
            ->assertSee('initialStationId', false)
            ->assertSee('initialAccessId', false)
            ->assertSee((string) $line->id, false)
            ->assertSee((string) $station->id, false)
            ->assertSee((string) $access->id, false);
    }

    public function test_edit_refuses_access_from_another_station(): void
    {
        $station = Station::factory()->create();
        $otherStation = Station::factory()->create();
        $wrongAccess = StationAccess::query()->create(['external_id' => 'ACCESS:WRONG', 'name' => 'Mauvais accès', 'is_active' => true]);
        $otherStation->accesses()->attach($wrongAccess->id);
        $photo = Photo::factory()->create(['station_id' => $station->id, 'station_access_id' => null]);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.photos.update', $photo), [
                'station_id' => $station->id,
                'station_access_id' => $wrongAccess->id,
                'copyright_holder' => $photo->copyright_holder,
                'copyright_notice' => $photo->copyright_notice,
                'license' => $photo->license->value,
            ])
            ->assertSessionHasErrors('station_access_id');
    }

    public function test_manual_publish_unpublish_and_refuse_pending(): void
    {
        $user = User::factory()->create();
        $ready = Photo::factory()->create(['is_published' => false, 'published_at' => null]);
        $pending = Photo::factory()->create([
            'processing_status' => PhotoProcessingStatus::Pending,
            'is_published' => false,
            'published_at' => null,
        ]);

        // publish() now processes on demand rather than requiring a manual
        // step first — this factory photo has no real file behind it, so
        // that on-demand processing genuinely fails rather than succeeding.
        $this->actingAs($user)
            ->post(route('admin.photos.publish', $pending))
            ->assertSessionHas('status', 'Le traitement de cette photo a échoué, elle ne peut pas être publiée.');
        $this->assertFalse($pending->fresh()->is_published);

        $this->actingAs($user)->post(route('admin.photos.publish', $ready))->assertSessionHas('status', 'Photo publiée.');
        $this->assertTrue($ready->fresh()->is_published);

        $this->actingAs($user)->post(route('admin.photos.unpublish', $ready))->assertSessionHas('status', 'Photo dépubliée.');
        $this->assertFalse($ready->fresh()->is_published);
        $this->assertNull($ready->fresh()->published_at);
    }

    public function test_the_edit_form_cannot_publish_a_photo_directly(): void
    {
        // Publishing is only ever done through the dedicated Publier/Dépublier
        // buttons (admin.photos.publish/.unpublish), which go through
        // PhotoPublicationService — not through this metadata-only form.
        // Ticking a raw is_published field here (if a client sent one) must
        // be ignored rather than bypassing processing/moderation entirely.
        $user = User::factory()->create();
        $station = Station::factory()->create();
        $photo = Photo::factory()->create([
            'station_id' => $station->id,
            'is_published' => false,
            'processing_status' => PhotoProcessingStatus::Pending,
        ]);

        $this->actingAs($user)
            ->put(route('admin.photos.update', $photo), [
                'station_id' => $station->id,
                'copyright_holder' => $photo->copyright_holder,
                'copyright_notice' => $photo->copyright_notice,
                'license' => $photo->license->value,
                'is_published' => '1',
            ])
            ->assertSessionHas('status', 'Photo mise à jour.');

        $photo->refresh();
        $this->assertFalse($photo->is_published);
        $this->assertSame(PhotoProcessingStatus::Pending, $photo->processing_status);
    }

    public function test_editing_a_photo_preselects_its_own_line_not_the_stations_first_line(): void
    {
        // For an interchange station, the edit form used to always default
        // to the station's first line by sort_order — regardless of which
        // line the photo actually was imported/edited for — which looked
        // like the photo had silently been moved to a different line.
        $user = User::factory()->create();
        $station = Station::factory()->create();
        $lineA = Line::factory()->create(['sort_order' => 1]);
        $lineB = Line::factory()->create(['sort_order' => 2]);
        $station->lines()->attach([$lineA->id, $lineB->id]);

        $photo = Photo::factory()->create(['station_id' => $station->id, 'line_id' => $lineB->id]);

        $response = $this->actingAs($user)->get(route('admin.photos.show', $photo));

        // Js::from() escapes the quotes as " to embed the JSON safely
        // inside a single-quoted JSON.parse('...') call — that's the literal
        // substring that ends up in the rendered HTML.
        $needleB = chr(92).'u0022initialLineId'.chr(92).'u0022:'.$lineB->id;
        $needleA = chr(92).'u0022initialLineId'.chr(92).'u0022:'.$lineA->id;

        $response->assertOk();
        $response->assertSee($needleB, false);
        $response->assertDontSee($needleA, false);
    }

    public function test_updating_a_photo_stores_the_selected_line(): void
    {
        $user = User::factory()->create();
        $station = Station::factory()->create();
        $line = Line::factory()->create();
        $station->lines()->attach($line->id);
        $photo = Photo::factory()->create(['station_id' => $station->id, 'line_id' => null]);

        $this->actingAs($user)->put(route('admin.photos.update', $photo), [
            'station_id' => $station->id,
            'line_id' => $line->id,
            'copyright_holder' => $photo->copyright_holder,
            'copyright_notice' => $photo->copyright_notice,
            'license' => $photo->license->value,
        ])->assertSessionHas('status', 'Photo mise à jour.');

        $this->assertSame($line->id, $photo->fresh()->line_id);
    }

    public function test_station_gallery_can_be_filtered_by_line_at_an_interchange(): void
    {
        $station = Station::factory()->create();
        $lineA = Line::factory()->create();
        $lineB = Line::factory()->create();
        $station->lines()->attach([$lineA->id, $lineB->id]);

        $photoA = Photo::factory()->create([
            'station_id' => $station->id,
            'line_id' => $lineA->id,
            'is_published' => true,
            'processing_status' => PhotoProcessingStatus::Ready,
        ]);
        $photoB = Photo::factory()->create([
            'station_id' => $station->id,
            'line_id' => $lineB->id,
            'is_published' => true,
            'processing_status' => PhotoProcessingStatus::Ready,
        ]);

        Livewire::test(\App\Livewire\StationGallery::class, ['station' => $station])
            ->assertViewHas('photos', fn ($photos) => $photos->total() === 2)
            ->call('selectLine', $lineA->id)
            ->assertViewHas('photos', fn ($photos) => $photos->total() === 1 && $photos->first()->id === $photoA->id);
    }

    public function test_station_gallery_hides_line_filter_for_single_line_stations(): void
    {
        $station = Station::factory()->create();
        $line = Line::factory()->create();
        $station->lines()->attach($line->id);
        Photo::factory()->create(['station_id' => $station->id, 'is_published' => true, 'processing_status' => PhotoProcessingStatus::Ready]);

        Livewire::test(\App\Livewire\StationGallery::class, ['station' => $station])
            ->assertDontSee('Filtrer par ligne');
    }

    public function test_publishing_a_not_yet_processed_photo_processes_it_in_the_same_step(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not available.');
        }

        Storage::fake('local');
        Storage::fake('public');
        config(['fotometro.photos.process_synchronously' => false]);

        $user = User::factory()->create();
        $station = Station::factory()->create();
        $photo = app(PhotoImporter::class)->import(
            UploadedFile::fake()->image('photo.jpg', 1600, 1000),
            ['station_id' => $station->id, 'license' => 'all_rights_reserved']
        );

        // process_synchronously is off, so this draft is still Pending —
        // publishing it should process it on the spot rather than requiring
        // a separate manual "Traiter" click first.
        $this->assertSame(PhotoProcessingStatus::Pending, $photo->processing_status);

        $this->actingAs($user)
            ->post(route('admin.photos.publish', $photo))
            ->assertSessionHas('status', 'Photo publiée.');

        $photo->refresh();
        $this->assertSame(PhotoProcessingStatus::Ready, $photo->processing_status);
        $this->assertTrue($photo->is_published);
    }

    public function test_bulk_publish_and_manual_processing_limit(): void
    {
        $user = User::factory()->create();
        $ready = Photo::factory()->count(2)->create(['is_published' => false, 'published_at' => null]);
        $pending = Photo::factory()->create(['processing_status' => PhotoProcessingStatus::Pending, 'is_published' => false]);

        $this->actingAs($user)->post(route('admin.photos.bulk'), [
            'bulk_action' => 'publish',
            'photo_ids' => [...$ready->pluck('id')->all(), $pending->id],
        ])->assertSessionHas('status', '2 photo(s) traitée(s), 1 ignorée(s).');

        $this->assertSame(2, Photo::query()->where('is_published', true)->count());

        config(['fotometro.photos.manual_process_limit' => 1]);

        $this->actingAs($user)->post(route('admin.photos.bulk'), [
            'bulk_action' => 'process',
            'photo_ids' => [$ready[0]->id, $ready[1]->id],
        ])->assertSessionHas('status', 'Ce lot est trop important pour un traitement immédiat. Il sera traité progressivement.');
    }

    public function test_admin_store_uses_simplified_publish_mode(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $station = Station::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('photos.upload.store'), [
            'license' => 'all_rights_reserved',
            'publish_mode' => 'auto',
            'files' => [
                UploadedFile::fake()->image('one.jpg', 800, 600),
                UploadedFile::fake()->image('two.jpg', 800, 600),
            ],
            'photos' => [
                ['station_id' => $station->id],
                ['station_id' => $station->id],
            ],
        ])->assertRedirect(route('admin.photos.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('photos', 2);
        $this->assertTrue(Photo::query()->get()->every(fn (Photo $photo) => $photo->publish_when_ready));
    }

    public function test_admin_store_supports_a_different_station_and_category_per_photo(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $stationA = Station::factory()->create(['name' => 'Bastille']);
        $stationB = Station::factory()->create(['name' => 'Nation']);
        $category = PhotoCategory::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('photos.upload.store'), [
            'license' => 'all_rights_reserved',
            'publish_mode' => 'draft',
            'files' => [
                UploadedFile::fake()->image('one.jpg', 800, 600),
                UploadedFile::fake()->image('two.jpg', 800, 600),
            ],
            'photos' => [
                ['station_id' => $stationA->id, 'photo_category_ids' => [$category->id], 'description' => 'Quai ligne 1'],
                ['station_id' => $stationB->id, 'description' => 'Entrée principale'],
            ],
        ])->assertRedirect(route('admin.photos.index'));

        $photoA = Photo::query()->where('station_id', $stationA->id)->firstOrFail();
        $photoB = Photo::query()->where('station_id', $stationB->id)->firstOrFail();

        $this->assertDatabaseHas('photos', ['station_id' => $stationA->id, 'description' => 'Quai ligne 1']);
        $this->assertTrue($photoA->categories->pluck('id')->contains($category->id));
        $this->assertDatabaseHas('photos', ['station_id' => $stationB->id, 'description' => 'Entrée principale']);
        $this->assertTrue($photoB->categories->isEmpty());
    }

    public function test_admin_store_rejects_a_photo_missing_its_station(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())->post(route('photos.upload.store'), [
            'license' => 'all_rights_reserved',
            'publish_mode' => 'auto',
            'files' => [
                UploadedFile::fake()->image('one.jpg', 800, 600),
            ],
            'photos' => [
                ['station_id' => ''],
            ],
        ])->assertSessionHasErrors('photos.0.station_id');

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_public_visibility_station_gallery_photo_page_and_hidden_states(): void
    {
        $station = Station::factory()->create(['is_active' => true]);
        $ready = Photo::factory()->create([
            'station_id' => $station->id,
            'title' => 'Quai central',
            'processing_status' => PhotoProcessingStatus::Ready,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
        Photo::factory()->create([
            'station_id' => $station->id,
            'title' => 'Brouillon',
            'processing_status' => PhotoProcessingStatus::Ready,
            'is_published' => false,
        ]);
        $pending = Photo::factory()->create([
            'station_id' => $station->id,
            'processing_status' => PhotoProcessingStatus::Pending,
            'is_published' => true,
        ]);

        $this->get(route('stations.show', $station))
            ->assertOk()
            ->assertSee('Quai central')
            ->assertDontSee('Brouillon');
        $this->get(route('photos.show', $ready))->assertOk()->assertSee('Quai central')->assertSee($ready->copyright_notice);
        $this->get(route('photos.show', $pending))->assertNotFound();
        $this->assertStringNotContainsString('originals', $this->get(route('photos.show', $ready))->getContent());
    }

    public function test_viewing_a_photo_increments_its_view_count_once_per_session(): void
    {
        $photo = Photo::factory()->create();
        $this->assertSame(0, $photo->fresh()->views_count);

        $this->get(route('photos.show', $photo))->assertOk();
        $this->assertSame(1, $photo->fresh()->views_count);

        // Same session viewing it again shouldn't inflate the count.
        $this->get(route('photos.show', $photo))->assertOk();
        $this->assertSame(1, $photo->fresh()->views_count);

        // A different visitor (nothing recorded in their session yet) does count as a new view.
        $this->withSession(['viewed_photos' => []])->get(route('photos.show', $photo))->assertOk();
        $this->assertSame(2, $photo->fresh()->views_count);
    }

    public function test_station_page_displays_enriched_gallery_filters_and_accesses(): void
    {
        $station = Station::factory()->create([
            'name' => 'Châtelet',
            'slug' => 'chatelet',
            'latitude' => 48.8586,
            'longitude' => 2.347,
            'is_active' => true,
        ]);
        $line = Line::factory()->create(['code' => '1', 'name' => 'Ligne 1', 'slug' => 'ligne-1']);
        $station->lines()->attach($line, ['position' => 1]);
        $root = PhotoCategory::factory()->create(['name' => 'Extérieur', 'slug' => 'exterieur', 'parent_id' => null, 'sort_order' => 1]);
        $child = PhotoCategory::factory()->create(['name' => 'Entrée', 'slug' => 'exterieur-entree', 'parent_id' => $root->id, 'sort_order' => 1]);
        $otherRoot = PhotoCategory::factory()->create(['name' => 'Intérieur', 'slug' => 'interieur', 'parent_id' => null, 'sort_order' => 2]);
        $access = StationAccess::query()->create([
            'external_id' => 'ACCESS:CHATELET:1',
            'name' => 'Accès Rivoli',
            'street' => 'Rue de Rivoli',
            'latitude' => 48.8587,
            'longitude' => 2.3472,
            'is_active' => true,
        ]);
        $station->accesses()->attach($access->id);
        $featured = Photo::factory()->withCategories($child)->create([
            'station_id' => $station->id,
            'station_access_id' => $access->id,
            'title' => 'Entrée Rivoli',
            'is_featured' => true,
            'sort_order' => 2,
            'taken_at' => now()->subDay(),
        ]);
        Photo::factory()->withCategories($otherRoot)->create([
            'station_id' => $station->id,
            'title' => 'Quai central',
            'sort_order' => 3,
        ]);
        Photo::factory()->create([
            'station_id' => $station->id,
            'title' => 'Brouillon',
            'processing_status' => PhotoProcessingStatus::Ready,
            'is_published' => false,
        ]);

        $this->get(route('stations.show', $station))
            ->assertOk()
            ->assertSee('Photos de Châtelet')
            ->assertSee('Entrée Rivoli')
            ->assertSee('% ·')
            ->assertSee('Extérieur (1)')
            ->assertSee('Intérieur (1)')
            ->assertSee('Station et accès')
            ->assertSee('Accès Rivoli')
            ->assertSee('Rue de Rivoli')
            ->assertSee('rasterUrl', false)
            ->assertDontSee('Brouillon');

        $this->assertSame($featured->id, Photo::query()->where('is_featured', true)->first()->id);
    }

    public function test_station_gallery_filters_by_root_category_subcategory_and_access(): void
    {
        $station = Station::factory()->create(['slug' => 'republique']);
        $root = PhotoCategory::factory()->create(['name' => 'Extérieur', 'slug' => 'exterieur']);
        $child = PhotoCategory::factory()->create(['name' => 'Entrée', 'slug' => 'exterieur-entree', 'parent_id' => $root->id]);
        $other = PhotoCategory::factory()->create(['name' => 'Signalétique', 'slug' => 'signaletique']);
        $access = StationAccess::query()->create(['external_id' => 'ACCESS:REP:1', 'name' => 'Accès Temple', 'is_active' => true]);
        $station->accesses()->attach($access->id);

        Photo::factory()->withCategories($child)->create(['station_id' => $station->id, 'station_access_id' => $access->id, 'title' => 'Photo entrée']);
        Photo::factory()->withCategories($other)->create(['station_id' => $station->id, 'title' => 'Photo panneau']);

        // Both photos always appear in the unfiltered hero mosaic (as the image
        // alt text, the hover caption, and the lightbox data-title attribute,
        // so 3 occurrences), regardless of the gallery filter below it. A
        // filtered gallery keeps "Photo panneau" out of the grid, so it must
        // not appear a 4th time.
        foreach ([
            ['category' => 'exterieur'],
            ['category' => 'exterieur-entree'],
            ['access' => $access->id],
        ] as $filter) {
            $html = $this->get(route('stations.show', ['station' => $station, ...$filter]))
                ->assertOk()
                ->assertSee('Photo entrée')
                ->getContent();

            $this->assertSame(3, substr_count($html, 'Photo panneau'), 'Photo panneau should only appear in the hero (alt + caption + lightbox data), not in the filtered gallery grid, for filter '.json_encode($filter));
        }
    }

    public function test_photo_page_hides_empty_metadata_and_uses_station_neighbors_only(): void
    {
        $station = Station::factory()->create();
        $otherStation = Station::factory()->create();
        $previous = Photo::factory()->create(['station_id' => $station->id, 'title' => 'Avant', 'sort_order' => 1]);
        $current = Photo::factory()->create([
            'station_id' => $station->id,
            'title' => 'Courante',
            'sort_order' => 2,
            'camera_make' => null,
            'camera_model' => null,
            'lens' => null,
            'focal_length' => null,
            'aperture' => null,
            'shutter_speed' => null,
            'iso' => null,
        ]);
        $next = Photo::factory()->create(['station_id' => $station->id, 'title' => 'Après', 'sort_order' => 3]);
        Photo::factory()->create(['station_id' => $otherStation->id, 'title' => 'Autre station', 'sort_order' => 2]);

        $response = $this->get(route('photos.show', $current))->assertOk();

        $response->assertSee('Photo précédente')
            ->assertSee(route('photos.show', $previous), false)
            ->assertSee('Photo suivante')
            ->assertSee(route('photos.show', $next), false)
            ->assertDontSee('Autre station')
            ->assertDontSee('Focale')
            ->assertDontSee('ISO');
    }

    public function test_delete_photo_removes_files_and_updates_coverage(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $station = Station::factory()->create(['coverage_status' => CoverageStatus::InProgress]);
        $photo = Photo::factory()->create(['station_id' => $station->id]);
        Storage::disk('local')->put($photo->original_path, 'original');
        Storage::disk('public')->put($photo->web_path, 'web');
        Storage::disk('public')->put($photo->thumbnail_path, 'thumb');

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.photos.destroy', $photo))
            ->assertRedirect(route('admin.photos.index'));

        Storage::disk('local')->assertMissing($photo->original_path);
        Storage::disk('public')->assertMissing($photo->web_path);
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        $this->assertSame(CoverageStatus::NotStarted, $station->fresh()->coverage_status);
    }

    public function test_admin_can_set_and_unset_station_cover_photo(): void
    {
        $station = Station::factory()->create();
        $photo = Photo::factory()->create(['station_id' => $station->id]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.photos.set-cover', $photo))
            ->assertRedirect();

        $this->assertSame($photo->id, $station->fresh()->cover_photo_id);

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.photos.unset-cover', $photo))
            ->assertRedirect();

        $this->assertNull($station->fresh()->cover_photo_id);
    }

    public function test_unpublished_photo_cannot_be_set_as_cover(): void
    {
        $station = Station::factory()->create();
        $photo = Photo::factory()->create(['station_id' => $station->id, 'is_published' => false]);

        $this->actingAs(User::factory()->create())->post(route('admin.photos.set-cover', $photo));

        $this->assertNull($station->fresh()->cover_photo_id);
    }

    public function test_unpublishing_the_cover_photo_clears_it(): void
    {
        $station = Station::factory()->create();
        $photo = Photo::factory()->create(['station_id' => $station->id]);
        $station->forceFill(['cover_photo_id' => $photo->id])->save();

        app(\App\Services\Photos\PhotoPublicationService::class)->unpublish($photo);

        $this->assertNull($station->fresh()->cover_photo_id);
    }

    public function test_cover_photo_is_prioritized_first_in_hero_mosaic(): void
    {
        $station = Station::factory()->create();
        $first = Photo::factory()->create(['station_id' => $station->id, 'sort_order' => 0]);
        $cover = Photo::factory()->create(['station_id' => $station->id, 'sort_order' => 1]);
        $station->forceFill(['cover_photo_id' => $cover->id])->save();

        $html = $this->get(route('stations.show', $station))->assertOk()->getContent();

        $coverPos = strpos($html, route('photos.show', $cover));
        $firstPos = strpos($html, route('photos.show', $first));
        $this->assertNotFalse($coverPos);
        $this->assertNotFalse($firstPos);
        $this->assertLessThan($firstPos, $coverPos);
    }

    public function test_coverage_rule_preserves_manual_planned_and_complete(): void
    {
        $planned = Station::factory()->create(['coverage_status' => CoverageStatus::Planned]);
        $complete = Station::factory()->create(['coverage_status' => CoverageStatus::Complete]);
        $normal = Station::factory()->create(['coverage_status' => CoverageStatus::NotStarted]);

        $access = StationAccess::query()->create(['external_id' => 'ACCESS:RULE:1', 'is_active' => true]);
        $normal->accesses()->attach($access->id);
        Photo::factory()->create(['station_id' => $normal->id, 'station_access_id' => $access->id]);

        app(StationCoverageUpdater::class)->update($planned);
        app(StationCoverageUpdater::class)->update($complete);
        app(StationCoverageUpdater::class)->update($normal);

        $this->assertSame(CoverageStatus::Planned, $planned->fresh()->coverage_status);
        $this->assertSame(CoverageStatus::Complete, $complete->fresh()->coverage_status);
        // All accesses photographed but no platform photo yet: halfway there, not "documented".
        $this->assertSame(CoverageStatus::InProgress, $normal->fresh()->coverage_status);
        $this->assertSame(50, $normal->fresh()->coverage_percentage);

        $quai = PhotoCategory::factory()->create(['slug' => 'interieur-quai']);
        Photo::factory()->withCategories($quai)->create(['station_id' => $normal->id]);
        app(StationCoverageUpdater::class)->update($normal);

        $this->assertSame(CoverageStatus::Documented, $normal->fresh()->coverage_status);
        $this->assertSame(100, $normal->fresh()->coverage_percentage);
    }

    public function test_category_breakdown_and_essential_coverage_use_subcategory_checklist(): void
    {
        $station = Station::factory()->create();

        $exterior = PhotoCategory::factory()->create(['name' => 'Extérieur']);
        $exteriorCovered = PhotoCategory::factory()->create(['parent_id' => $exterior->id]);
        PhotoCategory::factory()->create(['parent_id' => $exterior->id]);
        PhotoCategory::factory()->create(['parent_id' => $exterior->id]);
        PhotoCategory::factory()->create(['parent_id' => $exterior->id]);

        $access = StationAccess::query()->create(['external_id' => 'ACCESS:COVERAGE:1', 'is_active' => true]);
        StationAccess::query()->create(['external_id' => 'ACCESS:COVERAGE:2', 'is_active' => true])->stations()->attach($station->id);
        $station->accesses()->attach($access->id);

        Photo::factory()->withCategories($exteriorCovered)->create([
            'station_id' => $station->id,
            'station_access_id' => $access->id,
        ]);

        $service = app(\App\Services\Photos\StationPhotoCoverageService::class);
        $breakdown = $service->categoryBreakdown($station);
        $exteriorAxis = $breakdown->firstWhere(fn (array $axis) => $axis['category']->is($exterior));

        $this->assertSame(1, $exteriorAxis['covered']);
        $this->assertSame(4, $exteriorAxis['total']);
        $this->assertSame(25, $exteriorAxis['percentage']);
        $this->assertCount(3, $exteriorAxis['missing']);
        $this->assertFalse($exteriorAxis['missing']->contains('id', $exteriorCovered->id));

        $accessBreakdown = $service->accessBreakdown($station);
        $this->assertSame(1, $accessBreakdown['covered']);
        $this->assertSame(2, $accessBreakdown['total']);
        $this->assertSame(50, $accessBreakdown['percentage']);
        $this->assertCount(1, $accessBreakdown['missing']);
        $this->assertFalse($accessBreakdown['missing']->contains('id', $access->id));

        // categoryBreakdown/accessBreakdown stay purely informational ("what's
        // missing"); only accesses + platforms drive essentialCoverage now.
        $essential = $service->essentialCoverage($station, $accessBreakdown);
        $this->assertSame(50, $essential['accesses_percentage']);
        $this->assertFalse($essential['platforms_photographed']);
        $this->assertSame(25, $essential['percentage']);
        $this->assertFalse($essential['complete']);
    }

    public function test_photo_category_seeder_updates_accented_names_without_changing_slugs(): void
    {
        PhotoCategory::query()->create([
            'slug' => 'exterieur',
            'name' => 'Exterieur',
            'is_active' => true,
        ]);

        $this->seed(PhotoCategorySeeder::class);

        $this->assertDatabaseHas('photo_categories', [
            'slug' => 'exterieur',
            'name' => 'Extérieur',
        ]);
        $this->assertDatabaseHas('photo_categories', [
            'slug' => 'architecture-et-decoration-oeuvre-d-art',
            'name' => 'Œuvre d’art',
        ]);
    }

    public function test_photo_category_seeder_gives_entrees_sorties_and_details_techniques_their_own_root(): void
    {
        $this->seed(PhotoCategorySeeder::class);

        $entreesSorties = PhotoCategory::query()->where('slug', 'entrees-et-sorties')->firstOrFail();
        $this->assertNull($entreesSorties->parent_id);
        $this->assertDatabaseHas('photo_categories', ['slug' => 'entrees-et-sorties-entree', 'parent_id' => $entreesSorties->id]);
        $this->assertDatabaseHas('photo_categories', ['slug' => 'entrees-et-sorties-sortie', 'parent_id' => $entreesSorties->id]);

        $detailsTechniques = PhotoCategory::query()->where('slug', 'details-techniques')->firstOrFail();
        $this->assertNull($detailsTechniques->parent_id);
        $this->assertDatabaseHas('photo_categories', ['slug' => 'details-techniques-cablage-electricite', 'parent_id' => $detailsTechniques->id]);

        $exterieur = PhotoCategory::query()->where('slug', 'exterieur')->firstOrFail();
        $this->assertDatabaseMissing('photo_categories', ['slug' => 'exterieur-entree']);
        $this->assertDatabaseMissing('photo_categories', ['slug' => 'exterieur-sortie']);
        $this->assertSame(0, PhotoCategory::query()->where('parent_id', $exterieur->id)->where('name', 'Entrée')->count());
    }

    public function test_admin_can_delete_a_leaf_category_which_untags_photos_without_deleting_them(): void
    {
        $admin = User::factory()->create();
        $category = PhotoCategory::factory()->create();
        $photo = Photo::factory()->create();
        $photo->categories()->attach($category->id);

        $this->actingAs($admin)
            ->delete(route('admin.photo-categories.destroy', $category))
            ->assertRedirect(route('admin.photo-categories.index'));

        $this->assertDatabaseMissing('photo_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('photo_photo_category', ['photo_category_id' => $category->id]);
        $this->assertNotNull(Photo::find($photo->id));
    }

    public function test_admin_cannot_delete_a_category_that_still_has_children(): void
    {
        $admin = User::factory()->create();
        $root = PhotoCategory::factory()->create();
        PhotoCategory::factory()->create(['parent_id' => $root->id]);

        $this->actingAs($admin)
            ->delete(route('admin.photo-categories.destroy', $root))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('photo_categories', ['id' => $root->id]);
    }

    public function test_admin_can_reorder_categories_via_drag_and_drop_endpoint(): void
    {
        $admin = User::factory()->create();
        $first = PhotoCategory::factory()->create(['sort_order' => 0]);
        $second = PhotoCategory::factory()->create(['sort_order' => 1]);
        $third = PhotoCategory::factory()->create(['sort_order' => 2]);

        $this->actingAs($admin)
            ->postJson(route('admin.photo-categories.reorder'), [
                'ids' => [$third->id, $first->id, $second->id],
            ])
            ->assertOk();

        $this->assertSame(0, $third->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(2, $second->fresh()->sort_order);
    }

    public function test_shared_logo_component_is_visible_on_public_auth_and_admin_pages(): void
    {
        $station = Station::factory()->create();
        $line = Line::factory()->create();
        $line->stations()->attach($station->id, ['position' => 1]);
        $photo = Photo::factory()->create(['station_id' => $station->id]);
        $user = User::factory()->create();

        $this->get(route('stations.show', $station))
            ->assertOk()
            ->assertSee('fotométro');

        $this->get(route('lines.show', $line))
            ->assertOk()
            ->assertSee('fotométro');

        $this->get(route('photos.show', $photo))
            ->assertOk()
            ->assertSee('fotométro');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('fotométro');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('fotométro');
    }
}

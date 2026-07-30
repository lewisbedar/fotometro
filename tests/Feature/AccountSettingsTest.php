<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_username_email_and_copyright_display_name(): void
    {
        $user = User::factory()->regularUser()->create();

        $this->actingAs($user)
            ->patch(route('settings.update'), [
                'username' => 'nouveau-pseudo',
                'email' => 'nouveau@example.com',
                'copyright_display_name' => 'N. Pseudo',
            ])
            ->assertRedirect(route('settings.edit'));

        $user->refresh();
        $this->assertSame('nouveau-pseudo', $user->username);
        $this->assertSame('nouveau@example.com', $user->email);
        $this->assertSame('N. Pseudo', $user->copyright_display_name);
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->regularUser()->create(['username' => 'deja-pris']);
        $user = User::factory()->regularUser()->create();

        $this->actingAs($user)
            ->patch(route('settings.update'), [
                'username' => 'deja-pris',
                'email' => $user->email,
            ])
            ->assertSessionHasErrors('username');
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->regularUser()->create(['email' => 'prise@example.com']);
        $user = User::factory()->regularUser()->create();

        $this->actingAs($user)
            ->patch(route('settings.update'), [
                'username' => $user->username,
                'email' => 'prise@example.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_account_closure_requires_the_correct_password(): void
    {
        $user = User::factory()->regularUser()->create(['password' => bcrypt('le-bon-mot-de-passe')]);

        $this->actingAs($user)
            ->delete(route('settings.destroy'), ['password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($user->fresh());
    }

    public function test_account_closure_deletes_the_user_but_keeps_their_published_photos(): void
    {
        $user = User::factory()->regularUser()->create(['password' => bcrypt('le-bon-mot-de-passe')]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('settings.destroy'), ['password' => 'le-bon-mot-de-passe'])
            ->assertRedirect(route('home'));

        $this->assertNull(User::find($user->id));
        $this->assertNull($photo->fresh()->user_id);
        $this->assertTrue($photo->fresh()->is_published);
    }

    public function test_the_last_remaining_admin_cannot_close_their_own_account(): void
    {
        $admin = User::factory()->create(['password' => bcrypt('le-bon-mot-de-passe')]);

        $this->actingAs($admin)
            ->delete(route('settings.destroy'), ['password' => 'le-bon-mot-de-passe'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($admin->fresh());
    }
}

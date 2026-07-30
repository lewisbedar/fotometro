<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use App\Notifications\NewRegistrationPendingNotification;
use App\Notifications\UserAccountApprovedNotification;
use App\Notifications\UserAccountRejectedNotification;
use Tests\TestCase;

class UserRegistrationAndAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_pending_user_and_does_not_log_in(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Nouvelle Personne',
            'email' => 'nouvelle@example.com',
            'password' => 'MotDePasse1234',
            'password_confirmation' => 'MotDePasse1234',
        ]);

        $response->assertRedirect(route('register.pending'));
        $this->assertGuest();

        $user = User::query()->where('email', 'nouvelle@example.com')->firstOrFail();
        $this->assertSame(UserRole::User, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertNotNull($user->username);
    }

    public function test_registration_notifies_every_admin(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $moderator = User::factory()->moderator()->create();

        $this->post(route('register.store'), [
            'name' => 'Nouvelle Personne',
            'email' => 'nouvelle@example.com',
            'password' => 'MotDePasse1234',
            'password_confirmation' => 'MotDePasse1234',
        ]);

        Notification::assertSentTo($admin, NewRegistrationPendingNotification::class);
        Notification::assertNotSentTo($moderator, NewRegistrationPendingNotification::class);
    }

    public function test_registration_ignores_a_role_or_status_supplied_in_the_request(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Attaquant',
            'email' => 'attaquant@example.com',
            'password' => 'MotDePasse1234',
            'password_confirmation' => 'MotDePasse1234',
            'role' => 'admin',
            'status' => 'approved',
        ]);

        $user = User::query()->where('email', 'attaquant@example.com')->firstOrFail();
        $this->assertSame(UserRole::User, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
    }

    public function test_pending_user_cannot_log_in(): void
    {
        $user = User::factory()->regularUser()->pending()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_rejected_user_cannot_log_in_and_sees_the_reason(): void
    {
        $user = User::factory()->regularUser()->rejected()->create([
            'rejection_reason' => 'Adresse email invalide',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Adresse email invalide', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_admin_approval_allows_the_user_to_log_in(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $pendingUser = User::factory()->regularUser()->pending()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.approve', $pendingUser))
            ->assertRedirect();

        Notification::assertSentTo($pendingUser, UserAccountApprovedNotification::class);

        $this->assertSame(UserStatus::Approved, $pendingUser->fresh()->status);

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => $pendingUser->email,
            'password' => 'password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($pendingUser);
    }

    public function test_admin_rejection_records_reason_and_notifies_the_user(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $pendingUser = User::factory()->regularUser()->pending()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.reject', $pendingUser), ['rejection_reason' => 'Doublon de compte'])
            ->assertRedirect();

        $pendingUser->refresh();
        $this->assertSame(UserStatus::Rejected, $pendingUser->status);
        $this->assertSame('Doublon de compte', $pendingUser->rejection_reason);

        Notification::assertSentTo($pendingUser, UserAccountRejectedNotification::class);
    }

    public function test_admin_cannot_reject_their_own_account(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.reject', $admin))
            ->assertStatus(422);

        $this->assertSame(UserStatus::Approved, $admin->fresh()->status);
    }

    public function test_password_reset_request_returns_a_generic_message_whether_or_not_the_email_exists(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        $messageForRealEmail = session('status');

        $this->post(route('password.email'), ['email' => 'inconnu@example.com']);
        $messageForFakeEmail = session('status');

        $this->assertNotNull($messageForRealEmail);
        $this->assertSame($messageForRealEmail, $messageForFakeEmail);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NouveauMotDePasse456',
            'password_confirmation' => 'NouveauMotDePasse456',
        ])->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'NouveauMotDePasse456',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}

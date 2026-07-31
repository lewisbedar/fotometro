<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NewRegistrationPendingNotification;
use App\Services\Photos\AuthShowcasePhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(AuthShowcasePhoto $showcasePhoto): View
    {
        return view('auth.register', ['showcasePhoto' => $showcasePhoto->random()]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: the "website" field is hidden from real visitors via CSS, so
        // only a bot that blindly fills every input ever populates it. Pretend
        // success — same redirect as a real registration, no account created, no
        // validation error — so the bot has no signal it was caught.
        if ($request->filled('website')) {
            Log::info('Registration honeypot triggered', ['ip' => $request->ip()]);

            return redirect()->route('register.pending');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'username' => $this->uniqueUsername($data['name']),
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // role/status default to User/Pending at the database level, but set
        // explicitly here too so the intent isn't hidden behind a column
        // default someone could later change without noticing this depends
        // on it — registration must never grant anything beyond "pending".
        $user->forceFill([
            'role' => UserRole::User,
            'status' => UserStatus::Pending,
        ])->save();

        Notification::send(
            User::query()->where('role', UserRole::Admin)->get(),
            new NewRegistrationPendingNotification($user),
        );

        return redirect()->route('register.pending');
    }

    private function uniqueUsername(string $name): string
    {
        $slug = Str::slug($name) ?: 'utilisateur';
        $candidate = $slug;
        $index = 2;

        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = "{$slug}-{$index}";
            $index++;
        }

        return $candidate;
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent à aucun compte.',
            ]);
        }

        $user = Auth::user();

        if ($user->status !== UserStatus::Approved) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => match ($user->status) {
                    UserStatus::Rejected => 'Votre inscription a été refusée'.($user->rejection_reason ? " : {$user->rejection_reason}" : '.'),
                    default => 'Votre compte est en attente de validation par un administrateur.',
                },
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($user->canModerate() ? route('admin.dashboard') : route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}

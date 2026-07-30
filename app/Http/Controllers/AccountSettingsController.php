<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', [
            'settingsUser' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => [
                'required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'copyright_display_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user->update($data);

        return redirect()->route('settings.edit')->with('status', 'Paramètres mis à jour.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // A solo admin closing their own account would leave nobody able to
        // moderate or manage the site — block it rather than lock everyone out.
        if ($user->isAdmin() && User::query()->where('role', UserRole::Admin)->count() <= 1) {
            return back()->withErrors(['password' => 'Vous ne pouvez pas clôturer le dernier compte administrateur.']);
        }

        Auth::guard('web')->logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Votre compte a été clôturé.');
    }
}

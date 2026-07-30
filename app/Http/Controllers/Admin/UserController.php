<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\UserAccountApprovedNotification;
use App\Notifications\UserAccountRejectedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $status = UserStatus::tryFrom((string) $request->get('status')) ?? UserStatus::Pending;

        return view('admin.users.index', [
            'users' => User::query()
                ->where('status', $status)
                ->orderBy('created_at')
                ->paginate(20)
                ->withQueryString(),
            'statuses' => UserStatus::cases(),
            'currentStatus' => $status,
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        $user->forceFill([
            'status' => UserStatus::Approved,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'rejection_reason' => null,
        ])->save();

        $user->notify(new UserAccountApprovedNotification);

        return back()->with('status', "Compte de {$user->name} approuvé.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Vous ne pouvez pas refuser votre propre compte.');

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user->forceFill([
            'status' => UserStatus::Rejected,
            'approved_at' => null,
            'approved_by' => $request->user()->id,
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ])->save();

        $user->notify(new UserAccountRejectedNotification($user->rejection_reason));

        return back()->with('status', "Compte de {$user->name} refusé.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'editUser' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        // A solo admin demoting themselves (or being demoted) would leave
        // nobody able to manage the site — block it, same guard as closing
        // the last admin's own account in AccountSettingsController.
        if (
            $user->isAdmin()
            && $data['role'] !== UserRole::Admin->value
            && User::query()->where('role', UserRole::Admin)->count() <= 1
        ) {
            return back()->withErrors(['role' => 'Impossible de retirer le rôle administrateur du dernier compte admin.'])->withInput();
        }

        $user->update([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
        ]);
        $user->forceFill(['role' => $data['role']])->save();

        return redirect()->route('admin.users.index', ['status' => $user->status->value])
            ->with('status', "Compte de {$user->name} mis à jour.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas supprimer votre propre compte depuis cette page.']);
        }

        if ($user->isAdmin() && User::query()->where('role', UserRole::Admin)->count() <= 1) {
            return back()->withErrors(['user' => 'Impossible de supprimer le dernier compte administrateur.']);
        }

        $name = $user->name;
        $status = $user->status->value;
        $user->delete();

        return redirect()->route('admin.users.index', ['status' => $status])->with('status', "Compte de {$name} supprimé.");
    }
}

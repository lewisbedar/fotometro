<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\UserAccountApprovedNotification;
use App\Notifications\UserAccountRejectedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Always the same generic message whether or not the address exists
        // in the system — anything else would let an attacker enumerate
        // registered emails one guess at a time.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Si cette adresse correspond à un compte, un email de réinitialisation vient de lui être envoyé.');
    }
}

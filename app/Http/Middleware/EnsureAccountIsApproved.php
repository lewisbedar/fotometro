<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsApproved
{
    /**
     * Catches a session that was authenticated while the account was
     * approved but has since been revoked (rejected/reset to pending) —
     * the login-time check in AuthenticatedSessionController doesn't cover
     * an already-open session, so this must fully invalidate it here too,
     * not just call Auth::logout().
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== UserStatus::Approved) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Votre compte n’est plus approuvé.');
        }

        return $next($request);
    }
}

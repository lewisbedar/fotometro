<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('map-search', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        Gate::define('access-admin-panel', fn (User $user) => $user->canModerate());
        Gate::define('manage-catalog', fn (User $user) => $user->isAdmin());
        Gate::define('moderate-photos', fn (User $user) => $user->canModerate());

        // The "have I been pwned" breach check makes an external HTTP call,
        // which has no place running against every login attempt in tests
        // (and would silently fail/slow down in network-restricted
        // environments) — only enforce it in production.
        Password::defaults(function () {
            $rule = Password::min(10)->letters()->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}

<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Managing statuses, the roadmap and votings is reserved for admins.
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Brute-force protection for the login form and spam protection for new ideas.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip().'|'.$request->input('email')));
        RateLimiter::for('ideas', fn (Request $request) => Limit::perHour(10)->by($request->user()?->id ?: $request->ip()));
    }
}

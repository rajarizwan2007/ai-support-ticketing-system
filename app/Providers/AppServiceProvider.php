<?php

namespace App\Providers;

use App\Models\Organization;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Scribe;

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
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Scribe (dev only) builds example responses from factories, which need a tenant.
        if (class_exists(Scribe::class)) {
            Scribe::bootstrap(fn () => Organization::first()?->makeCurrent());
        }
    }
}

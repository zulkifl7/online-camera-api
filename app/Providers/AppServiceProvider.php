<?php

namespace App\Providers;

use App\Models\Photo;
use App\Policies\PhotoPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Photo::class, PhotoPolicy::class);

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('client-login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('username')).'|'.$request->ip());
        });

        RateLimiter::for('camera-upload', function (Request $request) {
            return Limit::perMinute(30)->by(sha1((string) $request->route('token').'|'.$request->ip()));
        });
    }
}

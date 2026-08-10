<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        //
    }

  
    public function boot(): void
    {
        if (config('app.env') === 'production' || str_contains(config('app.url'), 'ngrok')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('auth-throttle', function (Request $request) {
            $maxAttempts = app()->environment('production') ? 5 : 20;

            return Limit::perMinute($maxAttempts)->by($request->ip());
        });
    }
}
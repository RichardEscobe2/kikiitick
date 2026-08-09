<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // 🔒 Forzar HTTPS si estamos en producción o usando un túnel de Ngrok
        if (config('app.env') === 'production' || str_contains(config('app.url'), 'ngrok')) {
            URL::forceScheme('https');
        }

        // 🔒 RN-02: throttle de login/registro público
        RateLimiter::for('auth-throttle', function (Request $request) {
            $maxAttempts = app()->environment('production') ? 5 : 20;

            return Limit::perMinute($maxAttempts)->by($request->ip());
        });
    }
}
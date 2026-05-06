<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        // Force HTTPS URLs when behind proxy (ngrok, cloudflare, etc.)
        if (request()->header('X-Forwarded-Proto') === 'https' || 
            request()->header('X-Forwarded-Ssl') === 'on' ||
            str_contains(request()->header('Host', ''), 'ngrok')) {
            URL::forceScheme('https');
        }
    }
}

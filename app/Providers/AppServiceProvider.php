<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiters();

        // Force HTTPS URLs when behind proxy (ngrok, cloudflare, etc.)
        if (request()->header('X-Forwarded-Proto') === 'https' || 
            request()->header('X-Forwarded-Ssl') === 'on' ||
            str_contains(request()->header('Host', ''), 'ngrok')) {
            URL::forceScheme('https');
        }
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('student-login', function (Request $request) {
            return Limit::perMinute(8)->by($request->ip() . '|student|' . $request->input('student_id'));
        });

        RateLimiter::for('staff-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip() . '|staff|' . strtolower((string) $request->input('email')));
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip() . '|password|' . strtolower((string) $request->input('email')));
        });

        RateLimiter::for('walkin', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip() . '|walkin|' . $request->route('token'));
        });

        RateLimiter::for('chat-send', function (Request $request) {
            return Limit::perMinute(30)->by(($request->user()?->id ?? $request->ip()) . '|chat');
        });

        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(10)->by(($request->user()?->id ?? $request->ip()) . '|upload');
        });

        RateLimiter::for('status', function (Request $request) {
            return Limit::perMinute(120)->by(($request->user()?->id ?? $request->ip()) . '|status');
        });

        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(12)->by(($request->user()?->id ?? $request->ip()) . '|exports');
        });
    }
}

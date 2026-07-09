<?php

namespace App\Providers;

use App\Events\ActivityPublished;
use App\Events\AnnouncementPublished;
use App\Events\JobPublished;
use App\Listeners\SendLineActivityNotification;
use App\Listeners\SendLineAnnouncementNotification;
use App\Listeners\SendLineJobNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        $this->configureRateLimiters();
        $this->registerLineEvents();

        // ตรวจสอบและบังคับใช้โปรโตคอลและโดเมนตามที่เรียกเข้ามาจริง (รองรับทั้ง localhost และ ngrok)
        if (!app()->runningInConsole()) {
            $currentHost = request()->getSchemeAndHttpHost();
            URL::forceRootUrl($currentHost);

            if (request()->header('X-Forwarded-Proto') === 'https' || 
                request()->header('X-Forwarded-Ssl') === 'on' ||
                str_contains(request()->header('Host', ''), 'ngrok')) {
                URL::forceScheme('https');
            }
        }
    }

    /** ลงทะเบียน Event → Listener สำหรับ LINE Notifications */
    private function registerLineEvents(): void
    {
        Event::listen(ActivityPublished::class,    SendLineActivityNotification::class);
        Event::listen(JobPublished::class,         SendLineJobNotification::class);
        Event::listen(AnnouncementPublished::class, SendLineAnnouncementNotification::class);
    }

    private function configureRateLimiters(): void
    {
        // 1. Student Login: Allow 10 attempts per specific student, and max 100 attempts per IP
        RateLimiter::for('student-login', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->ip() . '|student|' . $request->input('student_id')),
                Limit::perMinute(100)->by($request->ip() . '|student-global')
            ];
        });

        // 2. Staff Login: 5 attempts per email, 50 per IP
        RateLimiter::for('staff-login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip() . '|staff|' . strtolower((string) $request->input('email'))),
                Limit::perMinute(50)->by($request->ip() . '|staff-global')
            ];
        });

        // 3. Password Reset
        RateLimiter::for('password-reset', function (Request $request) {
            return [
                Limit::perMinute(3)->by($request->ip() . '|password|' . strtolower((string) $request->input('email'))),
                Limit::perMinute(20)->by($request->ip() . '|password-global')
            ];
        });

        // 4. Walk-in Registration
        RateLimiter::for('walkin', function (Request $request) {
            $identifier = $request->session()->getId() ?: $request->userAgent();
            return [
                Limit::perMinute(30)->by($request->ip() . '|walkin|' . $identifier),
                Limit::perMinute(200)->by($request->ip() . '|walkin-global')
            ];
        });

        // 5. Chat Send
        RateLimiter::for('chat-send', function (Request $request) {
            $user = $request->user()?->id ?: ($request->session()->getId() ?: $request->userAgent());
            return [
                Limit::perMinute(60)->by($user . '|chat'),
                Limit::perMinute(300)->by($request->ip() . '|chat-global')
            ];
        });

        // 6. Upload
        RateLimiter::for('upload', function (Request $request) {
            $user = $request->user()?->id ?: ($request->session()->getId() ?: $request->userAgent());
            return [
                Limit::perMinute(20)->by($user . '|upload'),
                Limit::perMinute(100)->by($request->ip() . '|upload-global')
            ];
        });

        // 7. Status Polling (High limit for device, higher for IP)
        RateLimiter::for('status', function (Request $request) {
            $user = $request->user()?->id ?: ($request->session()->getId() ?: $request->userAgent());
            return [
                Limit::perMinute(150)->by($user . '|status'),
                Limit::perMinute(1000)->by($request->ip() . '|status-global')
            ];
        });

        // 8. Exports
        RateLimiter::for('exports', function (Request $request) {
            $user = $request->user()?->id ?: ($request->session()->getId() ?: $request->userAgent());
            return [
                Limit::perMinute(15)->by($user . '|exports'),
                Limit::perMinute(50)->by($request->ip() . '|exports-global')
            ];
        });
    }
}

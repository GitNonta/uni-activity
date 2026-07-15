<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ActivityPublished;
use App\Events\AnnouncementPublished;
use App\Events\JobPublished;
use App\Listeners\SendLineActivityNotification;
use App\Listeners\SendLineAnnouncementNotification;
use App\Listeners\SendLineJobNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private array $commandStartTimes = [];

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
        $this->registerConsoleCommandLogger();
        
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

    /** ลงทะเบียน Event → Listener สำหรับ LINE Notifications */
    private function registerLineEvents(): void
    {
        Event::listen(ActivityPublished::class,    SendLineActivityNotification::class);
        Event::listen(JobPublished::class,         SendLineJobNotification::class);
        Event::listen(AnnouncementPublished::class, SendLineAnnouncementNotification::class);
    }

    /**
     * Register Artisan console command logger.
     */
    private function registerConsoleCommandLogger(): void
    {
        if ($this->app->runningInConsole()) {
            Event::listen(CommandStarting::class, function (CommandStarting $event): void {
                if ($event->command) {
                    $key = $event->command . '_' . getmypid();
                    $this->commandStartTimes[$key] = microtime(true);
                }
            });

            Event::listen(CommandFinished::class, function (CommandFinished $event): void {
                $command = $event->command;
                if (!$command) {
                    return;
                }

                // Skip extremely spammy commands
                if (in_array($command, ['package:discover', 'vendor:publish', 'schedule:run'])) {
                    return;
                }

                $key = $command . '_' . getmypid();
                $startTime = $this->commandStartTimes[$key] ?? null;
                $duration = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : 0;

                $input = $event->input->__toString();
                $exitCode = $event->exitCode;

                $this->sendToInspector('ARTISAN', $command, $input, $exitCode, $duration);
            });
        }
    }

    /**
     * Send command info to UDP inspector.
     */
    private function sendToInspector(string $method, string $command, string $input, int $exitCode, float $duration): void
    {
        try {
            $data = [
                'method' => $method,
                'url' => 'artisan ' . $command,
                'path' => 'artisan ' . $command . ' ' . $input,
                'ip' => 'console',
                'duration' => $duration,
                'status' => $exitCode,
                'time' => now()->toIso8601String(),
                'request' => [
                    'headers' => [
                        'Environment' => app()->environment(),
                        'PID' => (string) getmypid(),
                    ],
                    'body' => $input,
                ],
                'response' => [
                    'headers' => [],
                    'body' => $exitCode === 0 ? 'Command finished successfully.' : 'Command failed with exit status ' . $exitCode,
                ]
            ];

            $payload = json_encode($data);
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket) {
                socket_set_nonblock($socket);
                socket_sendto($socket, $payload, strlen($payload), 0, '127.0.0.1', 9998);
                socket_close($socket);
            }
        } catch (\Throwable $e) {
            // Silence exceptions to avoid disrupting the command run
        }
    }
}

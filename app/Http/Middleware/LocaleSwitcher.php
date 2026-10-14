<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LocaleSwitcher
{
    /** @var list<string> */
    protected array $allowed = ['th', 'en'];

    public function handle(Request $request, Closure $next)
    {
        $locale = (string) Session::get('locale', (string) config('app.locale', 'th'));
        if (in_array($locale, $this->allowed, true)) {
            app()->setLocale($locale);
        }
        return $next($request);
    }
}

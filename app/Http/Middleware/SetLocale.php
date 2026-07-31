<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'id'];

    /**
     * Applies the user's session-stored locale preference (set via the locale
     * switcher route) to every request. Must run after StartSession — the panel
     * provider registers it right after StartSession in its middleware stack.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale');

        if (is_string($locale) && in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}

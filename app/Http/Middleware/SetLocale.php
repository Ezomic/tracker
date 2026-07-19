<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the authenticated user's preferred locale for the request, so
     * server-side messages (flash toasts, validation) match their language.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if ($locale !== null && in_array($locale, config('app.supported_locales', ['en']), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}

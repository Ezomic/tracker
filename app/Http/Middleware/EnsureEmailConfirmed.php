<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passwordless equivalent of Fortify's RequirePassword: gates sensitive
 * actions (passkey management) behind a recent email-code confirmation
 * instead of a password, since the app has no passwords.
 *
 * Runs on the web group and self-scopes to the passkey management routes,
 * since Fortify owns their registration and offers no custom-middleware hook.
 */
class EnsureEmailConfirmed
{
    public const SESSION_KEY = 'auth.email_confirmed_at';

    public const WINDOW_SECONDS = 900;

    private const GATED_ROUTES = [
        'passkey.registration-options',
        'passkey.store',
        'passkey.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(...self::GATED_ROUTES) || self::confirmedRecently($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(423, 'Email confirmation required.');
        }

        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('security.confirm');
    }

    public static function confirmedRecently(Request $request): bool
    {
        $confirmedAt = $request->session()->get(self::SESSION_KEY);

        return $confirmedAt !== null && (time() - (int) $confirmedAt) <= self::WINDOW_SECONDS;
    }
}

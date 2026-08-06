<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A service account is a User, so every login path in the app (email code,
 * passkey, and the SSO callback inside id-client) could in principle produce a
 * session for one. Rather than patching each of those, this sits on the web
 * group and refuses the session wherever it came from.
 */
class DenyServiceAccountSessions
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof User && $user->is_service) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Service accounts cannot sign in.');
        }

        return $next($request);
    }
}

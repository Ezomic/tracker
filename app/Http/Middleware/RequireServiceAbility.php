<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The counterpart to DenyServiceAccounts: it sits on the few routes service
 * accounts may reach and checks the token actually carries the ability.
 *
 * Human callers pass straight through. Sanctum's own `abilities` middleware
 * cannot be used here because it rejects session-authenticated requests, which
 * have no access token at all.
 */
class RequireServiceAbility
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_service && ! $user->tokenCan($ability)) {
            abort(403, "This token is not allowed to {$ability}.");
        }

        return $next($request);
    }
}

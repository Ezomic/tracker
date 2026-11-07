<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Integrations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The ID client package registers its SSO routes unconditionally. Without the
 * ID settings they could only fail, so they answer 404 as if absent.
 */
class CloseSsoWhenUnconfigured
{
    public function __construct(private readonly Integrations $integrations) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->routeIs('sso.redirect', 'sso.callback') && ! $this->integrations->sso(), 404);

        return $next($request);
    }
}

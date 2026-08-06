<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a deprecated route with the date it will be removed, per RFC 8594, so
 * the removal is visible to a consumer rather than living only in the README.
 *
 * See docs/api-versioning-2026-08-06.md.
 */
class AnnounceSunset
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $date, string $replacement = ''): Response
    {
        $response = $next($request);

        $response->headers->set('Sunset', Carbon::parse($date)->toRfc7231String());
        $response->headers->set('Deprecation', 'true');

        if ($replacement !== '') {
            $response->headers->set('Link', '<'.url($replacement).'>; rel="successor-version"');
        }

        return $response;
    }
}

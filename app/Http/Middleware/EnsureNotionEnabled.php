<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Integrations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Without a Notion token the mirror settings answer 404, as if the feature did
 * not exist, so nothing can be linked that could never sync.
 */
class EnsureNotionEnabled
{
    public function __construct(private readonly Integrations $integrations) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->integrations->notion(), 404);

        return $next($request);
    }
}

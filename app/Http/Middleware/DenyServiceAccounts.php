<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service accounts exist to file issues. Everything else on the API - projects,
 * members, labels, templates, categories, time - stays closed to them, so a
 * leaked ingest token cannot reshape the workspace.
 */
class DenyServiceAccounts
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_service) {
            abort(403, 'Service accounts may only read and file issues.');
        }

        return $next($request);
    }
}

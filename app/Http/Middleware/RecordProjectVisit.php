<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\RecordProjectVisitAction;
use App\Models\User;
use App\Support\CurrentProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feeds the sidebar's Recent group. Runs after the controller so a refused
 * or missing project (403/404) is never recorded.
 */
class RecordProjectVisit
{
    public function __construct(private readonly RecordProjectVisitAction $recordVisit) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $projectId = CurrentProject::id($request);

        if ($user instanceof User && $projectId !== null && $response->isSuccessful()) {
            $this->recordVisit->handle($user, $projectId);
        }

        return $response;
    }
}

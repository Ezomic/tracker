<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGithubWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.github.webhook_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        if ($secret === null || ! hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}

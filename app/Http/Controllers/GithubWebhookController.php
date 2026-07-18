<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApplyGithubPullRequestEventAction;
use App\Actions\RecordPushedCommitsAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GithubWebhookController extends Controller
{
    public function handle(
        Request $request,
        ApplyGithubPullRequestEventAction $pullRequests,
        RecordPushedCommitsAction $pushes,
    ): Response {
        $event = $request->header('X-GitHub-Event');
        $payload = (array) $request->json()->all();

        if ($event === 'pull_request') {
            $pullRequests->handle($payload);
        }

        if ($event === 'push') {
            $pushes->handle($payload);
        }

        return response()->noContent();
    }
}

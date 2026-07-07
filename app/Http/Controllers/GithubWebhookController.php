<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApplyGithubPullRequestEventAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GithubWebhookController extends Controller
{
    public function handle(Request $request, ApplyGithubPullRequestEventAction $action): Response
    {
        if ($request->header('X-GitHub-Event') === 'pull_request') {
            $action->handle((array) $request->json()->all());
        }

        return response()->noContent();
    }
}

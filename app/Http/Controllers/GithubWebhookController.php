<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GithubWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        return response()->noContent();
    }
}

<?php

use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\GithubWebhookController;
use App\Http\Middleware\VerifyGithubWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/teams', [TeamController::class, 'index'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::post('/issues', [IssueController::class, 'store'])->middleware(['auth:sanctum', 'throttle:60,1']);

Route::post('/webhooks/github', [GithubWebhookController::class, 'handle'])
    ->middleware(VerifyGithubWebhookSignature::class);

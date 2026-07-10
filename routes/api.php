<?php

use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\GithubWebhookController;
use App\Http\Middleware\VerifyGithubWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/projects', [ProjectController::class, 'index'])->middleware(['auth:sanctum', 'throttle:60,1']);
// Deprecated alias for /projects; kept for existing API consumers during the projects transition.
Route::get('/teams', [ProjectController::class, 'index'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::get('/issues', [IssueController::class, 'index'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::get('/issues/{issue}', [IssueController::class, 'show'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::post('/issues', [IssueController::class, 'store'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::patch('/issues/{issue}', [IssueController::class, 'update'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::patch('/issues/{issue}/status', [IssueController::class, 'updateStatus'])->middleware(['auth:sanctum', 'throttle:60,1']);

Route::post('/webhooks/github', [GithubWebhookController::class, 'handle'])
    ->middleware(VerifyGithubWebhookSignature::class);

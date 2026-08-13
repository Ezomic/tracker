<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\GithubWebhookController;
use App\Http\Middleware\AnnounceSunset;
use App\Http\Middleware\DenyServiceAccounts;
use App\Http\Middleware\RequireServiceAbility;
use App\Http\Middleware\VerifyGithubWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Reads and writes are throttled separately: see AppServiceProvider. A new
// route inherits the right limiter from its group rather than repeating an
// inline throttle string.
//
// The issue routes a service account is allowed to reach carry a
// RequireServiceAbility check; every other route carries DenyServiceAccounts.
// Human callers pass through both untouched.
Route::middleware(['auth:sanctum', 'throttle:api-read'])->group(function (): void {
    Route::middleware(RequireServiceAbility::class.':issues:read')->group(function (): void {
        Route::get('/issues', [IssueController::class, 'index']);
        Route::get('/issues/{issue}', [IssueController::class, 'show']);
    });

    // A service account needs to know which project keys exist before it can
    // file into one, so this is readable with its own ability rather than
    // blanket-denied like the rest of the API.
    Route::get('/projects', [ProjectController::class, 'index'])
        ->middleware(RequireServiceAbility::class.':projects:read');

    Route::middleware(DenyServiceAccounts::class)->group(function (): void {
        Route::get('/user', fn (Request $request) => $request->user());

        Route::get('/projects/{project:key}/members', [ProjectMemberController::class, 'index']);
        // Deprecated alias for /projects, from the projects transition. Now
        // carries a removal date rather than living on indefinitely.
        Route::get('/teams', [ProjectController::class, 'index'])
            ->middleware(AnnounceSunset::class.':2026-09-05,/api/projects');
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/labels', [LabelController::class, 'index']);
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/issues/{issue}/comments', [IssueController::class, 'listComments']);
        Route::get('/issues/{issue}/time', [IssueController::class, 'listTime']);
    });
});

Route::middleware(['auth:sanctum', 'throttle:api-write'])->group(function (): void {
    Route::post('/issues', [IssueController::class, 'store'])->middleware(RequireServiceAbility::class.':issues:create');

    Route::middleware(DenyServiceAccounts::class)->group(function (): void {
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::patch('/projects/{project:key}', [ProjectController::class, 'update']);
        Route::delete('/projects/{project:key}', [ProjectController::class, 'destroy']);
        Route::post('/projects/{project:key}/restore', [ProjectController::class, 'restore']);
        Route::post('/projects/{project:key}/members', [ProjectMemberController::class, 'store']);
        Route::patch('/projects/{project:key}/members/{user}', [ProjectMemberController::class, 'update']);
        Route::delete('/projects/{project:key}/members/{user}', [ProjectMemberController::class, 'destroy']);
        Route::post('/templates', [TemplateController::class, 'store']);
        Route::patch('/templates/{template}', [TemplateController::class, 'update']);
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::patch('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::post('/labels', [LabelController::class, 'store']);
        Route::patch('/labels/{label}', [LabelController::class, 'update']);
        Route::delete('/labels/{label}', [LabelController::class, 'destroy']);
        Route::patch('/issues/{issue}', [IssueController::class, 'update']);
        // `status` is deprecated here in favour of `workflow_state`; the header
        // carries the date the legacy form stops being accepted.
        Route::patch('/issues/{issue}/status', [IssueController::class, 'updateStatus'])
            ->middleware(AnnounceSunset::class.':2026-09-30');
        Route::post('/issues/{issue}/links', [IssueController::class, 'storeLink']);
        Route::delete('/issues/{issue}/links/{link}', [IssueController::class, 'destroyLink']);
        Route::post('/issues/{issue}/comments', [IssueController::class, 'storeComment']);
        Route::patch('/issues/{issue}/comments/{comment}', [IssueController::class, 'updateComment']);
        Route::post('/issues/{issue}/time', [IssueController::class, 'logTime']);
        Route::delete('/issues/{issue}/time/{timeEntry}', [IssueController::class, 'deleteTime']);
        Route::delete('/issues/{issue}', [IssueController::class, 'destroy']);
        Route::post('/issues/{issue}/restore', [IssueController::class, 'restore']);
    });
});

Route::post('/webhooks/github', [GithubWebhookController::class, 'handle'])
    ->middleware(VerifyGithubWebhookSignature::class);

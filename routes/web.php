<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Landing')->name('home');

// Public: the accept flow handles guests by sending them to log in or register first.
Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::put('organizations/{organization:slug}/switch', [OrganizationController::class, 'switch'])
        ->name('organizations.switch');

    Route::get('projects', [ProjectsController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectsController::class, 'store'])->name('projects.store');
    Route::patch('projects/{project}', [ProjectsController::class, 'update'])->name('projects.update');
    Route::patch('projects/{project:key}/favorite', [ProjectsController::class, 'toggleFavorite'])
        ->name('projects.favorite');

    Route::get('projects/{project:key}/members', [ProjectMemberController::class, 'index'])
        ->name('projects.members.index');
    Route::post('projects/{project:key}/members', [ProjectMemberController::class, 'store'])
        ->name('projects.members.store');
    Route::patch('projects/{project:key}/members/{user}', [ProjectMemberController::class, 'update'])
        ->name('projects.members.update');
    Route::delete('projects/{project:key}/members/{user}', [ProjectMemberController::class, 'destroy'])
        ->name('projects.members.destroy');

    Route::get('issues', [IssueController::class, 'index'])->name('issues.index');
    Route::post('issues', [IssueController::class, 'store'])->name('issues.store');
    Route::get('issues/board', [IssueController::class, 'board'])->name('issues.board');
    Route::get('issues/{issue:identifier}', [IssueController::class, 'show'])->name('issues.show');
    Route::patch('issues/{issue:identifier}', [IssueController::class, 'update'])->name('issues.update');
    Route::patch('issues/{issue:identifier}/status', [IssueController::class, 'updateStatus'])->name('issues.updateStatus');

    // Project-scoped views. The uppercase-key constraint keeps these from shadowing
    // lowercase paths like /issues, /dashboard, or /settings.
    Route::get('{project:key}/tickets', [IssueController::class, 'index'])
        ->where('project', '[A-Z]{2,10}')
        ->name('projects.tickets');
    Route::get('{project:key}/board', [IssueController::class, 'board'])
        ->where('project', '[A-Z]{2,10}')
        ->name('projects.board');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

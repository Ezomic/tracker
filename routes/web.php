<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProjectsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Landing')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('projects', [ProjectsController::class, 'index'])->name('projects.browse');
    Route::patch('projects/{project:key}/favorite', [ProjectsController::class, 'toggleFavorite'])
        ->name('projects.favorite');

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

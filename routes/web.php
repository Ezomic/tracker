<?php

use App\Http\Controllers\IssueController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('issues', [IssueController::class, 'index'])->name('issues.index');
    Route::post('issues', [IssueController::class, 'store'])->name('issues.store');
    Route::get('issues/board', [IssueController::class, 'board'])->name('issues.board');
    Route::get('issues/{issue:identifier}', [IssueController::class, 'show'])->name('issues.show');
    Route::patch('issues/{issue:identifier}', [IssueController::class, 'update'])->name('issues.update');
    Route::patch('issues/{issue:identifier}/status', [IssueController::class, 'updateStatus'])->name('issues.updateStatus');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

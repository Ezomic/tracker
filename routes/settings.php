<?php

use App\Http\Controllers\Settings\LabelController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::patch('settings/teams/{team}', [TeamController::class, 'update'])->name('teams.update');

    Route::get('settings/labels', [LabelController::class, 'index'])->name('labels.index');
    Route::post('settings/labels', [LabelController::class, 'store'])->name('labels.store');
    Route::patch('settings/labels/{label}', [LabelController::class, 'update'])->name('labels.update');
    Route::delete('settings/labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');

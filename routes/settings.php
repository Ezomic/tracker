<?php

use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\Settings\EmailConfirmationController;
use App\Http\Controllers\Settings\IssueTemplateController;
use App\Http\Controllers\Settings\LabelController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/labels', [LabelController::class, 'index'])->name('labels.index');
    Route::post('settings/labels', [LabelController::class, 'store'])->name('labels.store');
    Route::patch('settings/labels/{label}', [LabelController::class, 'update'])->name('labels.update');
    Route::delete('settings/labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');

    Route::get('settings/templates', [IssueTemplateController::class, 'index'])->name('templates.index');
    Route::get('settings/template-options', [IssueTemplateController::class, 'options'])->name('templates.options');
    Route::post('settings/templates', [IssueTemplateController::class, 'store'])->name('templates.store');
    Route::patch('settings/templates/{template}', [IssueTemplateController::class, 'update'])->name('templates.update');
    Route::delete('settings/templates/{template}', [IssueTemplateController::class, 'destroy'])->name('templates.destroy');

    Route::get('settings/members', [OrganizationMemberController::class, 'index'])->name('members.index');
    Route::patch('settings/members/{user}', [OrganizationMemberController::class, 'update'])->name('members.update');
    Route::delete('settings/members/{user}', [OrganizationMemberController::class, 'destroy'])->name('members.destroy');

    Route::post('settings/invitations', [OrganizationInvitationController::class, 'store'])->name('invitations.store');
    Route::post('settings/invitations/{invitation}/resend', [OrganizationInvitationController::class, 'resend'])->name('invitations.resend');
    Route::delete('settings/invitations/{invitation}', [OrganizationInvitationController::class, 'destroy'])->name('invitations.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::get('settings/security/confirm', [EmailConfirmationController::class, 'create'])
        ->middleware('throttle:6,1')
        ->name('security.confirm');
    Route::post('settings/security/confirm', [EmailConfirmationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('security.confirm.store');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');

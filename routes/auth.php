<?php

use App\Http\Controllers\Auth\EmailLoginCodeController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest'])->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])
        ->middleware('throttle:login-code')
        ->name('register.store');

    Route::get('login/code', [EmailLoginCodeController::class, 'create'])->name('login.code.create');
    Route::post('login/code', [EmailLoginCodeController::class, 'store'])
        ->middleware('throttle:login-code')
        ->name('login.code.store');

    Route::get('login/code/verify', [EmailLoginCodeController::class, 'verify'])->name('login.code.verify');
    Route::post('login/code/verify', [EmailLoginCodeController::class, 'authenticate'])
        ->middleware('throttle:login-code-verify')
        ->name('login.code.authenticate');
});

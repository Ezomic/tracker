<?php

declare(strict_types=1);

use App\Http\Middleware\AnnounceSunset;
use App\Models\User;
use Illuminate\Support\Facades\Route;

it('announces the removal date and points at the successor when one is named', function () {
    Route::get('/sunset-probe', fn () => 'ok')
        ->middleware(AnnounceSunset::class.':2026-12-01,/api/projects');

    $response = $this->get('/sunset-probe');

    $response->assertOk();
    expect($response->headers->get('Sunset'))->toBe('Tue, 01 Dec 2026 00:00:00 GMT')
        ->and($response->headers->get('Deprecation'))->toBe('true')
        ->and($response->headers->get('Link'))->toContain('rel="successor-version"')
        ->and($response->headers->get('Link'))->toContain('/api/projects');
});

it('leaves the Link header off when no successor is named', function () {
    Route::get('/sunset-probe', fn () => 'ok')
        ->middleware(AnnounceSunset::class.':2026-12-01');

    $response = $this->get('/sunset-probe');

    expect($response->headers->get('Sunset'))->toBe('Tue, 01 Dec 2026 00:00:00 GMT')
        ->and($response->headers->get('Link'))->toBeNull();
});

it('leaves a current endpoint unmarked', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/projects');

    $response->assertOk();
    expect($response->headers->get('Sunset'))->toBeNull()
        ->and($response->headers->get('Deprecation'))->toBeNull();
});

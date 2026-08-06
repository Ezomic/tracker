<?php

declare(strict_types=1);

use App\Models\User;

it('announces the removal date on the deprecated teams alias', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/teams');

    $response->assertOk();
    expect($response->headers->get('Sunset'))->toBe('Sat, 05 Sep 2026 00:00:00 GMT')
        ->and($response->headers->get('Deprecation'))->toBe('true')
        ->and($response->headers->get('Link'))->toContain('rel="successor-version"')
        ->and($response->headers->get('Link'))->toContain('/api/projects');
});

it('leaves the replacement endpoint unmarked', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/projects');

    $response->assertOk();
    expect($response->headers->get('Sunset'))->toBeNull()
        ->and($response->headers->get('Deprecation'))->toBeNull();
});

it('still returns the same body as the endpoint it aliases', function () {
    $user = User::factory()->create();

    $alias = $this->actingAs($user, 'sanctum')->getJson('/api/teams');
    $current = $this->actingAs($user, 'sanctum')->getJson('/api/projects');

    expect($alias->json())->toBe($current->json());
});

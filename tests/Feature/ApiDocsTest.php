<?php

declare(strict_types=1);

use App\Models\User;

it('serves the OpenAPI document to a signed-in user', function () {
    $response = $this->actingAs(User::factory()->create())->getJson('/docs/api.json');

    $response->assertOk();

    $document = $response->json();

    expect($document['openapi'] ?? null)->toStartWith('3.')
        ->and($document['info']['title'] ?? null)->toBe('Tracker')
        ->and($document['paths'] ?? [])->toHaveKey('/issues')
        ->and($document['paths'] ?? [])->toHaveKey('/projects');
});

it('renders the docs UI for a signed-in user', function () {
    $this->actingAs(User::factory()->create())->get('/docs/api')->assertOk();
});

it('forbids guests from viewing the docs', function () {
    $this->get('/docs/api')->assertForbidden();
});

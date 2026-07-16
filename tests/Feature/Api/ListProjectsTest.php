<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;

it('lists projects ordered by key', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software', 'color' => '#d85a30']);
    $billr = Project::factory()->create(['key' => 'BILLR', 'name' => 'Billr', 'color' => '#378add']);
    joinProjects($user, [$thi, $billr]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/projects');

    $response->assertOk()->assertExactJson([
        ['key' => 'BILLR', 'name' => 'Billr', 'color' => '#378add'],
        ['key' => 'THI', 'name' => 'Thijssen Software', 'color' => '#d85a30'],
    ]);
});

it('serves the deprecated /api/teams alias', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software', 'color' => '#d85a30']);
    joinProjects($user, $thi);

    $this->actingAs($user, 'sanctum')->getJson('/api/teams')
        ->assertOk()
        ->assertExactJson([
            ['key' => 'THI', 'name' => 'Thijssen Software', 'color' => '#d85a30'],
        ]);
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/projects')->assertUnauthorized();
});

it('rate limits after 60 requests per minute', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 60; $i++) {
        $this->actingAs($user, 'sanctum')->getJson('/api/projects')->assertOk();
    }

    $this->actingAs($user, 'sanctum')->getJson('/api/projects')->assertStatus(429);
});

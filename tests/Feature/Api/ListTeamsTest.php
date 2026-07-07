<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;

it('lists teams ordered by key', function () {
    $user = User::factory()->create();
    Team::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software']);
    Team::factory()->create(['key' => 'BILLR', 'name' => 'Billr']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/teams');

    $response->assertOk()->assertExactJson([
        ['key' => 'BILLR', 'name' => 'Billr'],
        ['key' => 'THI', 'name' => 'Thijssen Software'],
    ]);
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/teams')->assertUnauthorized();
});

it('rate limits after 60 requests per minute', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 60; $i++) {
        $this->actingAs($user, 'sanctum')->getJson('/api/teams')->assertOk();
    }

    $this->actingAs($user, 'sanctum')->getJson('/api/teams')->assertStatus(429);
});

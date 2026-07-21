<?php

declare(strict_types=1);

use App\Enums\ProjectLevel;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

it('creates a project and makes the caller an admin member', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/projects', [
            'key' => 'SHOP',
            'name' => 'Shop',
            'color' => '#d85a30',
        ])
        ->assertCreated()
        ->assertJson(['key' => 'SHOP', 'name' => 'Shop', 'color' => '#d85a30']);

    $project = Project::query()->where('key', 'SHOP')->firstOrFail();
    expect($project->members()->whereKey($user->id)->exists())->toBeTrue();
});

it('rejects a duplicate or malformed key', function () {
    $user = User::factory()->create();
    Project::factory()->create(['key' => 'SHOP']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/projects', ['key' => 'SHOP', 'name' => 'Dupe'])
        ->assertUnprocessable();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/projects', ['key' => 'lower', 'name' => 'Bad key'])
        ->assertUnprocessable();
});

it('updates a project for an admin member', function () {
    $project = Project::factory()->create(['key' => 'SHOP', 'name' => 'Shop']);
    $user = member($project, ProjectLevel::Admin);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/projects/SHOP', ['key' => 'SHOP', 'name' => 'Shop renamed'])
        ->assertOk()
        ->assertJson(['key' => 'SHOP', 'name' => 'Shop renamed']);

    expect($project->refresh()->name)->toBe('Shop renamed');
});

it('forbids updating a project the user is not an admin of', function () {
    $project = Project::factory()->create(['key' => 'SHOP', 'name' => 'Shop']);
    $user = member($project, ProjectLevel::Write);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/projects/SHOP', ['key' => 'SHOP', 'name' => 'Nope'])
        ->assertForbidden();
});

it('prohibits changing the key once the project has issues', function () {
    $project = Project::factory()->create(['key' => 'SHOP', 'name' => 'Shop']);
    Issue::factory()->for($project)->create();
    $user = member($project, ProjectLevel::Admin);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/projects/SHOP', ['name' => 'Shop', 'key' => 'NEW'])
        ->assertUnprocessable();
});

it('requires authentication to write projects', function () {
    $this->postJson('/api/projects', ['key' => 'SHOP', 'name' => 'Shop'])->assertUnauthorized();
});

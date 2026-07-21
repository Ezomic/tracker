<?php

declare(strict_types=1);

use App\Enums\ProjectLevel;
use App\Models\Project;

it('archives a project and hides it from the listing', function () {
    $project = Project::factory()->create(['key' => 'SHOP']);
    $other = Project::factory()->create(['key' => 'BILLR']);
    $user = member([$project, $other], ProjectLevel::Admin);

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/projects/SHOP')
        ->assertOk()
        ->assertJson(['key' => 'SHOP'])
        ->assertJsonPath('archivedAt', fn ($v) => $v !== null);

    expect($project->refresh()->isArchived())->toBeTrue();

    $keys = collect($this->actingAs($user, 'sanctum')->getJson('/api/projects')->json())->pluck('key');
    expect($keys)->toContain('BILLR')->not->toContain('SHOP');
});

it('restores an archived project', function () {
    $project = Project::factory()->create(['key' => 'SHOP', 'archived_at' => now()]);
    $user = member($project, ProjectLevel::Admin);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/projects/SHOP/restore')
        ->assertOk()
        ->assertJson(['key' => 'SHOP', 'archivedAt' => null]);

    expect($project->refresh()->isArchived())->toBeFalse();

    $keys = collect($this->actingAs($user, 'sanctum')->getJson('/api/projects')->json())->pluck('key');
    expect($keys)->toContain('SHOP');
});

it('forbids a non-admin from archiving', function () {
    $project = Project::factory()->create(['key' => 'SHOP']);
    $user = member($project, ProjectLevel::Write);

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/projects/SHOP')
        ->assertForbidden();
});

it('requires authentication', function () {
    Project::factory()->create(['key' => 'SHOP']);

    $this->deleteJson('/api/projects/SHOP')->assertUnauthorized();
});

<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\Project;

function activeFlag(Project $project): mixed
{
    $user = member($project);

    $flag = null;

    test()->actingAs($user)->get('/dashboard')
        ->assertInertia(function ($page) use (&$flag) {
            $flag = $page->toArray()['props']['sidebarCategories']['uncategorized'][0]['active'];
        });

    return $flag;
}

it('marks a project with an issue updated in the last 7 days as active', function () {
    $project = Project::factory()->create();
    Issue::factory()->for($project)->create(['updated_at' => now()->subDays(6)]);

    expect(activeFlag($project))->toBeTrue();
});

it('marks a project whose issues are all older than 7 days as inactive', function () {
    $project = Project::factory()->create();
    Issue::factory()->for($project)->create(['updated_at' => now()->subDays(8)]);

    expect(activeFlag($project))->toBeFalse();
});

it('does not count a recent update on an archived issue', function () {
    $project = Project::factory()->create();
    Issue::factory()->for($project)->create(['updated_at' => now()->subDay(), 'archived_at' => now()->subDay()]);

    expect(activeFlag($project))->toBeFalse();
});

it('marks a brand new project with no issues as inactive', function () {
    expect(activeFlag(Project::factory()->create()))->toBeFalse();
});

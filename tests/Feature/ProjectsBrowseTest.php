<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;

it('lists every project on the browse page with favorite state', function () {
    Project::factory()->create(['key' => 'THI', 'is_favorite' => true]);
    Project::factory()->create(['key' => 'CMS', 'is_favorite' => false]);

    $this->actingAs(User::factory()->create())
        ->get('/projects')
        ->assertInertia(fn ($page) => $page
            ->component('projects/Index')
            ->has('projects', 2)
            ->where('projects.0.key', 'THI')
            ->where('projects.0.isFavorite', true)
            ->where('projects.1.key', 'CMS')
            ->where('projects.1.isFavorite', false)
        );
});

it('toggles a project favorite', function () {
    $project = Project::factory()->create(['key' => 'THI', 'is_favorite' => true]);

    $this->actingAs(User::factory()->create())
        ->patch('/projects/THI/favorite')
        ->assertRedirect();

    expect($project->fresh()->is_favorite)->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->patch('/projects/THI/favorite');

    expect($project->fresh()->is_favorite)->toBeTrue();
});

it('only shares favorited projects to the sidebar', function () {
    Project::factory()->create(['key' => 'THI', 'is_favorite' => true]);
    Project::factory()->create(['key' => 'CMS', 'is_favorite' => false]);

    $this->actingAs(User::factory()->create())
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->has('sidebarProjects', 1)
            ->where('sidebarProjects.0.key', 'THI')
        );
});

it('stores an archive duration and never (null) when creating a project', function () {
    $this->actingAs(User::factory()->create())
        ->post('/settings/projects', [
            'key' => 'SHOP',
            'name' => 'Shop',
            'archive_after_days' => 14,
        ])
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->where('key', 'SHOP')->first()->archive_after_days)->toBe(14);

    $this->actingAs(User::factory()->create())
        ->post('/settings/projects', [
            'key' => 'ZERO',
            'name' => 'Zero',
            'archive_after_days' => null,
        ])
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->where('key', 'ZERO')->first()->archive_after_days)->toBeNull();
});

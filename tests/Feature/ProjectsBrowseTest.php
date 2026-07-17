<?php

declare(strict_types=1);

use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\User;

it('lists the projects the user belongs to with favorite state', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    $cms = Project::factory()->create(['key' => 'CMS']);
    $thi->members()->attach($user->id, ['level' => ProjectLevel::Admin->value, 'is_favorite' => true]);
    $cms->members()->attach($user->id, ['level' => ProjectLevel::Admin->value, 'is_favorite' => false]);

    $this->actingAs($user)
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

it('does not list projects the user is not a member of', function () {
    $user = User::factory()->create();
    Project::factory()->create(['key' => 'THI']);

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(fn ($page) => $page->has('projects', 0));
});

it('toggles a project favorite for the current user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    $project->members()->attach($user->id, ['level' => ProjectLevel::Admin->value, 'is_favorite' => true]);

    $favorite = fn () => (bool) $user->projects()->find($project->id)->getAttribute('pivot')->getAttribute('is_favorite');

    $this->actingAs($user)->patch('/projects/THI/favorite')->assertRedirect();
    expect($favorite())->toBeFalse();

    $this->actingAs($user)->patch('/projects/THI/favorite');
    expect($favorite())->toBeTrue();
});

it('only shares favorited projects to the sidebar', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    $cms = Project::factory()->create(['key' => 'CMS']);
    $thi->members()->attach($user->id, ['level' => ProjectLevel::Admin->value, 'is_favorite' => true]);
    $cms->members()->attach($user->id, ['level' => ProjectLevel::Admin->value, 'is_favorite' => false]);

    $this->actingAs($user)
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->has('sidebarProjects', 1)
            ->where('sidebarProjects.0.key', 'THI')
        );
});

it('stores an archive duration and never (null) when creating a project', function () {
    $this->actingAs(User::factory()->create())
        ->post('/projects', [
            'key' => 'SHOP',
            'name' => 'Shop',
            'archive_after_days' => 14,
        ])
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->where('key', 'SHOP')->first()->archive_after_days)->toBe(14);

    $this->actingAs(User::factory()->create())
        ->post('/projects', [
            'key' => 'ZERO',
            'name' => 'Zero',
            'archive_after_days' => null,
        ])
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->where('key', 'ZERO')->first()->archive_after_days)->toBeNull();
});

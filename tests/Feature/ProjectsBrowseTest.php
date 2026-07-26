<?php

declare(strict_types=1);

use App\Enums\ProjectLevel;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;

it('lists the projects the user belongs to, ordered by key', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    $cms = Project::factory()->create(['key' => 'CMS']);
    $thi->members()->attach($user->id, ['level' => ProjectLevel::Admin->value]);
    $cms->members()->attach($user->id, ['level' => ProjectLevel::Admin->value]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(fn ($page) => $page
            ->component('projects/Index')
            ->has('projects', 2)
            ->where('projects.0.key', 'CMS')
            ->where('projects.1.key', 'THI')
        );
});

it('does not list projects the user is not a member of', function () {
    $user = User::factory()->create();
    Project::factory()->create(['key' => 'THI']);

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(fn ($page) => $page->has('projects', 0));
});

it('shares the user projects to the sidebar grouped by category', function () {
    [$organization, $user] = organizationWith();
    $category = Category::factory()->for($organization)->create(['name' => 'Clients']);

    projectInOrganization($organization, $user, ['key' => 'THI', 'category_id' => $category->id]);
    projectInOrganization($organization, $user, ['key' => 'CMS']);

    $this->actingAs($user)
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->has('sidebarCategories.tree', 1)
            ->where('sidebarCategories.tree.0.name', 'Clients')
            ->has('sidebarCategories.tree.0.projects', 1)
            ->where('sidebarCategories.tree.0.projects.0.key', 'THI')
            ->has('sidebarCategories.uncategorized', 1)
            ->where('sidebarCategories.uncategorized.0.key', 'CMS')
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

<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;

it('stores github_repo and production_url when creating a project', function () {
    $this->actingAs(User::factory()->create())
        ->post('/settings/projects', [
            'key' => 'SHOP',
            'name' => 'Shop',
            'github_repo' => 'Ezomic/shop',
            'production_url' => 'https://shop.example.com',
        ])
        ->assertRedirect(route('projects.index'));

    $project = Project::query()->where('key', 'SHOP')->first();
    expect($project->github_repo)->toBe('Ezomic/shop')
        ->and($project->production_url)->toBe('https://shop.example.com');
});

it('updates github_repo and production_url', function () {
    $project = Project::factory()->create(['key' => 'SHOP']);

    $this->actingAs(User::factory()->create())
        ->patch("/settings/projects/{$project->id}", [
            'key' => 'SHOP',
            'name' => 'Shop',
            'github_repo' => 'Ezomic/shop',
            'production_url' => 'https://shop.example.com',
        ])
        ->assertRedirect(route('projects.index'));

    $project->refresh();
    expect($project->github_repo)->toBe('Ezomic/shop')
        ->and($project->production_url)->toBe('https://shop.example.com');
});

it('rejects an invalid production_url', function () {
    $this->actingAs(User::factory()->create())
        ->post('/settings/projects', [
            'key' => 'SHOP',
            'name' => 'Shop',
            'production_url' => 'not-a-url',
        ])
        ->assertSessionHasErrors('production_url');
});

it('derives docs, readme, and production links from the stored fields', function () {
    $project = Project::factory()->create([
        'key' => 'SHOP',
        'github_repo' => 'Ezomic/shop',
        'production_url' => 'https://shop.example.com/',
    ]);

    expect($project->links())->toBe([
        'docs' => 'https://shop.example.com/docs',
        'readme' => 'https://github.com/Ezomic/shop#readme',
        'production' => 'https://shop.example.com',
    ]);
});

it('returns null links when the fields are empty', function () {
    $project = Project::factory()->create([
        'key' => 'SHOP',
        'github_repo' => null,
        'production_url' => null,
    ]);

    expect($project->links())->toBe([
        'docs' => null,
        'readme' => null,
        'production' => null,
    ]);
});

it('exposes project links on the settings page', function () {
    Project::factory()->create([
        'key' => 'SHOP',
        'github_repo' => 'Ezomic/shop',
        'production_url' => 'https://shop.example.com',
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/settings/projects')
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.links.docs', 'https://shop.example.com/docs')
            ->where('projects.0.links.readme', 'https://github.com/Ezomic/shop#readme')
            ->where('projects.0.links.production', 'https://shop.example.com')
        );
});

it('exposes scoped project links on the board', function () {
    Project::factory()->create([
        'key' => 'SHOP',
        'github_repo' => 'Ezomic/shop',
        'production_url' => 'https://shop.example.com',
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/SHOP/board')
        ->assertInertia(fn ($page) => $page
            ->component('issues/Board')
            ->where('project.links.production', 'https://shop.example.com')
        );
});

it('exposes project links in the tickets teams list', function () {
    Project::factory()->create([
        'key' => 'SHOP',
        'github_repo' => 'Ezomic/shop',
        'production_url' => 'https://shop.example.com',
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->where('teams.0.links.readme', 'https://github.com/Ezomic/shop#readme')
        );
});

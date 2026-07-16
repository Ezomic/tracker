<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;

it('stores github_repos and production_url when creating a project', function () {
    $this->actingAs(User::factory()->create())
        ->post('/projects', [
            'key' => 'SHOP',
            'name' => 'Shop',
            'github_repos' => ['Ezomic/shop', 'Ezomic/shop-api'],
            'production_url' => 'https://shop.example.com',
        ])
        ->assertRedirect(route('projects.index'));

    $project = Project::query()->where('key', 'SHOP')->first();
    expect($project->github_repos)->toBe(['Ezomic/shop', 'Ezomic/shop-api'])
        ->and($project->production_url)->toBe('https://shop.example.com');
});

it('drops empty repo rows when saving', function () {
    $this->actingAs(User::factory()->create())
        ->post('/projects', [
            'key' => 'SHOP',
            'name' => 'Shop',
            'github_repos' => ['Ezomic/shop', '', '  '],
        ])
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->where('key', 'SHOP')->first()->github_repos)
        ->toBe(['Ezomic/shop']);
});

it('updates github_repos and production_url', function () {
    $project = Project::factory()->create(['key' => 'SHOP']);

    $this->actingAs(User::factory()->create())
        ->patch("/projects/{$project->id}", [
            'key' => 'SHOP',
            'name' => 'Shop',
            'github_repos' => ['Ezomic/shop'],
            'production_url' => 'https://shop.example.com',
        ])
        ->assertRedirect(route('projects.index'));

    $project->refresh();
    expect($project->github_repos)->toBe(['Ezomic/shop'])
        ->and($project->production_url)->toBe('https://shop.example.com');
});

it('rejects an invalid production_url', function () {
    $this->actingAs(User::factory()->create())
        ->post('/projects', [
            'key' => 'SHOP',
            'name' => 'Shop',
            'production_url' => 'not-a-url',
        ])
        ->assertSessionHasErrors('production_url');
});

it('derives docs, production, and per-repo links from the stored fields', function () {
    $project = Project::factory()->create([
        'key' => 'SHOP',
        'github_repos' => ['Ezomic/shop', 'Ezomic/shop-api'],
        'production_url' => 'https://shop.example.com/',
    ]);

    expect($project->links())->toBe([
        'docs' => 'https://shop.example.com/docs',
        'production' => 'https://shop.example.com',
        'repos' => [
            ['name' => 'Ezomic/shop', 'url' => 'https://github.com/Ezomic/shop'],
            ['name' => 'Ezomic/shop-api', 'url' => 'https://github.com/Ezomic/shop-api'],
        ],
    ]);
});

it('returns empty links when the fields are empty', function () {
    $project = Project::factory()->create([
        'key' => 'SHOP',
        'github_repos' => null,
        'production_url' => null,
    ]);

    expect($project->links())->toBe([
        'docs' => null,
        'production' => null,
        'repos' => [],
    ]);
});

it('exposes project links on the settings page', function () {
    Project::factory()->create([
        'key' => 'SHOP',
        'github_repos' => ['Ezomic/shop'],
        'production_url' => 'https://shop.example.com',
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/projects')
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.links.docs', 'https://shop.example.com/docs')
            ->where('projects.0.links.production', 'https://shop.example.com')
            ->where('projects.0.links.repos.0.url', 'https://github.com/Ezomic/shop')
        );
});

it('exposes scoped project links on the board', function () {
    Project::factory()->create([
        'key' => 'SHOP',
        'github_repos' => ['Ezomic/shop'],
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
        'github_repos' => ['Ezomic/shop'],
        'production_url' => 'https://shop.example.com',
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.links.repos.0.name', 'Ezomic/shop')
        );
});

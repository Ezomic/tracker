<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Category;
use App\Models\Project;

it('creates a project in a category from the same organization', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    session(['current_organization_id' => $org->id]);
    $category = Category::factory()->create(['organization_id' => $org->id]);

    $this->actingAs($owner)->post('/projects', [
        'key' => 'THI',
        'name' => 'Thijssen',
        'category_id' => $category->id,
    ])->assertRedirect();

    expect(Project::query()->where('key', 'THI')->value('category_id'))->toBe($category->id);
});

it('rejects a category from another organization', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    session(['current_organization_id' => $org->id]);
    $foreign = Category::factory()->create();

    $this->actingAs($owner)->post('/projects', [
        'key' => 'THI',
        'name' => 'Thijssen',
        'category_id' => $foreign->id,
    ])->assertSessionHasErrors('category_id');

    expect(Project::query()->where('key', 'THI')->exists())->toBeFalse();
});

it('updates a project to sit in a category', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    session(['current_organization_id' => $org->id]);
    $project = Project::factory()->create(['organization_id' => $org->id, 'key' => 'THI']);
    joinProjects($owner, $project, ProjectLevel::Admin);
    $category = Category::factory()->create(['organization_id' => $org->id]);

    $this->actingAs($owner)->patch("/projects/{$project->id}", [
        'key' => 'THI',
        'name' => $project->name,
        'category_id' => $category->id,
    ])->assertRedirect();

    expect($project->fresh()->category_id)->toBe($category->id);
});

it('exposes the category tree and each project\'s category on the index', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    session(['current_organization_id' => $org->id]);
    $parent = Category::factory()->create(['organization_id' => $org->id, 'name' => 'Parent']);
    $child = Category::factory()->create(['organization_id' => $org->id, 'parent_id' => $parent->id, 'name' => 'Child']);
    $project = Project::factory()->create(['organization_id' => $org->id, 'category_id' => $child->id]);
    joinProjects($owner, $project);

    $this->actingAs($owner)->get('/projects')
        ->assertInertia(fn ($page) => $page
            ->where('categories', fn ($categories) => collect($categories)->pluck('name')->all() === ['Parent', 'Child']
                && collect($categories)->firstWhere('name', 'Child')['depth'] === 1)
            ->where('projects', fn ($projects) => collect($projects)->firstWhere('id', $project->id)['categoryId'] === $child->id)
        );
});

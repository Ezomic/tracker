<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Category;

it('lists the organization categories', function () {
    [$org, $owner] = organizationWith();
    Category::factory()->for($org)->create(['name' => 'Client work']);

    $this->actingAs($owner, 'sanctum')->getJson('/api/categories')
        ->assertOk()
        ->assertJson([['name' => 'Client work', 'parentId' => null, 'depth' => 0]]);
});

it('creates a category', function () {
    [$org, $owner] = organizationWith();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/categories', ['name' => 'Internal'])
        ->assertCreated()
        ->assertJson(['name' => 'Internal', 'parentId' => null]);

    expect($org->categories()->where('name', 'Internal')->exists())->toBeTrue();
});

it('creates a nested category', function () {
    [$org, $owner] = organizationWith();
    $parent = Category::factory()->for($org)->create(['name' => 'Parent']);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/categories', ['name' => 'Child', 'parent_id' => $parent->id])
        ->assertCreated()
        ->assertJson(['name' => 'Child', 'parentId' => $parent->id]);
});

it('updates and deletes a category', function () {
    [$org, $owner] = organizationWith();
    $category = Category::factory()->for($org)->create(['name' => 'Old']);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/categories/{$category->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJson(['name' => 'New']);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/categories/{$category->id}")
        ->assertNoContent();

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('forbids a non-managing member from writing categories', function () {
    [, $member] = organizationWith(OrganizationRole::Member);

    $this->actingAs($member, 'sanctum')
        ->postJson('/api/categories', ['name' => 'Nope'])
        ->assertForbidden();
});

it('requires authentication', function () {
    $this->postJson('/api/categories', ['name' => 'x'])->assertUnauthorized();
});

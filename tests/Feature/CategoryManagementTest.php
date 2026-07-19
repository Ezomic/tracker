<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;

function actingInCurrentOrg(User $user, int $organizationId): User
{
    // The controllers resolve the "current" org from the session; seed it.
    session(['current_organization_id' => $organizationId]);

    return $user;
}

it('lets a manager create a category and a nested subcategory', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    actingInCurrentOrg($owner, $org->id);

    $this->actingAs($owner)->post('/settings/categories', ['name' => 'Clients'])
        ->assertRedirect();

    $parent = Category::query()->where('name', 'Clients')->firstOrFail();
    expect($parent->organization_id)->toBe($org->id)
        ->and($parent->parent_id)->toBeNull();

    $this->actingAs($owner)->post('/settings/categories', [
        'name' => 'Acme',
        'parent_id' => $parent->id,
    ])->assertRedirect();

    $child = Category::query()->where('name', 'Acme')->firstOrFail();
    expect($child->parent_id)->toBe($parent->id);
});

it('forbids a plain member from managing categories and hides them from guests', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    $category = Category::factory()->create(['organization_id' => $org->id]);

    $member = User::factory()->create();
    $org->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);
    actingInCurrentOrg($member, $org->id);

    $this->actingAs($member)->post('/settings/categories', ['name' => 'Nope'])
        ->assertForbidden();
    $this->actingAs($member)->patch("/settings/categories/{$category->id}", ['name' => 'X'])
        ->assertForbidden();

    $guest = User::factory()->create();
    $org->members()->attach($guest->id, ['role' => OrganizationRole::Guest->value]);
    actingInCurrentOrg($guest, $org->id);

    $this->actingAs($guest)->get('/settings/categories')->assertForbidden();
});

it('renames and reparents a category', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    actingInCurrentOrg($owner, $org->id);
    $a = Category::factory()->create(['organization_id' => $org->id, 'name' => 'A']);
    $b = Category::factory()->create(['organization_id' => $org->id, 'name' => 'B']);

    $this->actingAs($owner)->patch("/settings/categories/{$b->id}", [
        'name' => 'B renamed',
        'parent_id' => $a->id,
    ])->assertRedirect();

    $b->refresh();
    expect($b->name)->toBe('B renamed')->and($b->parent_id)->toBe($a->id);
});

it('rejects a reparent that would create a cycle', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    actingInCurrentOrg($owner, $org->id);
    $parent = Category::factory()->create(['organization_id' => $org->id]);
    $child = Category::factory()->create(['organization_id' => $org->id, 'parent_id' => $parent->id]);

    // Moving the parent under its own child, or under itself, is invalid.
    $this->actingAs($owner)->patch("/settings/categories/{$parent->id}", [
        'name' => $parent->name,
        'parent_id' => $child->id,
    ])->assertSessionHasErrors('parent_id');

    $this->actingAs($owner)->patch("/settings/categories/{$parent->id}", [
        'name' => $parent->name,
        'parent_id' => $parent->id,
    ])->assertSessionHasErrors('parent_id');
});

it('deletes a category with its subtree and un-categorizes affected projects', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    actingInCurrentOrg($owner, $org->id);
    $parent = Category::factory()->create(['organization_id' => $org->id]);
    $child = Category::factory()->create(['organization_id' => $org->id, 'parent_id' => $parent->id]);
    $project = Project::factory()->create(['organization_id' => $org->id, 'category_id' => $child->id]);

    $this->actingAs($owner)->delete("/settings/categories/{$parent->id}")->assertRedirect();

    expect(Category::query()->whereIn('id', [$parent->id, $child->id])->count())->toBe(0)
        ->and($project->fresh()->category_id)->toBeNull();
});

it('does not accept another organization\'s category as a parent', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    actingInCurrentOrg($owner, $org->id);
    $foreign = Category::factory()->create(); // different org

    $this->actingAs($owner)->post('/settings/categories', [
        'name' => 'Child',
        'parent_id' => $foreign->id,
    ])->assertSessionHasErrors('parent_id');
});

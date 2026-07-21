<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Label;

it('creates a label in the current organization', function () {
    [$org, $owner] = organizationWith();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/labels', ['name' => 'bug', 'color' => 'red'])
        ->assertCreated()
        ->assertJson(['name' => 'bug', 'color' => 'red']);

    expect($org->labels()->where('name', 'bug')->exists())->toBeTrue();
});

it('rejects a duplicate name or invalid color', function () {
    [$org, $owner] = organizationWith();
    Label::factory()->for($org)->create(['name' => 'bug']);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/labels', ['name' => 'bug', 'color' => 'red'])
        ->assertUnprocessable();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/labels', ['name' => 'new', 'color' => 'chartreuse'])
        ->assertUnprocessable();
});

it('updates a label', function () {
    [$org, $owner] = organizationWith();
    $label = Label::factory()->for($org)->create(['name' => 'bug', 'color' => 'red']);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/labels/{$label->id}", ['name' => 'defect', 'color' => 'yellow'])
        ->assertOk()
        ->assertJson(['name' => 'defect', 'color' => 'yellow']);
});

it('deletes a label', function () {
    [$org, $owner] = organizationWith();
    $label = Label::factory()->for($org)->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/labels/{$label->id}")
        ->assertNoContent();

    expect(Label::query()->whereKey($label->id)->exists())->toBeFalse();
});

it('forbids a non-managing member from writing labels', function () {
    [, $member] = organizationWith(OrganizationRole::Member);

    $this->actingAs($member, 'sanctum')
        ->postJson('/api/labels', ['name' => 'bug', 'color' => 'red'])
        ->assertForbidden();
});

it('requires authentication', function () {
    $this->postJson('/api/labels', ['name' => 'bug', 'color' => 'red'])->assertUnauthorized();
});

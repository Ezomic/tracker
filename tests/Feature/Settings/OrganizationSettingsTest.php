<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('starts every organization with drafting off', function () {
    $organization = Organization::factory()->create();

    expect($organization->refresh()->ai_drafting_enabled)->toBeFalse();
});

it('shows the organization settings to owners and admins', function (OrganizationRole $role) {
    [$organization, $user] = organizationWith($role);
    $organization->forceFill(['ai_drafting_enabled' => true])->save();

    $this->actingAs($user)->get('/settings/organization')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Organization')
            ->where('organization', [
                'name' => $organization->name,
                'aiDraftingEnabled' => true,
            ]));
})->with([OrganizationRole::Owner, OrganizationRole::Admin]);

it('hides the organization settings from members and guests', function (OrganizationRole $role) {
    [, $user] = organizationWith($role);

    $this->actingAs($user)->get('/settings/organization')->assertForbidden();
})->with([OrganizationRole::Member, OrganizationRole::Guest]);

it('lets owners and admins switch drafting on and off', function (OrganizationRole $role) {
    [$organization, $user] = organizationWith($role);

    $this->actingAs($user)
        ->patch('/settings/organization', ['ai_drafting_enabled' => true])
        ->assertRedirect('/settings/organization');

    expect($organization->refresh()->ai_drafting_enabled)->toBeTrue();

    $this->actingAs($user)
        ->patch('/settings/organization', ['ai_drafting_enabled' => false])
        ->assertRedirect('/settings/organization');

    expect($organization->refresh()->ai_drafting_enabled)->toBeFalse();
})->with([OrganizationRole::Owner, OrganizationRole::Admin]);

it('forbids members and guests from switching drafting on', function (OrganizationRole $role) {
    [$organization, $user] = organizationWith($role);

    $this->actingAs($user)
        ->patch('/settings/organization', ['ai_drafting_enabled' => true])
        ->assertForbidden();

    expect($organization->refresh()->ai_drafting_enabled)->toBeFalse();
})->with([OrganizationRole::Member, OrganizationRole::Guest]);

it('only changes the organization the user has open', function () {
    [$mine, $user] = organizationWith();
    [$theirs] = organizationWith();
    $alsoMine = Organization::factory()->create();
    $alsoMine->members()->attach($user->id, ['role' => OrganizationRole::Owner->value]);

    $this->actingAs($user)
        ->withSession(['current_organization_id' => $mine->id])
        ->patch('/settings/organization', ['ai_drafting_enabled' => true])
        ->assertRedirect();

    expect($mine->refresh()->ai_drafting_enabled)->toBeTrue()
        ->and($theirs->refresh()->ai_drafting_enabled)->toBeFalse()
        ->and($alsoMine->refresh()->ai_drafting_enabled)->toBeFalse();
});

it('requires a yes or no', function (mixed $value) {
    [$organization, $user] = organizationWith();

    $this->actingAs($user)
        ->patch('/settings/organization', array_filter(['ai_drafting_enabled' => $value], fn ($each) => $each !== null))
        ->assertSessionHasErrors('ai_drafting_enabled');

    expect($organization->refresh()->ai_drafting_enabled)->toBeFalse();
})->with([
    'missing' => [null],
    'not a boolean' => ['yes please'],
    'a list' => [[true]],
]);

it('accepts the ways a form sends a boolean', function (mixed $value, bool $expected) {
    [$organization, $user] = organizationWith();
    $organization->forceFill(['ai_drafting_enabled' => ! $expected])->save();

    $this->actingAs($user)
        ->patch('/settings/organization', ['ai_drafting_enabled' => $value])
        ->assertSessionHasNoErrors();

    expect($organization->refresh()->ai_drafting_enabled)->toBe($expected);
})->with([
    'string one' => ['1', true],
    'string zero' => ['0', false],
    'integer one' => [1, true],
]);

it('ignores anything but the drafting switch', function () {
    [$organization, $user] = organizationWith();
    $name = $organization->name;

    $this->actingAs($user)
        ->patch('/settings/organization', ['ai_drafting_enabled' => true, 'name' => 'Hijacked', 'slug' => 'hijacked']);

    expect($organization->refresh()->name)->toBe($name);
});

it('requires a signed-in user', function () {
    $this->get('/settings/organization')->assertRedirect('/login');
    $this->patch('/settings/organization', ['ai_drafting_enabled' => true])->assertRedirect('/login');

    expect(User::count())->toBe(0);
});

<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    [$this->organization, $this->owner] = organizationWith(OrganizationRole::Owner);
    $this->project = projectInOrganization($this->organization, $this->owner, ['key' => 'THI']);
});

it('creates a service account granted the chosen projects and shows the token once', function () {
    $response = $this->actingAs($this->owner)->post('/settings/service-accounts', [
        'name' => 'snag ingest',
        'projects' => ['THI'],
    ]);

    $response->assertRedirect('/settings/service-accounts');
    $response->assertSessionHas('createdToken');

    $account = User::query()->where('is_service', true)->firstOrFail();
    expect($account->name)->toBe('snag ingest')
        ->and($account->projects->pluck('key')->all())->toBe(['THI'])
        ->and($account->tokens()->count())->toBe(1);
});

it('requires at least one project', function () {
    $this->actingAs($this->owner)
        ->post('/settings/service-accounts', ['name' => 'no projects', 'projects' => []])
        ->assertSessionHasErrors('projects');

    expect(User::query()->where('is_service', true)->count())->toBe(0);
});

it('refuses a project key the caller cannot see', function () {
    [$otherOrg, $stranger] = organizationWith(OrganizationRole::Owner);
    projectInOrganization($otherOrg, $stranger, ['key' => 'OTHER']);

    $this->actingAs($this->owner)
        ->post('/settings/service-accounts', ['name' => 'reach', 'projects' => ['OTHER']])
        ->assertForbidden();

    expect(User::query()->where('is_service', true)->count())->toBe(0);
});

it('is closed to members who cannot manage the organization', function () {
    $member = User::factory()->create();
    $this->organization->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($member)->get('/settings/service-accounts')->assertForbidden();
    $this->actingAs($member)
        ->post('/settings/service-accounts', ['name' => 'nope', 'projects' => ['THI']])
        ->assertForbidden();
});

it('lists the organization service accounts without leaking another org', function () {
    $this->actingAs($this->owner)->post('/settings/service-accounts', [
        'name' => 'mine',
        'projects' => ['THI'],
    ]);

    [$otherOrg, $stranger] = organizationWith(OrganizationRole::Owner);
    projectInOrganization($otherOrg, $stranger, ['key' => 'OTHER']);
    $this->actingAs($stranger)->post('/settings/service-accounts', [
        'name' => 'theirs',
        'projects' => ['OTHER'],
    ]);

    $this->actingAs($this->owner)
        ->get('/settings/service-accounts')
        ->assertInertia(fn ($page) => $page
            ->component('settings/ServiceAccounts')
            ->has('accounts', 1)
            ->where('accounts.0.name', 'mine'));
});

it('revokes a service account and its tokens', function () {
    $this->actingAs($this->owner)->post('/settings/service-accounts', [
        'name' => 'snag ingest',
        'projects' => ['THI'],
    ]);
    $account = User::query()->where('is_service', true)->firstOrFail();

    $this->actingAs($this->owner)
        ->delete("/settings/service-accounts/{$account->id}")
        ->assertRedirect('/settings/service-accounts');

    expect(User::query()->whereKey($account->id)->exists())->toBeFalse()
        ->and(PersonalAccessToken::query()->count())->toBe(0);
});

it('will not revoke a human user through the service account route', function () {
    $this->actingAs($this->owner)
        ->delete("/settings/service-accounts/{$this->owner->id}")
        ->assertNotFound();

    expect(User::query()->whereKey($this->owner->id)->exists())->toBeTrue();
});

it('will not revoke a service account belonging to another organization', function () {
    [$otherOrg, $stranger] = organizationWith(OrganizationRole::Owner);
    projectInOrganization($otherOrg, $stranger, ['key' => 'OTHER']);
    $this->actingAs($stranger)->post('/settings/service-accounts', [
        'name' => 'theirs',
        'projects' => ['OTHER'],
    ]);
    $theirs = User::query()->where('is_service', true)->firstOrFail();

    $this->actingAs($this->owner)
        ->delete("/settings/service-accounts/{$theirs->id}")
        ->assertNotFound();

    expect(User::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Project;
use App\Models\User;

it('renders the teams settings page with issue counts', function () {
    $team = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software']);
    (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->get('/settings/teams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Teams')
            ->where('teams.0.key', 'THI')
            ->where('teams.0.issuesCount', 1)
            ->where('teams.0.keyLocked', true)
        );
});

it('creates a team', function () {
    $this->actingAs(User::factory()->create())
        ->post('/settings/teams', ['key' => 'BILLR', 'name' => 'Billr'])
        ->assertRedirect(route('teams.index'));

    expect(Project::query()->where('key', 'BILLR')->exists())->toBeTrue();
});

it('creates a project with a color', function () {
    $this->actingAs(User::factory()->create())
        ->post('/settings/teams', ['key' => 'SHOP', 'name' => 'Shop', 'color' => '#378add'])
        ->assertRedirect(route('teams.index'));

    expect(Project::query()->where('key', 'SHOP')->first()->color)->toBe('#378add');
});

it('rejects an invalid color', function () {
    $this->actingAs(User::factory()->create())
        ->post('/settings/teams', ['key' => 'SHOP', 'name' => 'Shop', 'color' => 'blue'])
        ->assertSessionHasErrors('color');
});

it('rejects a team key that is not 2-10 uppercase letters', function () {
    $this->actingAs(User::factory()->create())
        ->post('/settings/teams', ['key' => 'thi', 'name' => 'Thijssen Software'])
        ->assertSessionHasErrors('key');
});

it('rejects a duplicate team key', function () {
    Project::factory()->create(['key' => 'THI']);

    $this->actingAs(User::factory()->create())
        ->post('/settings/teams', ['key' => 'THI', 'name' => 'Duplicate'])
        ->assertSessionHasErrors('key');
});

it('allows renaming a team with no issues, including its key', function () {
    $team = Project::factory()->create(['key' => 'OLD', 'name' => 'Old name']);

    $this->actingAs(User::factory()->create())
        ->patch("/settings/teams/{$team->id}", ['key' => 'NEW', 'name' => 'New name'])
        ->assertRedirect(route('teams.index'));

    expect($team->fresh())
        ->key->toBe('NEW')
        ->name->toBe('New name');
});

it('prohibits changing the key once a team has issues', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->patch("/settings/teams/{$team->id}", ['key' => 'CHANGED', 'name' => 'Thijssen Software'])
        ->assertSessionHasErrors('key');

    expect($team->fresh()->key)->toBe('THI');
});

it('still allows renaming a locked team as long as the key is omitted', function () {
    $team = Project::factory()->create(['key' => 'THI', 'name' => 'Old name']);
    (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->patch("/settings/teams/{$team->id}", ['name' => 'New name'])
        ->assertRedirect(route('teams.index'));

    expect($team->fresh())
        ->key->toBe('THI')
        ->name->toBe('New name');
});

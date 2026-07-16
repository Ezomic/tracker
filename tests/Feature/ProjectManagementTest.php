<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Project;
use App\Models\User;

it('renders the projects settings page with issue counts', function () {
    $team = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software']);
    (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Index')
            ->where('projects.0.key', 'THI')
            ->where('projects.0.issuesCount', 1)
            ->where('projects.0.keyLocked', true)
        );
});

it('creates a project', function () {
    $this->actingAs(User::factory()->create())
        ->post('/projects', ['key' => 'BILLR', 'name' => 'Billr'])
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->where('key', 'BILLR')->exists())->toBeTrue();
});

it('creates a project with a color', function () {
    $this->actingAs(User::factory()->create())
        ->post('/projects', ['key' => 'SHOP', 'name' => 'Shop', 'color' => '#378add'])
        ->assertRedirect(route('projects.index'));

    expect(Project::query()->where('key', 'SHOP')->first()->color)->toBe('#378add');
});

it('rejects an invalid color', function () {
    $this->actingAs(User::factory()->create())
        ->post('/projects', ['key' => 'SHOP', 'name' => 'Shop', 'color' => 'blue'])
        ->assertSessionHasErrors('color');
});

it('rejects a project key that is not 2-10 uppercase letters', function () {
    $this->actingAs(User::factory()->create())
        ->post('/projects', ['key' => 'thi', 'name' => 'Thijssen Software'])
        ->assertSessionHasErrors('key');
});

it('rejects a duplicate project key', function () {
    Project::factory()->create(['key' => 'THI']);

    $this->actingAs(User::factory()->create())
        ->post('/projects', ['key' => 'THI', 'name' => 'Duplicate'])
        ->assertSessionHasErrors('key');
});

it('allows renaming a project with no issues, including its key', function () {
    $team = Project::factory()->create(['key' => 'OLD', 'name' => 'Old name']);

    $this->actingAs(User::factory()->create())
        ->patch("/projects/{$team->id}", ['key' => 'NEW', 'name' => 'New name'])
        ->assertRedirect(route('projects.index'));

    expect($team->fresh())
        ->key->toBe('NEW')
        ->name->toBe('New name');
});

it('prohibits changing the key once a project has issues', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->patch("/projects/{$team->id}", ['key' => 'CHANGED', 'name' => 'Thijssen Software'])
        ->assertSessionHasErrors('key');

    expect($team->fresh()->key)->toBe('THI');
});

it('still allows renaming a locked project as long as the key is omitted', function () {
    $team = Project::factory()->create(['key' => 'THI', 'name' => 'Old name']);
    (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->patch("/projects/{$team->id}", ['name' => 'New name'])
        ->assertRedirect(route('projects.index'));

    expect($team->fresh())
        ->key->toBe('THI')
        ->name->toBe('New name');
});

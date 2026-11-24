<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Project;

it('lists notes on the board, most recently updated first', function () {
    [$organization, $user] = organizationWith();
    $project = projectInOrganization($organization, $user, ['key' => 'AAA', 'name' => 'Alpha']);
    Note::factory()->for($project)->create(['title' => 'Older', 'updated_at' => now()->subDays(2)]);
    Note::factory()->for($project)->create(['title' => 'Newest', 'updated_at' => now()]);
    Note::factory()->for($project)->create(['title' => 'Middle', 'updated_at' => now()->subDay()]);

    $this->actingAs($user)->get('/issues/board')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notes', 3)
            ->where('notes.0.title', 'Newest')
            ->where('notes.1.title', 'Middle')
            ->where('notes.2.title', 'Older')
            ->where('notes.0.projectKey', 'AAA')
            ->where('notes.0.projectName', 'Alpha')
            ->missing('notes.0.body'));
});

it('shows only the viewed project\'s notes on a project board', function () {
    [$organization, $user] = organizationWith();
    $first = projectInOrganization($organization, $user, ['key' => 'AAA']);
    $second = projectInOrganization($organization, $user, ['key' => 'BBB']);
    Note::factory()->for($first)->create(['title' => 'In first']);
    Note::factory()->for($second)->create(['title' => 'In second']);

    $this->actingAs($user)->get('/AAA/board')
        ->assertInertia(fn ($page) => $page->has('notes', 1)->where('notes.0.title', 'In first'));

    $this->actingAs($user)->get('/issues/board')
        ->assertInertia(fn ($page) => $page->has('notes', 2));
});

it('never lists notes from projects the user cannot see', function () {
    [$organization, $user] = organizationWith(OrganizationRole::Member);
    $visible = projectInOrganization($organization, $user);
    $sameOrgHidden = Project::factory()->create(['organization_id' => $organization->id]);
    $otherOrg = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $otherOrg->members()->attach($user->id, ['level' => ProjectLevel::Read->value]);
    $stranger = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    Note::factory()->for($visible)->create(['title' => 'Visible']);
    Note::factory()->for($sameOrgHidden)->create(['title' => 'Hidden in same org']);
    Note::factory()->for($otherOrg)->create(['title' => 'Other org']);
    Note::factory()->for($stranger)->create(['title' => 'Stranger']);

    $this->actingAs($user)->get('/issues/board')
        ->assertInertia(fn ($page) => $page->has('notes', 1)->where('notes.0.title', 'Visible'));
});

it('leaves out deleted notes and notes of archived projects on the combined board', function () {
    [$organization, $user] = organizationWith();
    $project = projectInOrganization($organization, $user);
    $archived = projectInOrganization($organization, $user, ['archived_at' => now()]);
    Note::factory()->for($project)->create(['title' => 'Kept']);
    Note::factory()->for($project)->create(['title' => 'Deleted'])->delete();
    Note::factory()->for($archived)->create(['title' => 'In archived project']);

    $this->actingAs($user)->get('/issues/board')
        ->assertInertia(fn ($page) => $page->has('notes', 1)->where('notes.0.title', 'Kept'));
});

it('caps the board list at 50 notes', function () {
    [$organization, $user] = organizationWith();
    $project = projectInOrganization($organization, $user);
    Note::factory()->count(51)->for($project)->create();

    $this->actingAs($user)->get('/issues/board')
        ->assertInertia(fn ($page) => $page->has('notes', 50));
});

it('still refuses a project board the user cannot see', function () {
    $project = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    Note::factory()->for($project)->create();
    [, $user] = organizationWith();

    $this->actingAs($user)->get("/{$project->key}/board")->assertForbidden();
});

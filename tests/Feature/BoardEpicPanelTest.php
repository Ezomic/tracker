<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;

/**
 * The board's epic panel and the chips on child cards are built from the
 * board payload (TRACK-259), so every epic a card points at must be one the
 * viewer could open themselves, under the same filters as the board.
 */

/**
 * @param  TestResponse<Response>  $response
 * @return Collection<string, array<string, mixed>>
 */
function boardCards(TestResponse $response): Collection
{
    $issues = [];

    $response->assertOk()->assertInertia(function ($page) use (&$issues) {
        $issues = $page->toArray()['props']['issues'];
    });

    return collect($issues)->keyBy('identifier');
}

function epicIn(Project $project, array $attributes = []): Issue
{
    return Issue::factory()->for($project)->create(['title' => 'Epic', ...$attributes]);
}

function childOf(Issue $epic, ?Project $project = null, array $attributes = []): Issue
{
    return Issue::factory()->for($project ?? $epic->project)->create(['parent_id' => $epic->id, ...$attributes]);
}

it('serializes the issue id so the panel can color each epic', function () {
    $project = Project::factory()->create();
    $epic = epicIn($project);
    childOf($epic);

    $cards = boardCards($this->actingAs(member($project))->get('/issues/board'));

    expect($cards[$epic->identifier]['id'])->toBe($epic->id)
        ->and($cards[$epic->identifier]['childrenCount'])->toBe(1);
});

it('points a child card at its epic with the epic id, identifier and title', function () {
    $project = Project::factory()->create();
    $epic = epicIn($project, ['title' => 'Ünïcode 🚀 '.str_repeat('long ', 40)]);
    $child = childOf($epic);

    $cards = boardCards($this->actingAs(member($project))->get("/{$project->key}/board"));

    expect($cards[$child->identifier]['parent'])->toBe([
        'id' => $epic->id,
        'identifier' => $epic->identifier,
        'title' => $epic->title,
    ]);
});

it('keeps an issue without sub-issues as an ordinary card', function () {
    $project = Project::factory()->create();
    $loose = Issue::factory()->for($project)->create();

    $cards = boardCards($this->actingAs(member($project))->get('/issues/board'));

    expect($cards[$loose->identifier]['childrenCount'])->toBe(0)
        ->and($cards[$loose->identifier]['parent'])->toBeNull();
});

it('hides an epic from another organization on child cards', function () {
    [$organization, $user] = organizationWith(OrganizationRole::Member);
    $mine = projectInOrganization($organization, $user);
    [$foreignOrganization] = organizationWith();
    $foreign = Project::factory()->create(['organization_id' => $foreignOrganization->id]);
    $secretEpic = epicIn($foreign, ['title' => 'Secret roadmap']);
    $child = childOf($secretEpic, $mine);

    foreach (['/issues/board', "/{$mine->key}/board"] as $url) {
        $response = $this->actingAs($user)->get($url);
        $cards = boardCards($response);

        expect($cards)->toHaveKey($child->identifier)
            ->and($cards[$child->identifier]['parent'])->toBeNull()
            ->and($cards)->not->toHaveKey($secretEpic->identifier);
        $response->assertDontSee('Secret roadmap');
    }
});

it('hides an epic in a project of the same organization the viewer is not in', function () {
    [$organization, $user] = organizationWith(OrganizationRole::Member);
    $mine = projectInOrganization($organization, $user);
    $other = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = epicIn($other, ['title' => 'Other team epic']);
    $child = childOf($epic, $mine);

    $response = $this->actingAs($user)->get('/issues/board');

    expect(boardCards($response)[$child->identifier]['parent'])->toBeNull();
    $response->assertDontSee('Other team epic');
});

it('hides the epic from a member restricted to their own issues', function () {
    $project = Project::factory()->create();
    $restricted = User::factory()->create();
    joinProjects($restricted, $project, ProjectLevel::Write);
    $project->members()->updateExistingPivot($restricted->id, ['own_issues_only' => true]);
    $epic = epicIn($project, ['title' => 'Not yours']);
    $child = childOf($epic, attributes: ['assignee_id' => $restricted->id]);

    $response = $this->actingAs($restricted)->get('/issues/board');
    $cards = boardCards($response);

    expect($cards->keys()->all())->toBe([$child->identifier])
        ->and($cards[$child->identifier]['parent'])->toBeNull();
    $response->assertDontSee('Not yours');
});

it('only counts the sub-issues the viewer can see', function () {
    $project = Project::factory()->create();
    $restricted = User::factory()->create();
    joinProjects($restricted, $project, ProjectLevel::Write);
    $project->members()->updateExistingPivot($restricted->id, ['own_issues_only' => true]);
    $epic = epicIn($project, ['owner_id' => $restricted->id]);
    childOf($epic);

    $cards = boardCards($this->actingAs($restricted)->get('/issues/board'));

    expect($cards[$epic->identifier]['childrenCount'])->toBe(0);
});

it('shows epics to a guest with a project grant but not to a guest without one', function () {
    [$organization, $granted] = organizationWith(OrganizationRole::Guest);
    $project = projectInOrganization($organization, $granted);
    joinProjects($granted, $project, ProjectLevel::Read);
    $ungranted = User::factory()->create();
    $organization->members()->attach($ungranted->id, ['role' => OrganizationRole::Guest->value]);
    $epic = epicIn($project, ['title' => 'Granted epic']);
    $child = childOf($epic);

    $cards = boardCards($this->actingAs($granted)->get('/issues/board'));
    expect($cards[$child->identifier]['parent']['identifier'])->toBe($epic->identifier)
        ->and($cards[$epic->identifier]['childrenCount'])->toBe(1);

    $this->actingAs($ungranted)->withSession(['current_organization_id' => $organization->id]);
    $response = $this->get('/issues/board');
    expect(boardCards($response))->toBeEmpty();
    $response->assertDontSee('Granted epic');
});

it('drops an archived epic from child cards unless archived issues are shown', function () {
    $project = Project::factory()->create();
    $epic = epicIn($project, ['archived_at' => now()]);
    $child = childOf($epic);
    $user = member($project);

    $hidden = boardCards($this->actingAs($user)->get('/issues/board'));
    expect($hidden)->not->toHaveKey($epic->identifier)
        ->and($hidden[$child->identifier]['parent'])->toBeNull();

    $shown = boardCards($this->actingAs($user)->get('/issues/board?archived=1'));
    expect($shown)->toHaveKey($epic->identifier)
        ->and($shown[$child->identifier]['parent']['id'])->toBe($epic->id);
});

it('keeps an epic whose sub-issues are all archived out of the columns', function () {
    $project = Project::factory()->create();
    $epic = epicIn($project);
    $child = childOf($epic, attributes: ['archived_at' => now()]);

    $cards = boardCards($this->actingAs(member($project))->get('/issues/board'));

    expect($cards)->not->toHaveKey($child->identifier)
        ->and($cards[$epic->identifier]['childrenCount'])->toBe(1);
});

it('drops an epic in an archived project from the everything board', function () {
    [$organization, $user] = organizationWith();
    $live = projectInOrganization($organization, $user);
    $shelved = projectInOrganization($organization, $user, ['archived_at' => now()]);
    $epic = epicIn($shelved, ['title' => 'Shelved epic']);
    $child = childOf($epic, $live);

    $cards = boardCards($this->actingAs($user)->get('/issues/board'));

    expect($cards)->not->toHaveKey($epic->identifier)
        ->and($cards[$child->identifier]['parent'])->toBeNull();
});

it('keeps an epic whose only sub-issue sits in an archived project', function () {
    [$organization, $user] = organizationWith();
    $live = projectInOrganization($organization, $user);
    $shelved = projectInOrganization($organization, $user, ['archived_at' => now()]);
    $epic = epicIn($live);
    $child = childOf($epic, $shelved);

    $cards = boardCards($this->actingAs($user)->get('/issues/board'));

    expect($cards)->not->toHaveKey($child->identifier)
        ->and($cards[$epic->identifier]['childrenCount'])->toBe(1);
});

it('points at an epic in another project when viewing one project board', function () {
    [$organization, $user] = organizationWith();
    $viewed = projectInOrganization($organization, $user);
    $elsewhere = projectInOrganization($organization, $user);
    $epic = epicIn($elsewhere);
    $child = childOf($epic, $viewed);

    $cards = boardCards($this->actingAs($user)->get("/{$viewed->key}/board"));

    expect($cards)->not->toHaveKey($epic->identifier)
        ->and($cards[$child->identifier]['parent']['id'])->toBe($epic->id);
});

it('flags a card whose epic is filtered off the board', function () {
    $project = Project::factory()->create();
    $epic = epicIn($project, ['archived_at' => now()]);
    $hidden = childOf($epic);
    $loose = Issue::factory()->for($project)->create();
    $listed = childOf(epicIn($project));
    $this->actingAs(member($project));

    $cards = boardCards($this->get('/issues/board'));

    expect($cards[$hidden->identifier]['parent'])->toBeNull()
        ->and($cards[$hidden->identifier]['parentOffBoard'])->toBeTrue()
        ->and($cards[$loose->identifier]['parentOffBoard'])->toBeFalse()
        ->and($cards[$listed->identifier]['parentOffBoard'])->toBeFalse();

    $cards = boardCards($this->get('/issues/board?archived=1'));

    expect($cards[$hidden->identifier]['parentOffBoard'])->toBeFalse();
});

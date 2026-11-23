<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

it('creates an issue assigned to an epic', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $epic = (new CreateIssueAction)->handle($team, 'Big feature', IssueType::Feature);
    $user = member($team);

    $this->actingAs($user)->post('/issues', [
        'project_id' => $team->id,
        'title' => 'Sub-task',
        'type' => 'feature',
        'parent_id' => $epic->id,
    ])->assertRedirect();

    $child = Issue::query()->where('title', 'Sub-task')->first();
    expect($child->parent_id)->toBe($epic->id);
});

it('rejects creating an issue under a parent that already has a parent itself', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $child = (new CreateIssueAction)->handle($team, 'Child', IssueType::Feature, parent: $epic);

    $this->actingAs(member($team))->post('/issues', [
        'project_id' => $team->id,
        'title' => 'Grandchild',
        'type' => 'feature',
        'parent_id' => $child->id,
    ])->assertSessionHasErrors('parent_id');
});

it('assigns an existing issue to an epic via update', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $issue = (new CreateIssueAction)->handle($team, 'Standalone issue', IssueType::Feature);

    $this->actingAs(member($team))->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'parent_id' => $epic->id,
    ])->assertRedirect();

    expect($issue->fresh()->parent_id)->toBe($epic->id);
});

it('removes an epic assignment when parent_id is submitted empty', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $issue = (new CreateIssueAction)->handle($team, 'Child issue', IssueType::Feature, parent: $epic);

    $this->actingAs(member($team))->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'parent_id' => '',
    ])->assertRedirect();

    expect($issue->fresh()->parent_id)->toBeNull();
});

it('rejects an issue being assigned as its own epic', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(member($team))->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'parent_id' => $issue->id,
    ])->assertSessionHasErrors('parent_id');
});

it('rejects assigning a parent to an issue that already has sub-issues', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $epicA = (new CreateIssueAction)->handle($team, 'Epic A', IssueType::Feature);
    $epicB = (new CreateIssueAction)->handle($team, 'Epic B', IssueType::Feature);
    (new CreateIssueAction)->handle($team, 'Child of A', IssueType::Feature, parent: $epicA);

    $this->actingAs(member($team))->patch("/issues/{$epicA->identifier}", [
        'title' => $epicA->title,
        'type' => 'feature',
        'priority' => 'none',
        'parent_id' => $epicB->id,
    ])->assertSessionHasErrors('parent_id');
});

it('shows sub-issues and progress on the epic detail page', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $done = (new CreateIssueAction)->handle($team, 'Done child', IssueType::Feature, parent: $epic);
    $done->forceFill(['status' => IssueStatus::Done])->save();
    (new CreateIssueAction)->handle($team, 'Backlog child', IssueType::Feature, parent: $epic);

    $this->actingAs(member($team))
        ->get("/issues/{$epic->identifier}")
        ->assertInertia(fn ($page) => $page
            ->where('issue.childrenCount', 2)
            ->has('issue.children', 2)
        );
});

it('shows the parent link on a child issue detail page', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $child = (new CreateIssueAction)->handle($team, 'Child', IssueType::Feature, parent: $epic);

    $this->actingAs(member($team))
        ->get("/issues/{$child->identifier}")
        ->assertInertia(fn ($page) => $page
            ->where('issue.parent.identifier', $epic->identifier)
        );
});

it('only offers top-level issues as eligible epics', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $child = (new CreateIssueAction)->handle($team, 'Child', IssueType::Feature, parent: $epic);

    $this->actingAs(member($team))
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->has('epics', 1)
            ->where('epics.0.identifier', $epic->identifier)
        );
});

/**
 * An epic in another project than THI, with the acting user granted access to
 * that project or not. "other organization" keeps the grant, so only the
 * organization boundary can refuse it.
 *
 * @return array{0: Project, 1: Issue, 2: User}
 */
function foreignEpicCase(bool $sameOrganization, bool $canSeeEpic): array
{
    $organization = Organization::factory()->create();
    $team = Project::factory()->create(['key' => 'THI', 'organization_id' => $organization->id]);
    $user = member($team);
    $other = Project::factory()->create([
        'key' => 'OTH',
        'organization_id' => $sameOrganization ? $organization->id : Organization::factory()->create()->id,
    ]);
    $epic = (new CreateIssueAction)->handle($other, 'Foreign epic', IssueType::Feature);

    if ($canSeeEpic) {
        joinProjects($user, $other);
    }

    return [$team, $epic, $user];
}

$refusedEpics = [
    'same organization, epic not visible' => [true, false],
    'other organization, epic visible' => [false, true],
];

it('creates an issue under a visible epic from another project in the same organization', function () {
    [$team, $epic, $user] = foreignEpicCase(sameOrganization: true, canSeeEpic: true);

    $this->actingAs($user)->post('/issues', [
        'project_id' => $team->id,
        'title' => 'Sub-task',
        'type' => 'feature',
        'parent_id' => $epic->id,
    ])->assertSessionHasNoErrors();

    expect(Issue::query()->where('title', 'Sub-task')->value('parent_id'))->toBe($epic->id);
});

it('assigns an issue to a visible epic from another project in the same organization', function () {
    [$team, $epic, $user] = foreignEpicCase(sameOrganization: true, canSeeEpic: true);
    $issue = (new CreateIssueAction)->handle($team, 'Standalone issue', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'parent_id' => $epic->id,
    ])->assertSessionHasNoErrors();

    expect($issue->fresh()->parent_id)->toBe($epic->id);
});

it('rejects creating an issue under an epic from another organization or one it cannot see', function (bool $sameOrganization, bool $canSeeEpic) {
    [$team, $epic, $user] = foreignEpicCase($sameOrganization, $canSeeEpic);

    $this->actingAs($user)->post('/issues', [
        'project_id' => $team->id,
        'title' => 'Sub-task',
        'type' => 'feature',
        'parent_id' => $epic->id,
    ])->assertSessionHasErrors('parent_id');

    expect(Issue::query()->where('title', 'Sub-task')->exists())->toBeFalse();
})->with($refusedEpics);

it('rejects assigning an issue to an epic from another organization or one it cannot see', function (bool $sameOrganization, bool $canSeeEpic) {
    [$team, $epic, $user] = foreignEpicCase($sameOrganization, $canSeeEpic);
    $issue = (new CreateIssueAction)->handle($team, 'Standalone issue', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'parent_id' => $epic->id,
    ])->assertSessionHasErrors('parent_id');

    expect($issue->fresh()->parent_id)->toBeNull();
})->with($refusedEpics);

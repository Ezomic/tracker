<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project, ProjectLevel::Write);
    $this->epic = dropIssue($this->project, ['title' => 'Secret epic title']);
    $this->card = dropIssue($this->project);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function dropIssue(Project $project, array $attributes = []): Issue
{
    return Issue::factory()->for($project)->create($attributes);
}

function setParent(Issue $issue, mixed $parent): TestResponse
{
    return test()->from('/issues/board')->patch("/issues/{$issue->identifier}/parent", ['parent' => $parent]);
}

function parentActivities(Issue $issue): int
{
    return Activity::query()
        ->where('issue_id', $issue->id)
        ->where('type', 'parent_changed')
        ->count();
}

it('attaches a card to an epic and goes back to the board', function () {
    $this->actingAs($this->user);

    setParent($this->card, $this->epic->identifier)
        ->assertRedirect('/issues/board')
        ->assertSessionHasNoErrors();

    expect($this->card->refresh()->parent_id)->toBe($this->epic->id)
        ->and(parentActivities($this->card))->toBe(1);
});

it('detaches a card when the parent is null', function () {
    $this->card->forceFill(['parent_id' => $this->epic->id])->save();
    $this->actingAs($this->user);

    setParent($this->card, null)->assertRedirect('/issues/board')->assertSessionHasNoErrors();

    expect($this->card->refresh()->parent_id)->toBeNull();
});

it('treats an empty string as detaching', function () {
    $this->card->forceFill(['parent_id' => $this->epic->id])->save();
    $this->actingAs($this->user);

    setParent($this->card, '')->assertSessionHasNoErrors();

    expect($this->card->refresh()->parent_id)->toBeNull();
});

it('requires the parent key', function () {
    $this->card->forceFill(['parent_id' => $this->epic->id])->save();
    $this->actingAs($this->user);

    $this->from('/issues/board')->patch("/issues/{$this->card->identifier}/parent", [])
        ->assertSessionHasErrors('parent');

    expect($this->card->refresh()->parent_id)->toBe($this->epic->id);
});

it('moves a card from one epic to another', function () {
    $other = dropIssue($this->project);
    $this->card->forceFill(['parent_id' => $this->epic->id])->save();
    $this->actingAs($this->user);

    setParent($this->card, $other->identifier)->assertSessionHasNoErrors();

    expect($this->card->refresh()->parent_id)->toBe($other->id);
});

it('records nothing when dropped onto the epic it already belongs to', function () {
    $this->card->forceFill(['parent_id' => $this->epic->id])->save();
    $before = parentActivities($this->card);
    $this->actingAs($this->user);

    setParent($this->card, $this->epic->identifier)->assertSessionHasNoErrors();

    expect($this->card->refresh()->parent_id)->toBe($this->epic->id)
        ->and(parentActivities($this->card))->toBe($before);
});

it('changes only the parent, never the lane or board position', function () {
    $before = $this->card->only(['status', 'workflow_state_id', 'board_position']);
    $this->actingAs($this->user);

    setParent($this->card, $this->epic->identifier);

    expect($this->card->refresh()->only(['status', 'workflow_state_id', 'board_position']))->toBe($before);
});

it('allows an archived epic, so undo still restores a link to it', function () {
    $this->epic->forceFill(['archived_at' => now()])->save();
    $this->actingAs($this->user);

    setParent($this->card, $this->epic->identifier)->assertSessionHasNoErrors();

    expect($this->card->refresh()->parent_id)->toBe($this->epic->id);
});

it('refuses invalid parents without changing anything', function (Closure $parent) {
    $this->actingAs($this->user);

    $response = setParent($this->card, $parent->call($this));

    $response->assertSessionHasErrors('parent');
    expect($this->card->refresh()->parent_id)->toBeNull();
})->with([
    'the issue itself' => [fn () => $this->card->identifier],
    'an issue that has a parent' => [function () {
        $child = dropIssue($this->project);
        $child->forceFill(['parent_id' => $this->epic->id])->save();

        return $child->identifier;
    }],
    'a nonexistent identifier' => [fn () => 'THI-999999'],
    'a non-string value' => [fn () => ['THI-1']],
    'an epic in another project' => [function () {
        $other = Project::factory()->create();
        joinProjects($this->user, $other, ProjectLevel::Write);

        return dropIssue($other)->identifier;
    }],
    'an epic in another organization' => [function () {
        [$organization] = organizationWith();
        $foreign = Project::factory()->create(['organization_id' => $organization->id]);

        return dropIssue($foreign)->identifier;
    }],
]);

it('refuses to give a parent to an issue that has sub-issues', function () {
    $child = dropIssue($this->project);
    $child->forceFill(['parent_id' => $this->card->id])->save();
    $this->actingAs($this->user);

    setParent($this->card, $this->epic->identifier)->assertSessionHasErrors('parent');

    expect($this->card->refresh()->parent_id)->toBeNull();
});

it('refuses an epic the user cannot see in the same project', function () {
    $restricted = member($this->project, ProjectLevel::Write);
    $this->project->members()->updateExistingPivot($restricted->id, ['own_issues_only' => true]);
    $own = dropIssue($this->project, ['owner_id' => $restricted->id]);
    $this->actingAs($restricted);

    setParent($own, $this->epic->identifier)->assertSessionHasErrors('parent');

    expect($own->refresh()->parent_id)->toBeNull();
});

it('gives the same answer for an epic it cannot see as for one that does not exist', function () {
    $other = Project::factory()->create();
    $hidden = dropIssue($other, ['title' => 'Hidden epic title']);
    $this->actingAs($this->user);

    $answer = ['parent' => 'That epic does not exist.'];

    setParent($this->card, 'NOPE-1')->assertSessionHasErrors($answer);
    $refused = setParent($this->card, $hidden->identifier)->assertSessionHasErrors($answer);

    expect(json_encode(session()->all()))->not->toContain('Hidden epic title')
        ->and($refused->getContent())->not->toContain('Hidden epic title');
});

it('refuses users who cannot write to the card', function (Closure $actor) {
    $user = $actor->call($this);
    $this->card->forceFill(['parent_id' => $this->epic->id])->save();
    $this->actingAs($user);

    $response = setParent($this->card, null);

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('Secret epic title')
        ->and($this->card->refresh()->parent_id)->toBe($this->epic->id);
})->with([
    'a read-only grant' => [fn () => member($this->project, ProjectLevel::Read)],
    'a non-member' => [fn () => User::factory()->create()],
    'a restricted member on someone else\'s issue' => [function () {
        $restricted = member($this->project, ProjectLevel::Write);
        $this->project->members()->updateExistingPivot($restricted->id, ['own_issues_only' => true]);

        return $restricted;
    }],
]);

it('refuses a guest with a read grant', function () {
    [$organization, $guest] = organizationWith(OrganizationRole::Guest);
    $project = projectInOrganization($organization, $guest);
    joinProjects($guest, $project, ProjectLevel::Read);
    $epic = dropIssue($project);
    $card = dropIssue($project);
    $this->actingAs($guest);

    setParent($card, $epic->identifier)->assertForbidden();

    expect($card->refresh()->parent_id)->toBeNull();
});

it('refuses re-parenting someone else\'s card even to an epic the user can see', function () {
    $outsider = member(Project::factory()->create(), ProjectLevel::Admin);
    $this->actingAs($outsider);

    setParent($this->card, $this->epic->identifier)->assertForbidden();

    expect($this->card->refresh()->parent_id)->toBeNull();
});

it('restores the previous epic on undo after that epic was archived', function () {
    $this->card->forceFill(['parent_id' => $this->epic->id])->save();
    $next = dropIssue($this->project);
    $this->actingAs($this->user);

    setParent($this->card, $next->identifier)->assertSessionHasNoErrors();
    $this->epic->forceFill(['archived_at' => now()])->save();
    setParent($this->card, $this->epic->identifier)->assertSessionHasNoErrors();

    expect($this->card->refresh()->parent_id)->toBe($this->epic->id);
});

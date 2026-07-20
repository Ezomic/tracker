<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\Label;
use App\Models\User;

function apiIssue(): array
{
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);

    return [$org, $owner, $project, $issue];
}

it('updates priority on its own', function () {
    [, $owner, , $issue] = apiIssue();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['priority' => 'urgent'])
        ->assertOk()
        ->assertJson(['priority' => 'urgent']);

    expect($issue->fresh()->priority)->toBe(IssuePriority::Urgent);
});

it('updates type on its own', function () {
    [, $owner, , $issue] = apiIssue();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['type' => 'fix'])
        ->assertOk()
        ->assertJson(['type' => 'fix']);

    expect($issue->fresh()->type)->toBe(IssueType::Fix);
});

it('updates the estimate from a duration string', function () {
    [, $owner, , $issue] = apiIssue();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['estimate' => '4h 30m'])
        ->assertOk()
        ->assertJson(['estimate_minutes' => 270]);

    expect($issue->fresh()->estimate_minutes)->toBe(270);
});

it('clears the estimate when it is submitted null', function () {
    [, $owner, , $issue] = apiIssue();
    $issue->forceFill(['estimate_minutes' => 120])->save();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['estimate' => null])
        ->assertOk();

    expect($issue->fresh()->estimate_minutes)->toBeNull();
});

it('rejects an unparseable estimate', function () {
    [, $owner, , $issue] = apiIssue();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['estimate' => 'soonish'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('estimate');
});

it('assigns by email and unassigns on an empty string', function () {
    [, $owner, , $issue] = apiIssue();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['assignee' => $owner->email])
        ->assertOk()
        ->assertJson(['assignee' => $owner->email]);

    expect($issue->fresh()->assignee_id)->toBe($owner->id);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['assignee' => ''])
        ->assertOk()
        ->assertJson(['assignee' => null]);

    expect($issue->fresh()->assignee_id)->toBeNull();
});

it('rejects an assignee who is not a project member', function () {
    [, $owner, , $issue] = apiIssue();
    $stranger = User::factory()->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['assignee' => $stranger->email])
        ->assertStatus(422)
        ->assertJsonValidationErrors('assignee');
});

it('syncs labels by name', function () {
    [$org, $owner, , $issue] = apiIssue();
    Label::factory()->for($org)->create(['name' => 'backend']);
    Label::factory()->for($org)->create(['name' => 'api']);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['labels' => ['backend', 'api']])
        ->assertOk();

    expect($issue->fresh()->labels->pluck('name')->sort()->values()->all())
        ->toBe(['api', 'backend']);
});

it('accepts labels as a comma-separated string', function () {
    [$org, $owner, , $issue] = apiIssue();
    Label::factory()->for($org)->create(['name' => 'backend']);
    Label::factory()->for($org)->create(['name' => 'api']);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['labels' => 'backend, api'])
        ->assertOk();

    expect($issue->fresh()->labels)->toHaveCount(2);
});

it('leaves labels untouched when the key is omitted', function () {
    [$org, $owner, , $issue] = apiIssue();
    $issue->labels()->attach(Label::factory()->for($org)->create(['name' => 'backend']));

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['title' => 'Retitled'])
        ->assertOk();

    expect($issue->fresh()->labels->pluck('name')->all())->toBe(['backend']);
});

it('clears labels when an empty array is submitted', function () {
    [$org, $owner, , $issue] = apiIssue();
    $issue->labels()->attach(Label::factory()->for($org)->create(['name' => 'backend']));

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['labels' => []])
        ->assertOk();

    expect($issue->fresh()->labels)->toBeEmpty();
});

it('rejects a label that does not exist in the project', function () {
    [, $owner, , $issue] = apiIssue();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['labels' => ['nonsense']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('labels.0');
});

it('leaves untouched fields alone on a partial patch', function () {
    [$org, $owner, , $issue] = apiIssue();
    $issue->forceFill([
        'priority' => IssuePriority::High,
        'estimate_minutes' => 60,
        'assignee_id' => $owner->id,
        'description' => 'Original description',
    ])->save();
    $issue->labels()->attach(Label::factory()->for($org)->create(['name' => 'backend']));

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['title' => 'Only the title moves'])
        ->assertOk();

    expect($issue->fresh())
        ->title->toBe('Only the title moves')
        ->priority->toBe(IssuePriority::High)
        ->estimate_minutes->toBe(60)
        ->assignee_id->toBe($owner->id)
        ->description->toBe('Original description')
        ->and($issue->fresh()->labels)->toHaveCount(1);
});

it('returns the full detail shape so a client can confirm what applied', function () {
    [, $owner, , $issue] = apiIssue();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['priority' => 'low'])
        ->assertOk()
        ->assertJsonStructure([
            'identifier', 'number', 'title', 'description', 'type', 'priority',
            'status', 'estimate_minutes', 'labels', 'branch_name', 'project',
            'owner', 'assignee', 'parent', 'url', 'created_at',
        ]);
});

it('applies several fields in one call', function () {
    [$org, $owner, , $issue] = apiIssue();
    Label::factory()->for($org)->create(['name' => 'api']);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", [
            'title' => 'Fully reconfigured',
            'type' => 'fix',
            'priority' => 'high',
            'estimate' => '90m',
            'assignee' => $owner->email,
            'labels' => ['api'],
        ])
        ->assertOk();

    expect($issue->fresh())
        ->title->toBe('Fully reconfigured')
        ->type->toBe(IssueType::Fix)
        ->priority->toBe(IssuePriority::High)
        ->estimate_minutes->toBe(90)
        ->assignee_id->toBe($owner->id)
        ->and($issue->fresh()->labels->pluck('name')->all())->toBe(['api']);
});

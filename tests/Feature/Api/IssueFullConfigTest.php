<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Models\Issue;
use App\Models\IssueTemplate;
use App\Models\Label;
use App\Models\Organization;

it('sets priority, estimate and labels given explicitly', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI', 'next_number' => 0]);
    Label::factory()->create(['organization_id' => $organization->id, 'name' => 'backend']);
    Label::factory()->create(['organization_id' => $organization->id, 'name' => 'api']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Rate limit the public API',
        'type' => 'feature',
        'priority' => 'urgent',
        'estimate' => '4h 30m',
        'labels' => ['backend', 'API'],
    ])->assertCreated();

    $issue = Issue::query()->where('title', 'Rate limit the public API')->firstOrFail();

    expect($issue->priority)->toBe(IssuePriority::Urgent)
        ->and($issue->estimate_minutes)->toBe(270)
        ->and($issue->labels->pluck('name')->sort()->values()->all())->toBe(['api', 'backend']);
});

it('lets an explicit priority and labels override the template defaults', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI', 'next_number' => 0]);
    $templateLabel = Label::factory()->create(['organization_id' => $organization->id, 'name' => 'bug']);
    Label::factory()->create(['organization_id' => $organization->id, 'name' => 'backend']);
    $template = IssueTemplate::factory()->for($organization)->create([
        'name' => 'Bug report',
        'priority' => 'high',
    ]);
    $template->labels()->attach($templateLabel);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Override me',
        'type' => 'fix',
        'template' => 'Bug report',
        'priority' => 'low',
        'labels' => ['backend'],
    ])->assertCreated();

    $issue = Issue::query()->where('title', 'Override me')->firstOrFail();

    expect($issue->priority)->toBe(IssuePriority::Low)
        ->and($issue->labels->pluck('name')->all())->toBe(['backend']);
});

it('falls back to the template priority and labels when none are given', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI', 'next_number' => 0]);
    $label = Label::factory()->create(['organization_id' => $organization->id, 'name' => 'bug']);
    $template = IssueTemplate::factory()->for($organization)->create([
        'name' => 'Bug report',
        'priority' => 'high',
    ]);
    $template->labels()->attach($label);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Use the template',
        'type' => 'fix',
        'template' => 'Bug report',
    ])->assertCreated();

    $issue = Issue::query()->where('title', 'Use the template')->firstOrFail();

    expect($issue->priority)->toBe(IssuePriority::High)
        ->and($issue->labels->pluck('name')->all())->toBe(['bug']);
});

it('accepts labels as a comma-separated string', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI', 'next_number' => 0]);
    Label::factory()->create(['organization_id' => $organization->id, 'name' => 'backend']);
    Label::factory()->create(['organization_id' => $organization->id, 'name' => 'api']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Comma separated',
        'type' => 'feature',
        'labels' => 'backend, api',
    ])->assertCreated();

    expect(Issue::query()->where('title', 'Comma separated')->firstOrFail()->labels)->toHaveCount(2);
});

it('rejects an unknown label, an invalid priority and a malformed estimate', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Anything',
        'type' => 'feature',
        'labels' => ['nope'],
    ])->assertUnprocessable()->assertJsonValidationErrors('labels.0');

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Anything',
        'type' => 'feature',
        'priority' => 'critical',
    ])->assertUnprocessable()->assertJsonValidationErrors('priority');

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Anything',
        'type' => 'feature',
        'estimate' => 'soon',
    ])->assertUnprocessable()->assertJsonValidationErrors('estimate');
});

it('does not accept a label from another organization', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI']);
    $otherOrg = Organization::factory()->create();
    Label::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'foreign']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Anything',
        'type' => 'feature',
        'labels' => ['foreign'],
    ])->assertUnprocessable()->assertJsonValidationErrors('labels.0');
});

it('lists the labels for the project organization', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI']);
    Label::factory()->create(['organization_id' => $organization->id, 'name' => 'backend', 'color' => 'blue']);

    $this->actingAs($user, 'sanctum')->getJson('/api/labels?project=THI')
        ->assertOk()
        ->assertExactJson([['name' => 'backend', 'color' => 'blue']]);
});

it('lists the members of a project', function () {
    [$organization, $user] = organizationWith();
    $project = projectInOrganization($organization, $user, ['key' => 'THI']);

    $this->actingAs($user, 'sanctum')->getJson('/api/members?project=THI')
        ->assertOk()
        ->assertJsonFragment(['email' => $user->email]);

    expect($project->members()->count())->toBe(1);
});

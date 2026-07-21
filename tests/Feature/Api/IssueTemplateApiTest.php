<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Models\Issue;
use App\Models\IssueTemplate;
use App\Models\Label;
use App\Models\Organization;

it('lists the templates for the project organization', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI']);
    $label = Label::factory()->create(['organization_id' => $organization->id, 'name' => 'bug']);
    $template = IssueTemplate::factory()->for($organization)->create([
        'name' => 'Bug report',
        'description' => 'Steps to reproduce:',
        'type' => 'fix',
        'priority' => 'high',
    ]);
    $template->labels()->attach($label);
    $template->refresh();

    $this->actingAs($user, 'sanctum')->getJson('/api/templates?project=THI')
        ->assertOk()
        ->assertExactJson([[
            'id' => $template->id,
            'name' => 'Bug report',
            'description' => 'Steps to reproduce:',
            'type' => 'fix',
            'priority' => 'high',
            'labels' => ['bug'],
            'cadence' => $template->cadence->value,
            'nextRunAt' => $template->next_run_at?->toIso8601String(),
            'targetProjectId' => $template->target_project_id,
        ]]);
});

it('applies the template priority, labels, and description when creating an issue', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI', 'next_number' => 0]);
    $label = Label::factory()->create(['organization_id' => $organization->id, 'name' => 'bug']);
    $template = IssueTemplate::factory()->for($organization)->create([
        'name' => 'Bug report',
        'description' => 'Steps to reproduce:',
        'priority' => 'high',
    ]);
    $template->labels()->attach($label);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Checkout crashes',
        'type' => 'fix',
        'template' => 'bug report',
    ])->assertCreated();

    $issue = Issue::query()->where('title', 'Checkout crashes')->firstOrFail();

    expect($issue->priority)->toBe(IssuePriority::High)
        ->and($issue->description)->toBe('Steps to reproduce:')
        ->and($issue->labels->pluck('name')->all())->toBe(['bug']);
});

it('keeps an explicit description over the template description', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI', 'next_number' => 0]);
    IssueTemplate::factory()->for($organization)->create([
        'name' => 'Bug report',
        'description' => 'Template body',
    ]);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Checkout crashes',
        'type' => 'fix',
        'description' => 'My own body',
        'template' => 'Bug report',
    ])->assertCreated();

    expect(Issue::query()->where('title', 'Checkout crashes')->firstOrFail()->description)
        ->toBe('My own body');
});

it('rejects an unknown template name', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Anything',
        'type' => 'feature',
        'template' => 'does-not-exist',
    ])->assertUnprocessable()->assertJsonValidationErrors('template');
});

it('does not accept a template from another organization', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI']);
    $otherOrg = Organization::factory()->create();
    IssueTemplate::factory()->for($otherOrg)->create(['name' => 'Foreign template']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Anything',
        'type' => 'feature',
        'template' => 'Foreign template',
    ])->assertUnprocessable()->assertJsonValidationErrors('template');
});

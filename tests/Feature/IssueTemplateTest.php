<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Enums\OrganizationRole;
use App\Models\Issue;
use App\Models\IssueTemplate;
use App\Models\Label;

it('lists templates to any organization member', function () {
    [$org, $owner] = organizationWith();
    $bug = IssueTemplate::factory()->for($org)->create(['name' => 'Bug report']);
    $bug->labels()->attach(Label::factory()->for($org)->create(['name' => 'bug']));

    $this->actingAs($owner)->get('/settings/templates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Templates')
            ->has('templates', 1)
            ->where('templates.0.name', 'Bug report')
            ->has('templates.0.labelIds', 1)
            ->where('canManage', true)
        );
});

it('marks a plain member as unable to manage', function () {
    [$org, $member] = organizationWith(OrganizationRole::Member);

    $this->actingAs($member)->get('/settings/templates')
        ->assertInertia(fn ($page) => $page->where('canManage', false));
});

it('forbids a guest from viewing templates or the options endpoint', function () {
    [$org, $guest] = organizationWith(OrganizationRole::Guest);

    $this->actingAs($guest)->get('/settings/templates')->assertForbidden();
    $this->actingAs($guest)->getJson('/settings/template-options')->assertForbidden();
});

it('creates a template with defaults and labels', function () {
    [$org, $owner] = organizationWith();
    $label = Label::factory()->for($org)->create();

    $this->actingAs($owner)->post('/settings/templates', [
        'name' => 'Bug report',
        'description' => "## Steps\n## Expected",
        'type' => 'fix',
        'priority' => 'high',
        'labels' => [$label->id],
    ])->assertRedirect();

    $template = IssueTemplate::query()->firstOrFail();
    expect($template->name)->toBe('Bug report')
        ->and($template->type)->toBe(IssueType::Fix)
        ->and($template->priority)->toBe(IssuePriority::High)
        ->and($template->organization_id)->toBe($org->id)
        ->and($template->labels->pluck('id')->all())->toBe([$label->id]);
});

it('forbids a plain member from creating a template', function () {
    [$org, $member] = organizationWith(OrganizationRole::Member);

    $this->actingAs($member)->post('/settings/templates', ['name' => 'Nope'])
        ->assertForbidden();

    expect(IssueTemplate::query()->count())->toBe(0);
});

it('rejects a label from another organization on a template', function () {
    [$org, $owner] = organizationWith();
    [$otherOrg] = organizationWith();
    $foreign = Label::factory()->for($otherOrg)->create();

    $this->actingAs($owner)->post('/settings/templates', [
        'name' => 'Bug report',
        'labels' => [$foreign->id],
    ])->assertSessionHasErrors('labels.0');
});

it('rejects a duplicate name within the same organization', function () {
    [$org, $owner] = organizationWith();
    IssueTemplate::factory()->for($org)->create(['name' => 'Bug report']);

    $this->actingAs($owner)->post('/settings/templates', ['name' => 'Bug report'])
        ->assertSessionHasErrors('name');
});

it('allows the same template name in a different organization', function () {
    [$org, $owner] = organizationWith();
    [$otherOrg] = organizationWith();
    IssueTemplate::factory()->for($otherOrg)->create(['name' => 'Bug report']);

    $this->actingAs($owner)->post('/settings/templates', ['name' => 'Bug report'])
        ->assertRedirect();

    expect(IssueTemplate::query()->count())->toBe(2);
});

it('updates a template', function () {
    [$org, $owner] = organizationWith();
    $template = IssueTemplate::factory()->for($org)->create(['name' => 'Bug report']);

    $this->actingAs($owner)->patch("/settings/templates/{$template->id}", [
        'name' => 'Bug report',
        'description' => 'Updated body',
        'priority' => 'urgent',
    ])->assertRedirect();

    expect($template->fresh())
        ->description->toBe('Updated body')
        ->priority->toBe(IssuePriority::Urgent);
});

it('deletes a template', function () {
    [$org, $owner] = organizationWith();
    $template = IssueTemplate::factory()->for($org)->create();

    $this->actingAs($owner)->delete("/settings/templates/{$template->id}")
        ->assertRedirect();

    expect(IssueTemplate::query()->count())->toBe(0);
});

it('404s when managing a template from another organization', function () {
    [$org, $owner] = organizationWith();
    [$otherOrg] = organizationWith();
    $template = IssueTemplate::factory()->for($otherOrg)->create();

    $this->actingAs($owner)->delete("/settings/templates/{$template->id}")
        ->assertNotFound();

    expect(IssueTemplate::query()->count())->toBe(1);
});

it('cascades templates when the organization is deleted', function () {
    [$org] = organizationWith();
    IssueTemplate::factory()->for($org)->create();

    $org->delete();

    expect(IssueTemplate::query()->count())->toBe(0);
});

it('serves the current organization templates as json for the picker', function () {
    [$org, $owner] = organizationWith();
    IssueTemplate::factory()->for($org)->create(['name' => 'Bug report']);

    $this->actingAs($owner)->getJson('/settings/template-options')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Bug report');
});

it('applies a template priority and labels when filing an issue', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $label = Label::factory()->for($org)->create();
    $template = IssueTemplate::factory()->for($org)->create([
        'type' => IssueType::Fix,
        'priority' => IssuePriority::High,
    ]);
    $template->labels()->attach($label);

    $this->actingAs($owner)->post('/issues', [
        'project_id' => $project->id,
        'title' => 'Filed from a template',
        'type' => 'fix',
        'description' => "## Steps\nEdited by the user",
        'template_id' => $template->id,
    ])->assertRedirect('/issues/THI-1');

    $issue = Issue::query()->firstOrFail();
    expect($issue->priority)->toBe(IssuePriority::High)
        ->and($issue->description)->toBe("## Steps\nEdited by the user")
        ->and($issue->labels->pluck('id')->all())->toBe([$label->id]);
});

it('files a blank issue with no template', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);

    $this->actingAs($owner)->post('/issues', [
        'project_id' => $project->id,
        'title' => 'Blank one',
        'type' => 'feature',
        'template_id' => '',
    ])->assertRedirect('/issues/THI-1');

    $issue = Issue::query()->firstOrFail();
    expect($issue->priority)->toBe(IssuePriority::None)
        ->and($issue->labels)->toBeEmpty();
});

it('rejects a template from a different organization when filing', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    [$otherOrg] = organizationWith();
    $foreign = IssueTemplate::factory()->for($otherOrg)->create();

    $this->actingAs($owner)->post('/issues', [
        'project_id' => $project->id,
        'title' => 'Nope',
        'type' => 'feature',
        'template_id' => $foreign->id,
    ])->assertSessionHasErrors('template_id');

    expect(Issue::query()->count())->toBe(0);
});

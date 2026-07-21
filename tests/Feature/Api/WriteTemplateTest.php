<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\IssueTemplate;
use App\Models\Label;

it('creates a template with labels', function () {
    [$org, $owner] = organizationWith();
    $label = Label::factory()->for($org)->create(['name' => 'bug']);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/templates', [
            'name' => 'Bug report',
            'description' => 'Steps:',
            'type' => 'fix',
            'priority' => 'high',
            'labels' => [$label->id],
        ])
        ->assertCreated()
        ->assertJson(['name' => 'Bug report', 'type' => 'fix', 'priority' => 'high', 'labels' => ['bug']]);

    expect($org->issueTemplates()->where('name', 'Bug report')->exists())->toBeTrue();
});

it('rejects a duplicate template name', function () {
    [$org, $owner] = organizationWith();
    IssueTemplate::factory()->for($org)->create(['name' => 'Bug report']);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/templates', ['name' => 'Bug report'])
        ->assertUnprocessable();
});

it('updates a template', function () {
    [$org, $owner] = organizationWith();
    $template = IssueTemplate::factory()->for($org)->create(['name' => 'Old']);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/templates/{$template->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJson(['name' => 'New']);
});

it('deletes a template', function () {
    [$org, $owner] = organizationWith();
    $template = IssueTemplate::factory()->for($org)->create();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/templates/{$template->id}")
        ->assertNoContent();

    expect(IssueTemplate::query()->whereKey($template->id)->exists())->toBeFalse();
});

it('404s when the template belongs to another organization', function () {
    [, $owner] = organizationWith();
    $foreign = IssueTemplate::factory()->create(['name' => 'Theirs']);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/templates/{$foreign->id}")
        ->assertNotFound();
});

it('forbids a non-managing member from writing templates', function () {
    [, $member] = organizationWith(OrganizationRole::Member);

    $this->actingAs($member, 'sanctum')
        ->postJson('/api/templates', ['name' => 'Nope'])
        ->assertForbidden();
});

it('requires authentication', function () {
    $this->postJson('/api/templates', ['name' => 'x'])->assertUnauthorized();
});

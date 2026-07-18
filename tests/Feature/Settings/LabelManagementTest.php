<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\LabelColor;
use App\Enums\OrganizationRole;
use App\Models\Label;
use App\Models\User;

it('renders the labels page with issue counts', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner);
    $label = Label::factory()->for($org)->create(['name' => 'bug', 'color' => 'red']);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);
    $issue->labels()->attach($label);

    $this->actingAs($owner)->get('/settings/labels')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Labels')
            ->where('labels.0.name', 'bug')
            ->where('labels.0.issuesCount', 1)
            ->where('canManage', true)
        );
});

it('only lists the current organization labels', function () {
    [$org, $owner] = organizationWith();
    Label::factory()->for($org)->create(['name' => 'mine']);
    Label::factory()->create(['name' => 'theirs']);

    $this->actingAs($owner)->get('/settings/labels')
        ->assertInertia(fn ($page) => $page
            ->has('labels', 1)
            ->where('labels.0.name', 'mine')
        );
});

it('marks a plain member as unable to manage', function () {
    [$org, $member] = organizationWith(OrganizationRole::Member);

    $this->actingAs($member)->get('/settings/labels')
        ->assertInertia(fn ($page) => $page->where('canManage', false));
});

it('forbids a guest from viewing the labels library', function () {
    [$org, $guest] = organizationWith(OrganizationRole::Guest);

    $this->actingAs($guest)->get('/settings/labels')->assertForbidden();
});

it('creates a label in the current organization', function () {
    [$org, $owner] = organizationWith();

    $this->actingAs($owner)->post('/settings/labels', ['name' => 'bug', 'color' => 'red'])
        ->assertRedirect(route('labels.index'));

    expect(Label::query()->where('name', 'bug')->first()->organization_id)->toBe($org->id);
});

it('forbids a plain member from creating a label', function () {
    [$org, $member] = organizationWith(OrganizationRole::Member);

    $this->actingAs($member)->post('/settings/labels', ['name' => 'bug', 'color' => 'red'])
        ->assertForbidden();

    expect(Label::query()->count())->toBe(0);
});

it('rejects a duplicate label name in the same organization', function () {
    [$org, $owner] = organizationWith();
    Label::factory()->for($org)->create(['name' => 'bug']);

    $this->actingAs($owner)->post('/settings/labels', ['name' => 'bug', 'color' => 'blue'])
        ->assertSessionHasErrors('name');
});

it('allows the same label name in a different organization', function () {
    [$org, $owner] = organizationWith();
    Label::factory()->create(['name' => 'bug']);

    $this->actingAs($owner)->post('/settings/labels', ['name' => 'bug', 'color' => 'blue'])
        ->assertRedirect(route('labels.index'));

    expect(Label::query()->where('name', 'bug')->count())->toBe(2);
});

it('rejects an invalid color', function () {
    [$org, $owner] = organizationWith();

    $this->actingAs($owner)->post('/settings/labels', ['name' => 'bug', 'color' => 'not-a-color'])
        ->assertSessionHasErrors('color');
});

it('updates a label', function () {
    [$org, $owner] = organizationWith();
    $label = Label::factory()->for($org)->create(['name' => 'old', 'color' => 'gray']);

    $this->actingAs($owner)->patch("/settings/labels/{$label->id}", ['name' => 'new', 'color' => 'green'])
        ->assertRedirect(route('labels.index'));

    expect($label->fresh())->name->toBe('new')->color->toBe(LabelColor::Green);
});

it('forbids editing a label in an organization you do not manage', function () {
    $label = Label::factory()->create(['name' => 'theirs']);

    $this->actingAs(User::factory()->create())
        ->patch("/settings/labels/{$label->id}", ['name' => 'hijacked', 'color' => 'red'])
        ->assertForbidden();

    expect($label->fresh()->name)->toBe('theirs');
});

it('deletes a label', function () {
    [$org, $owner] = organizationWith();
    $label = Label::factory()->for($org)->create();

    $this->actingAs($owner)->delete("/settings/labels/{$label->id}")
        ->assertRedirect(route('labels.index'));

    expect(Label::query()->find($label->id))->toBeNull();
});

it('forbids deleting a label you do not manage', function () {
    $label = Label::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete("/settings/labels/{$label->id}")
        ->assertForbidden();

    expect(Label::query()->find($label->id))->not->toBeNull();
});

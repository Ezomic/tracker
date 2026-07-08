<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\LabelColor;
use App\Models\Label;
use App\Models\Team;
use App\Models\User;

it('renders the labels settings page with issue counts', function () {
    $label = Label::factory()->create(['name' => 'bug', 'color' => 'red']);
    $team = Team::factory()->create();
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->labels()->attach($label);

    $this->actingAs(User::factory()->create())
        ->get('/settings/labels')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Labels')
            ->where('labels.0.name', 'bug')
            ->where('labels.0.color', 'red')
            ->where('labels.0.issuesCount', 1)
        );
});

it('creates a label', function () {
    $this->actingAs(User::factory()->create())
        ->post('/settings/labels', ['name' => 'bug', 'color' => 'red'])
        ->assertRedirect(route('labels.index'));

    expect(Label::query()->where('name', 'bug')->exists())->toBeTrue();
});

it('rejects a duplicate label name', function () {
    Label::factory()->create(['name' => 'bug']);

    $this->actingAs(User::factory()->create())
        ->post('/settings/labels', ['name' => 'bug', 'color' => 'blue'])
        ->assertSessionHasErrors('name');
});

it('rejects an invalid color', function () {
    $this->actingAs(User::factory()->create())
        ->post('/settings/labels', ['name' => 'bug', 'color' => 'not-a-color'])
        ->assertSessionHasErrors('color');
});

it('updates a label', function () {
    $label = Label::factory()->create(['name' => 'old', 'color' => 'gray']);

    $this->actingAs(User::factory()->create())
        ->patch("/settings/labels/{$label->id}", ['name' => 'new', 'color' => 'green'])
        ->assertRedirect(route('labels.index'));

    expect($label->fresh())
        ->name->toBe('new')
        ->color->toBe(LabelColor::Green);
});

it('allows keeping the same name when updating a label', function () {
    $label = Label::factory()->create(['name' => 'bug', 'color' => 'gray']);

    $this->actingAs(User::factory()->create())
        ->patch("/settings/labels/{$label->id}", ['name' => 'bug', 'color' => 'blue'])
        ->assertRedirect(route('labels.index'));

    expect($label->fresh()->color)->toBe(LabelColor::Blue);
});

it('deletes a label', function () {
    $label = Label::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete("/settings/labels/{$label->id}")
        ->assertRedirect(route('labels.index'));

    expect(Label::query()->find($label->id))->toBeNull();
});

<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\LabelColor;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;

it('renders the labels settings page with issue counts', function () {
    $team = Project::factory()->create();
    $user = member($team);
    $label = Label::factory()->for($user)->create(['name' => 'bug', 'color' => 'red']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->labels()->attach($label);

    $this->actingAs($user)
        ->get('/settings/labels')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Labels')
            ->where('labels.0.name', 'bug')
            ->where('labels.0.color', 'red')
            ->where('labels.0.issuesCount', 1)
        );
});

it('only lists the acting user own labels', function () {
    $mine = User::factory()->create();
    Label::factory()->for($mine)->create(['name' => 'mine']);
    Label::factory()->for(User::factory()->create())->create(['name' => 'theirs']);

    $this->actingAs($mine)
        ->get('/settings/labels')
        ->assertInertia(fn ($page) => $page
            ->has('labels', 1)
            ->where('labels.0.name', 'mine')
        );
});

it('creates a label owned by the acting user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/settings/labels', ['name' => 'bug', 'color' => 'red'])
        ->assertRedirect(route('labels.index'));

    expect(Label::query()->where('name', 'bug')->first()->user_id)->toBe($user->id);
});

it('rejects a duplicate label name for the same owner', function () {
    $user = User::factory()->create();
    Label::factory()->for($user)->create(['name' => 'bug']);

    $this->actingAs($user)
        ->post('/settings/labels', ['name' => 'bug', 'color' => 'blue'])
        ->assertSessionHasErrors('name');
});

it('allows the same label name for a different owner', function () {
    Label::factory()->for(User::factory()->create())->create(['name' => 'bug']);

    $this->actingAs(User::factory()->create())
        ->post('/settings/labels', ['name' => 'bug', 'color' => 'blue'])
        ->assertRedirect(route('labels.index'));

    expect(Label::query()->where('name', 'bug')->count())->toBe(2);
});

it('rejects an invalid color', function () {
    $this->actingAs(User::factory()->create())
        ->post('/settings/labels', ['name' => 'bug', 'color' => 'not-a-color'])
        ->assertSessionHasErrors('color');
});

it('updates a label', function () {
    $user = User::factory()->create();
    $label = Label::factory()->for($user)->create(['name' => 'old', 'color' => 'gray']);

    $this->actingAs($user)
        ->patch("/settings/labels/{$label->id}", ['name' => 'new', 'color' => 'green'])
        ->assertRedirect(route('labels.index'));

    expect($label->fresh())
        ->name->toBe('new')
        ->color->toBe(LabelColor::Green);
});

it('allows keeping the same name when updating a label', function () {
    $user = User::factory()->create();
    $label = Label::factory()->for($user)->create(['name' => 'bug', 'color' => 'gray']);

    $this->actingAs($user)
        ->patch("/settings/labels/{$label->id}", ['name' => 'bug', 'color' => 'blue'])
        ->assertRedirect(route('labels.index'));

    expect($label->fresh()->color)->toBe(LabelColor::Blue);
});

it('forbids editing someone else label', function () {
    $label = Label::factory()->for(User::factory()->create())->create(['name' => 'theirs']);

    $this->actingAs(User::factory()->create())
        ->patch("/settings/labels/{$label->id}", ['name' => 'hijacked', 'color' => 'red'])
        ->assertForbidden();

    expect($label->fresh()->name)->toBe('theirs');
});

it('deletes a label', function () {
    $user = User::factory()->create();
    $label = Label::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete("/settings/labels/{$label->id}")
        ->assertRedirect(route('labels.index'));

    expect(Label::query()->find($label->id))->toBeNull();
});

it('forbids deleting someone else label', function () {
    $label = Label::factory()->for(User::factory()->create())->create();

    $this->actingAs(User::factory()->create())
        ->delete("/settings/labels/{$label->id}")
        ->assertForbidden();

    expect(Label::query()->find($label->id))->not->toBeNull();
});

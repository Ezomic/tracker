<?php

declare(strict_types=1);

use App\Actions\SeedOrganizationStarterDataAction;
use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Enums\LabelColor;
use App\Enums\StatusCategory;
use App\Models\IssueTemplate;
use App\Models\Label;
use App\Models\Organization;
use App\Models\ProjectType;
use App\Models\User;
use App\Models\WorkflowState;
use Illuminate\Support\Facades\Mail;

function registeredOrganization(string $email): Organization
{
    Mail::fake();

    test()->post('/register', ['name' => 'Ada Lovelace', 'email' => $email]);

    return User::query()->where('email', $email)->firstOrFail()->organizations()->firstOrFail();
}

function verifiedOwnerOf(Organization $organization): User
{
    $owner = $organization->members()->firstOrFail();
    $owner->forceFill(['email_verified_at' => now()])->save();

    return $owner;
}

it('gives a newly registered organization the standard labels', function () {
    $organization = registeredOrganization('ada@example.com');

    $labels = $organization->labels()->orderBy('id')->get()
        ->mapWithKeys(fn (Label $label) => [$label->name => $label->color])
        ->all();

    expect($labels)->toBe([
        'backend' => LabelColor::Blue,
        'frontend' => LabelColor::Purple,
        'api' => LabelColor::Blue,
        'database' => LabelColor::Gray,
        'infra' => LabelColor::Gray,
        'ui' => LabelColor::Purple,
        'docs' => LabelColor::Green,
        'security' => LabelColor::Red,
        'performance' => LabelColor::Yellow,
        'tech-debt' => LabelColor::Yellow,
    ]);
});

it('gives a newly registered organization the default issue templates', function () {
    $organization = registeredOrganization('ada@example.com');

    $templates = $organization->issueTemplates()->orderBy('id')->get()
        ->map(fn (IssueTemplate $template) => [
            $template->name,
            $template->type,
            $template->priority,
            $template->description,
        ])
        ->all();

    expect($templates)->toBe([
        ['Bug report', IssueType::Fix, IssuePriority::High, "## Steps to reproduce\n1. \n\n## Expected\n\n## Actual\n\n## Environment\n"],
        ['Feature request', IssueType::Feature, null, "## Problem\n\n## Proposed solution\n\n## Acceptance criteria\n- [ ] \n"],
        ['Chore', IssueType::Feature, null, "## What\n\n## Why\n"],
        ['Spike', IssueType::Feature, null, "## Question\n\n## Timebox\n\n## Findings\n"],
    ]);
});

it('gives a newly registered organization the default Engineering project type', function () {
    $organization = registeredOrganization('ada@example.com');

    $type = $organization->projectTypes()->sole();

    expect($type->name)->toBe('Engineering')
        ->and($type->description)->toBe('Backlog, In Progress, In Review, Done.')
        ->and($type->is_default)->toBeTrue()
        ->and($type->states()->orderBy('position')->get()->map(fn (WorkflowState $state) => [
            $state->name,
            $state->category,
            $state->color,
            $state->position,
            $state->is_default,
        ])->all())->toBe([
            ['Backlog', StatusCategory::Backlog, '#9ca3af', 0, true],
            ['In Progress', StatusCategory::Started, '#d85a30', 1, false],
            ['In Review', StatusCategory::Started, '#378add', 2, false],
            ['Done', StatusCategory::Completed, '#1d9e75', 3, false],
        ]);
});

it('files the first project of a new organization under the Engineering type', function () {
    $organization = registeredOrganization('ada@example.com');
    $owner = verifiedOwnerOf($organization);

    $this->actingAs($owner)
        ->post('/projects', ['key' => 'SHOP', 'name' => 'Web shop'])
        ->assertRedirect();

    expect($organization->projects()->sole()->project_type_id)
        ->toBe($organization->projectTypes()->sole()->id);
});

it('never duplicates the starter data when it runs again', function () {
    $organization = Organization::factory()->create();
    $action = app(SeedOrganizationStarterDataAction::class);

    $action->handle($organization);
    $action->handle($organization);

    expect($organization->labels()->count())->toBe(10)
        ->and($organization->issueTemplates()->count())->toBe(4)
        ->and($organization->projectTypes()->count())->toBe(1)
        ->and($organization->projectTypes()->sole()->states()->count())->toBe(4);
});

it('never overwrites a label or template the organization already has', function () {
    $organization = Organization::factory()->create();
    $organization->labels()->create(['name' => 'backend', 'color' => LabelColor::Red]);
    $organization->issueTemplates()->create([
        'name' => 'Bug report',
        'description' => 'Our own bug template',
        'type' => IssueType::Fix,
        'priority' => IssuePriority::Urgent,
    ]);

    app(SeedOrganizationStarterDataAction::class)->handle($organization);

    expect($organization->labels()->where('name', 'backend')->sole()->color)->toBe(LabelColor::Red)
        ->and($organization->labels()->count())->toBe(10)
        ->and($organization->issueTemplates()->where('name', 'Bug report')->sole())
        ->description->toBe('Our own bug template')
        ->priority->toBe(IssuePriority::Urgent)
        ->and($organization->issueTemplates()->count())->toBe(4);
});

it('adds no Engineering type to an organization that already has project types', function () {
    $organization = Organization::factory()->create();
    $organization->projectTypes()->create(['name' => 'Support', 'is_default' => true]);

    app(SeedOrganizationStarterDataAction::class)->handle($organization);

    expect($organization->projectTypes()->pluck('name')->all())->toBe(['Support']);
});

it('gives each new organization its own starter set that the other cannot see', function () {
    $first = registeredOrganization('ada@example.com');
    $second = registeredOrganization('grace@example.com');

    expect($first->is($second))->toBeFalse();

    foreach ([$first, $second] as $organization) {
        expect($organization->labels()->count())->toBe(10)
            ->and($organization->issueTemplates()->count())->toBe(4)
            ->and($organization->projectTypes()->count())->toBe(1)
            ->and($organization->projectTypes()->sole()->states()->count())->toBe(4);
    }

    expect(Label::query()->count())->toBe(20)
        ->and(IssueTemplate::query()->count())->toBe(8)
        ->and(ProjectType::query()->count())->toBe(2)
        ->and(WorkflowState::query()->count())->toBe(8);

    $firstOwner = verifiedOwnerOf($first);

    $this->actingAs($firstOwner)
        ->get('/settings/labels')
        ->assertInertia(fn ($page) => $page->where(
            'labels',
            fn ($labels) => collect($labels)->pluck('id')->sort()->values()->all()
                === $first->labels()->orderBy('id')->pluck('id')->all(),
        ));

    $this->actingAs($firstOwner)
        ->get('/settings/templates')
        ->assertInertia(fn ($page) => $page->where(
            'templates',
            fn ($templates) => collect($templates)->pluck('id')->sort()->values()->all()
                === $first->issueTemplates()->orderBy('id')->pluck('id')->all(),
        ));

    $this->actingAs($firstOwner)
        ->get('/settings/project-types')
        ->assertInertia(fn ($page) => $page->where(
            'types',
            fn ($types) => collect($types)->pluck('id')->all() === [$first->projectTypes()->sole()->id],
        ));

    $foreignLabel = $second->labels()->where('name', 'backend')->sole();
    $foreignTemplate = $second->issueTemplates()->where('name', 'Bug report')->sole();
    $foreignType = $second->projectTypes()->sole();

    $refused = [403, 404];

    expect($this->actingAs($firstOwner)
        ->patch("/settings/labels/{$foreignLabel->id}", ['name' => 'hijacked', 'color' => 'red'])
        ->status())->toBeIn($refused)
        ->and($this->actingAs($firstOwner)
            ->delete("/settings/templates/{$foreignTemplate->id}")
            ->status())->toBeIn($refused)
        ->and($this->actingAs($firstOwner)
            ->delete("/settings/project-types/{$foreignType->id}")
            ->status())->toBeIn($refused);

    expect($foreignLabel->fresh()->name)->toBe('backend')
        ->and($foreignTemplate->fresh())->not->toBeNull()
        ->and($foreignType->fresh())->not->toBeNull();
});

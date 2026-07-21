<?php

declare(strict_types=1);

use App\Actions\SpawnRecurringIssuesAction;
use App\Enums\Cadence;
use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\IssueTemplate;
use App\Models\Label;
use App\Models\Project;
use Carbon\CarbonImmutable;

function recurringTemplate(Project $project, array $attributes = []): IssueTemplate
{
    $template = IssueTemplate::query()->create(array_merge([
        'name' => 'Weekly chores',
        'description' => 'Do the chores',
        'type' => IssueType::Fix,
        'priority' => IssuePriority::High,
        'cadence' => Cadence::Weekly,
        'target_project_id' => $project->id,
        'next_run_at' => CarbonImmutable::now()->subDay(),
    ], $attributes));

    // organization_id is stamped via the relationship, not mass-assigned.
    $template->forceFill(['organization_id' => $project->organization_id])->save();

    return $template;
}

it('spawns an issue from a due template with its defaults', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $label = Label::factory()->create(['organization_id' => $project->organization_id]);
    $template = recurringTemplate($project);
    $template->labels()->sync([$label->id]);

    $spawned = app(SpawnRecurringIssuesAction::class)->handle();

    expect($spawned)->toBe(1);

    $issue = $project->issues()->firstOrFail();
    expect($issue->title)->toBe('Weekly chores')
        ->and($issue->type)->toBe(IssueType::Fix)
        ->and($issue->priority)->toBe(IssuePriority::High)
        ->and($issue->labels->pluck('id')->all())->toBe([$label->id]);
});

it('advances next_run_at to a single future occurrence even after a long gap', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $now = CarbonImmutable::now();
    $template = recurringTemplate($project, ['next_run_at' => $now->subWeeks(4)]);

    $spawned = app(SpawnRecurringIssuesAction::class)->handle($now);

    expect($spawned)->toBe(1)
        // Only one issue despite four missed weeks.
        ->and($project->issues()->count())->toBe(1)
        ->and($template->fresh()->next_run_at->greaterThan($now))->toBeTrue();
});

it('does not spawn a template whose next run is in the future', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    recurringTemplate($project, ['next_run_at' => CarbonImmutable::now()->addWeek()]);

    expect(app(SpawnRecurringIssuesAction::class)->handle())->toBe(0)
        ->and($project->issues()->count())->toBe(0);
});

it('ignores non-recurring templates', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    recurringTemplate($project, ['cadence' => Cadence::None, 'next_run_at' => null, 'target_project_id' => null]);

    expect(app(SpawnRecurringIssuesAction::class)->handle())->toBe(0);
});

it('runs from the scheduled command', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    recurringTemplate($project);

    $this->artisan('issues:spawn-recurring')
        ->expectsOutputToContain('Spawned 1 recurring issue(s).')
        ->assertSuccessful();

    expect($project->issues()->count())->toBe(1);
});

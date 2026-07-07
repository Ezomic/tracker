<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Team;

it('numbers issues sequentially per team starting from 1', function () {
    $team = Team::factory()->create(['key' => 'THI', 'next_number' => 0]);
    $action = new CreateIssueAction;

    $first = $action->handle($team, 'First issue', IssueType::Feature);
    $second = $action->handle($team, 'Second issue', IssueType::Feature);

    expect($first->number)->toBe(1)
        ->and($first->identifier)->toBe('THI-1')
        ->and($second->number)->toBe(2)
        ->and($second->identifier)->toBe('THI-2')
        ->and($team->fresh()->next_number)->toBe(2);
});

it('keeps numbering independent per team', function () {
    $thi = Team::factory()->create(['key' => 'THI', 'next_number' => 5]);
    $billr = Team::factory()->create(['key' => 'BILLR', 'next_number' => 0]);
    $action = new CreateIssueAction;

    $thiIssue = $action->handle($thi, 'THI issue', IssueType::Feature);
    $billrIssue = $action->handle($billr, 'Billr issue', IssueType::Feature);

    expect($thiIssue->identifier)->toBe('THI-6')
        ->and($billrIssue->identifier)->toBe('BILLR-1');
});

it('derives a feature branch name from the type and title', function () {
    $team = Team::factory()->create(['key' => 'THI', 'next_number' => 0]);
    $action = new CreateIssueAction;

    $issue = $action->handle($team, 'Fix per-lesson quiz randomization', IssueType::Fix);

    expect($issue->branch_name)->toBe('fix/THI-1-fix-per-lesson-quiz-randomization')
        ->and($issue->status)->toBe(IssueStatus::Backlog);
});

it('truncates very long titles when building the branch slug', function () {
    $team = Team::factory()->create(['key' => 'THI', 'next_number' => 0]);
    $action = new CreateIssueAction;

    $title = 'This is an extremely long issue title that goes on and on well past a reasonable branch name length';
    $issue = $action->handle($team, $title, IssueType::Feature);

    expect(strlen($issue->slug))->toBeLessThanOrEqual(50);
});

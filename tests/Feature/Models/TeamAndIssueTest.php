<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Database\QueryException;

it('relates issues to their team', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = Issue::factory()->for($team)->create();

    expect($issue->project->is($team))->toBeTrue()
        ->and($team->issues->first()->is($issue))->toBeTrue();
});

it('casts issue type and status to their backing enums', function () {
    $issue = Issue::factory()->create([
        'type' => IssueType::Fix,
        'status' => IssueStatus::InReview,
    ]);

    expect($issue->fresh()->type)->toBe(IssueType::Fix)
        ->and($issue->fresh()->status)->toBe(IssueStatus::InReview);
});

it('enforces a unique identifier', function () {
    Issue::factory()->create(['identifier' => 'THI-1']);

    Issue::factory()->create(['identifier' => 'THI-1']);
})->throws(QueryException::class);

it('enforces a unique number per team', function () {
    $team = Project::factory()->create();

    Issue::factory()->for($team)->create(['number' => 1]);
    Issue::factory()->for($team)->create(['number' => 1]);
})->throws(QueryException::class);

<?php

declare(strict_types=1);

use App\Actions\ReassignIssuesAction;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;

function makeIssue(Project $project, int $number, array $overrides = []): Issue
{
    return Issue::factory()->for($project)->create(array_merge([
        'number' => $number,
        'identifier' => "{$project->key}-{$number}",
    ], $overrides));
}

it('moves issues into a new project, renumbering 1..N in original-number order', function () {
    $thi = Project::factory()->create(['key' => 'THI', 'next_number' => 300]);
    $hab = Project::factory()->create(['key' => 'HAB', 'next_number' => 0]);
    makeIssue($thi, 186, ['title' => 'Second hablas ticket']);
    makeIssue($thi, 182, ['title' => 'First hablas ticket']);

    $result = (new ReassignIssuesAction)->handle([
        'THI-186' => 'HAB',
        'THI-182' => 'HAB',
    ]);

    expect($result['moved'])->toBe(2);
    // lowest original number becomes HAB-1
    $first = Issue::where('title', 'First hablas ticket')->first();
    $second = Issue::where('title', 'Second hablas ticket')->first();
    expect($first->identifier)->toBe('HAB-1')
        ->and($first->number)->toBe(1)
        ->and($first->project_id)->toBe($hab->id)
        ->and($second->identifier)->toBe('HAB-2');
    expect($hab->fresh()->next_number)->toBe(2);
});

it('regenerates identifier and branch_name but preserves content, type, status, and dates', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    Project::factory()->create(['key' => 'CMS', 'next_number' => 0]);
    $issue = makeIssue($thi, 99, [
        'title' => 'SEO hreflang and JSON-LD',
        'description' => 'Original description kept.',
        'type' => IssueType::Fix,
        'created_at' => '2026-01-02 03:04:05',
    ]);

    (new ReassignIssuesAction)->handle(['THI-99' => 'CMS']);

    $issue->refresh();
    expect($issue->identifier)->toBe('CMS-1')
        ->and($issue->branch_name)->toBe('fix/CMS-1-seo-hreflang-and-json-ld')
        ->and($issue->description)->toBe('Original description kept.')
        ->and($issue->type)->toBe(IssueType::Fix)
        ->and($issue->created_at->format('Y-m-d H:i:s'))->toBe('2026-01-02 03:04:05');
});

it('appends to an existing project at next_number without disturbing its issues', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    $billr = Project::factory()->create(['key' => 'BILLR', 'next_number' => 7]);
    $existing = makeIssue($billr, 7, ['title' => 'Existing billr ticket']);
    makeIssue($thi, 44, ['title' => 'Multi-workspace switching']);

    (new ReassignIssuesAction)->handle(['THI-44' => 'BILLR']);

    expect($existing->fresh()->identifier)->toBe('BILLR-7'); // untouched
    $moved = Issue::where('title', 'Multi-workspace switching')->first();
    expect($moved->identifier)->toBe('BILLR-8')
        ->and($moved->number)->toBe(8);
    expect($billr->fresh()->next_number)->toBe(8);
});

it('skips issues already in their target project', function () {
    $cms = Project::factory()->create(['key' => 'CMS', 'next_number' => 5]);
    makeIssue($cms, 3, ['title' => 'Already CMS']);

    $result = (new ReassignIssuesAction)->handle(['CMS-3' => 'CMS']);

    expect($result['moved'])->toBe(0)
        ->and($result['skipped'])->toBe(1);
    expect(Issue::where('title', 'Already CMS')->first()->identifier)->toBe('CMS-3');
});

it('reports identifiers that do not exist', function () {
    Project::factory()->create(['key' => 'HAB', 'next_number' => 0]);

    $result = (new ReassignIssuesAction)->handle(['THI-9999' => 'HAB']);

    expect($result['moved'])->toBe(0)
        ->and($result['missing'])->toBe(['THI-9999']);
});

it('preserves parent/child relationships across the re-key', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    Project::factory()->create(['key' => 'ST', 'next_number' => 0]);
    $epic = makeIssue($thi, 315, ['title' => 'Content epic']);
    $child = makeIssue($thi, 316, ['title' => 'Child ticket', 'parent_id' => $epic->id]);

    (new ReassignIssuesAction)->handle(['THI-315' => 'ST', 'THI-316' => 'ST']);

    $epic->refresh();
    $child->refresh();
    expect($epic->identifier)->toBe('ST-1')
        ->and($child->identifier)->toBe('ST-2')
        ->and($child->parent_id)->toBe($epic->id); // relationship intact
});

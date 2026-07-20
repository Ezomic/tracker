<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\Project;

function runDuplicateReasonsMigration(): void
{
    (require base_path('database/migrations/2026_07_20_180000_record_duplicate_archive_reasons.php'))->up();
}

function stIssue(Project $project, int $number, string $title, array $overrides = []): Issue
{
    return Issue::factory()->for($project)->create(array_merge([
        'identifier' => "ST-{$number}",
        'number' => $number,
        'title' => $title,
        'archived_at' => now(),
        'archive_reason' => null,
    ], $overrides));
}

/** ST-46 is a real row: duplicate of ST-67, titled "Epic: More Content, Better Practice". */
function duplicatePair(Project $project): Issue
{
    stIssue($project, 67, 'Epic: More Content, Better Practice');

    return stIssue($project, 46, 'Epic: More Content, Better Practice');
}

it('records the reason on a duplicate', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'ST']);
    $duplicate = duplicatePair($project);

    runDuplicateReasonsMigration();

    expect($duplicate->fresh()->archive_reason)
        ->toBe('Duplicate of ST-67, which covers the same work.');
});

it('does not change status or archived_at', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'ST']);
    $duplicate = duplicatePair($project);
    $before = $duplicate->fresh();

    runDuplicateReasonsMigration();

    expect($duplicate->fresh())
        ->status->toBe($before->status)
        ->archived_at->toEqual($before->archived_at);
});

it('leaves an existing reason alone', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'ST']);
    stIssue($project, 67, 'Epic: More Content, Better Practice');
    $duplicate = stIssue($project, 46, 'Epic: More Content, Better Practice', [
        'archive_reason' => 'Closed for a reason someone actually wrote',
    ]);

    runDuplicateReasonsMigration();

    expect($duplicate->fresh()->archive_reason)
        ->toBe('Closed for a reason someone actually wrote');
});

it('skips an issue that is not archived', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'ST']);
    stIssue($project, 67, 'Epic: More Content, Better Practice');
    $duplicate = stIssue($project, 46, 'Epic: More Content, Better Practice', ['archived_at' => null]);

    runDuplicateReasonsMigration();

    expect($duplicate->fresh()->archive_reason)->toBeNull();
});

it('skips when the title no longer matches', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'ST']);
    stIssue($project, 67, 'Epic: More Content, Better Practice');
    $duplicate = stIssue($project, 46, 'Something else entirely');

    runDuplicateReasonsMigration();

    expect($duplicate->fresh()->archive_reason)->toBeNull();
});

it('skips when the superseding issue does not exist', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'ST']);
    $duplicate = stIssue($project, 46, 'Epic: More Content, Better Practice');

    runDuplicateReasonsMigration();

    expect($duplicate->fresh()->archive_reason)->toBeNull();
});

it('is idempotent when run twice', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'ST']);
    $duplicate = duplicatePair($project);

    runDuplicateReasonsMigration();
    runDuplicateReasonsMigration();

    expect($duplicate->fresh()->archive_reason)
        ->toBe('Duplicate of ST-67, which covers the same work.');
});

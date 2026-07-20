<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Project;
use App\Models\TimeEntry;

function runDeletePlaceholdersMigration(): void
{
    (require base_path('database/migrations/2026_07_20_160000_delete_placeholder_issues.php'))->up();
}

function placeholder(Project $project, string $identifier, int $number, string $title): Issue
{
    return Issue::factory()->for($project)->create([
        'identifier' => $identifier,
        'number' => $number,
        'title' => $title,
        'archived_at' => now(),
    ]);
}

it('deletes a placeholder whose title matches', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $issue = placeholder($project, 'THI-5', 5, 'test');

    runDeletePlaceholdersMigration();

    expect(Issue::query()->find($issue->id))->toBeNull();
});

it('also clears the label rows attached to it', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $issue = placeholder($project, 'THI-5', 5, 'test');
    $issue->labels()->attach(Label::factory()->for($org)->create());

    runDeletePlaceholdersMigration();

    expect(DB::table('issue_label')->where('issue_id', $issue->id)->count())->toBe(0);
});

it('refuses to delete when the title does not match', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $issue = placeholder($project, 'THI-5', 5, 'Real work that got this identifier later');

    runDeletePlaceholdersMigration();

    expect(Issue::query()->find($issue->id))->not->toBeNull();
});

it('refuses to delete an issue that has comments', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $issue = placeholder($project, 'THI-5', 5, 'test');
    Comment::factory()->create(['issue_id' => $issue->id, 'user_id' => $owner->id]);

    runDeletePlaceholdersMigration();

    expect(Issue::query()->find($issue->id))->not->toBeNull();
});

it('refuses to delete an issue that has logged time', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $issue = placeholder($project, 'THI-5', 5, 'test');
    TimeEntry::factory()->create(['issue_id' => $issue->id, 'user_id' => $owner->id]);

    runDeletePlaceholdersMigration();

    expect(Issue::query()->find($issue->id))->not->toBeNull();
});

it('refuses to delete an issue that has sub-issues', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $issue = placeholder($project, 'THI-5', 5, 'test');
    Issue::factory()->for($project)->create([
        'identifier' => 'THI-500',
        'number' => 500,
        'parent_id' => $issue->id,
    ]);

    runDeletePlaceholdersMigration();

    expect(Issue::query()->find($issue->id))->not->toBeNull();
});

it('leaves unrelated issues untouched', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $keep = placeholder($project, 'THI-346', 346, 'Fix LanguageFactory code collision with seeded languages');

    runDeletePlaceholdersMigration();

    expect(Issue::query()->find($keep->id))->not->toBeNull();
});

it('is idempotent when run twice', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    placeholder($project, 'THI-5', 5, 'test');

    runDeletePlaceholdersMigration();
    runDeletePlaceholdersMigration();

    expect(Issue::query()->count())->toBe(0);
});

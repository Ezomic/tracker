<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\Project;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project);
    config(['issue_sources.snag' => ['label' => 'Snag', 'url' => 'https://snag.test/reports/:ref']]);
});

it('has no originating report for a hand-filed issue', function () {
    $issue = Issue::factory()->for($this->project)->create();

    expect($issue->originatingReport())->toBeNull();
});

it('builds a link back to the originating record', function () {
    $issue = Issue::factory()->for($this->project)->create([
        'source' => 'snag',
        'external_ref' => '123',
    ]);

    expect($issue->originatingReport())->toBe([
        'source' => 'snag',
        'label' => 'Snag',
        'reference' => '123',
        'url' => 'https://snag.test/reports/123',
    ]);
});

it('escapes a reference that would otherwise break the url', function () {
    $issue = Issue::factory()->for($this->project)->create([
        'source' => 'snag',
        'external_ref' => 'a b/c?d',
    ]);

    expect($issue->originatingReport()['url'])->toBe('https://snag.test/reports/a%20b%2Fc%3Fd');
});

it('renders an unmapped source without a link rather than hiding it', function () {
    $issue = Issue::factory()->for($this->project)->create([
        'source' => 'some_new_app',
        'external_ref' => '7',
    ]);

    expect($issue->originatingReport())->toBe([
        'source' => 'some_new_app',
        'label' => 'Some New App',
        'reference' => '7',
        'url' => null,
    ]);
});

it('omits the link when the source is mapped but carries no reference', function () {
    $issue = Issue::factory()->for($this->project)->create([
        'source' => 'snag',
        'external_ref' => null,
    ]);

    expect($issue->originatingReport()['url'])->toBeNull()
        ->and($issue->originatingReport()['label'])->toBe('Snag');
});

it('sends the originating report to the issue page', function () {
    $issue = Issue::factory()->for($this->project)->create([
        'identifier' => 'THI-1',
        'source' => 'snag',
        'external_ref' => '123',
    ]);

    $this->actingAs($this->user)
        ->get("/issues/{$issue->identifier}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('issue.originatingReport.label', 'Snag')
            ->where('issue.originatingReport.url', 'https://snag.test/reports/123'));
});

it('sends it to the issue list too, so the badge can render', function () {
    Issue::factory()->for($this->project)->create([
        'identifier' => 'THI-1',
        'source' => 'snag',
        'external_ref' => '123',
    ]);

    $this->actingAs($this->user)
        ->get('/issues')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('issues.0.originatingReport.label', 'Snag'));
});

it('leaves the list field null for hand-filed issues', function () {
    Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $this->actingAs($this->user)
        ->get('/issues')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('issues.0.originatingReport', null));
});

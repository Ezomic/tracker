<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function fileExternally(User $user, array $overrides = []): TestResponse
{
    return test()->actingAs($user, 'sanctum')->postJson('/api/issues', array_merge([
        'project' => 'THI',
        'title' => 'Total is wrong on the invoice screen',
        'type' => 'fix',
        'source' => 'snag',
        'external_ref' => '123',
    ], $overrides));
}

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project);
});

it('records source and external_ref on a new issue', function () {
    $response = fileExternally($this->user);

    $response->assertCreated()->assertJson([
        'source' => 'snag',
        'external_ref' => '123',
    ]);

    $issue = Issue::query()->firstOrFail();
    expect($issue->source)->toBe('snag')
        ->and($issue->external_ref)->toBe('123');
});

it('returns the existing issue when the same reference is filed again', function () {
    $first = fileExternally($this->user)->assertCreated();

    $second = fileExternally($this->user, ['title' => 'A retry with a different title']);

    $second->assertOk();
    expect($second->json('identifier'))->toBe($first->json('identifier'))
        ->and(Issue::query()->count())->toBe(1);
});

it('does not burn a project number on a repeat filing', function () {
    fileExternally($this->user)->assertCreated();
    $numberAfterFirst = $this->project->fresh()->next_number;

    fileExternally($this->user)->assertOk();

    expect($this->project->fresh()->next_number)->toBe($numberAfterFirst);
});

it('treats the same ref in a different project as a different issue', function () {
    $other = Project::factory()->create(['key' => 'BILLR']);
    joinProjects($this->user, $other);

    fileExternally($this->user)->assertCreated();
    fileExternally($this->user, ['project' => 'BILLR'])->assertCreated();

    expect(Issue::query()->count())->toBe(2);
});

it('treats the same ref from a different source as a different issue', function () {
    fileExternally($this->user)->assertCreated();
    fileExternally($this->user, ['source' => 'flare'])->assertCreated();

    expect(Issue::query()->count())->toBe(2);
});

it('leaves hand-filed issues unconstrained', function () {
    foreach (range(1, 3) as $n) {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/issues', [
            'project' => 'THI',
            'title' => "Filed by hand {$n}",
            'type' => 'feature',
        ])->assertCreated();
    }

    expect(Issue::query()->count())->toBe(3)
        ->and(Issue::query()->whereNotNull('source')->count())->toBe(0);
});

it('rejects a source without a reference', function () {
    fileExternally($this->user, ['external_ref' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('external_ref');
});

it('rejects a reference without a source', function () {
    fileExternally($this->user, ['source' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('source');
});

it('filters the issue list by source', function () {
    fileExternally($this->user)->assertCreated();
    $this->actingAs($this->user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Filed by hand',
        'type' => 'feature',
    ])->assertCreated();

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues?source=snag');

    expect($response->json('data.*.source'))->toBe(['snag'])
        ->and($response->json('meta.total'))->toBe(1);
});

it('reports source and external_ref on the issue detail', function () {
    $identifier = fileExternally($this->user)->json('identifier');

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/issues/{$identifier}")
        ->assertOk()
        ->assertJsonFragment(['source' => 'snag', 'external_ref' => '123']);
});

it('does not hand back an issue from a project the caller cannot see', function () {
    $other = Project::factory()->create(['key' => 'BILLR']);
    $stranger = member($other);
    fileExternally($this->user)->assertCreated();

    // Same source and ref, but filed against a project this caller belongs to:
    // the lookup is project-scoped, so it must create rather than return.
    $response = fileExternally($stranger, ['project' => 'BILLR']);

    $response->assertCreated();
    expect($response->json('identifier'))->toStartWith('BILLR-');
});

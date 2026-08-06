<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;

it('lists issues ordered by project then number', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);
    Issue::factory()->for($thi)->create(['number' => 2, 'identifier' => 'THI-2', 'title' => 'Second']);
    Issue::factory()->for($thi)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'First']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues');

    $response->assertOk();
    expect($response->json('data.*.identifier'))->toBe(['THI-1', 'THI-2']);
    $response->assertJsonFragment([
        'identifier' => 'THI-1',
        'title' => 'First',
        'project' => 'THI',
    ]);
});

it('filters issues by project key', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    $billr = Project::factory()->create(['key' => 'BILLR']);
    joinProjects($user, [$thi, $billr]);
    Issue::factory()->for($thi)->create(['identifier' => 'THI-1']);
    Issue::factory()->for($billr)->create(['identifier' => 'BILLR-1']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues?project=THI');

    $response->assertOk();
    expect($response->json('data.*.identifier'))->toBe(['THI-1']);
});

it('excludes archived issues', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);
    Issue::factory()->for($thi)->create(['identifier' => 'THI-1']);
    Issue::factory()->for($thi)->create(['identifier' => 'THI-2', 'archived_at' => now()]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues');

    expect($response->json('data.*.identifier'))->toBe(['THI-1']);
});

it('returns 422 for an unknown project filter', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/issues?project=NOPE')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('project');
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/issues')->assertUnauthorized();
});

it('carries the fields needed to triage without a second request', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);
    $label = Label::factory()->create(['name' => 'backend', 'organization_id' => $thi->organization_id]);
    $issue = Issue::factory()->for($thi)->create([
        'identifier' => 'THI-1',
        'priority' => IssuePriority::High,
        'assignee_id' => $user->id,
        'estimate_minutes' => 90,
        'status' => IssueStatus::Done,
        'closed_at' => now(),
    ]);
    $issue->labels()->sync([$label->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues');

    $response->assertOk();
    expect($response->json('data.0'))
        ->toMatchArray([
            'identifier' => 'THI-1',
            'priority' => 'high',
            'assignee' => $user->email,
            'estimate_minutes' => 90,
            'labels' => ['backend'],
        ])
        ->and($response->json('data.0.created_at'))->not->toBeNull()
        ->and($response->json('data.0.closed_at'))->not->toBeNull();
});

it('paginates with meta and honours per_page', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);

    foreach (range(1, 5) as $n) {
        Issue::factory()->for($thi)->create(['number' => $n, 'identifier' => "THI-{$n}"]);
    }

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues?per_page=2');

    $response->assertOk();
    expect($response->json('data.*.identifier'))->toBe(['THI-1', 'THI-2'])
        ->and($response->json('meta'))->toMatchArray([
            'total' => 5,
            'per_page' => 2,
            'current_page' => 1,
            'last_page' => 3,
        ]);

    $page2 = $this->actingAs($user, 'sanctum')->getJson('/api/issues?per_page=2&page=2');
    expect($page2->json('data.*.identifier'))->toBe(['THI-3', 'THI-4']);
});

it('rejects a per_page above the ceiling', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/issues?per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('filters by status, type and priority', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);
    Issue::factory()->for($thi)->create([
        'identifier' => 'THI-1',
        'status' => IssueStatus::Backlog,
        'type' => IssueType::Feature,
        'priority' => IssuePriority::Low,
    ]);
    Issue::factory()->for($thi)->create([
        'identifier' => 'THI-2',
        'status' => IssueStatus::Done,
        'type' => IssueType::Fix,
        'priority' => IssuePriority::Urgent,
    ]);

    $acting = $this->actingAs($user, 'sanctum');

    expect($acting->getJson('/api/issues?status=done')->json('data.*.identifier'))->toBe(['THI-2']);
    expect($acting->getJson('/api/issues?type=feature')->json('data.*.identifier'))->toBe(['THI-1']);
    expect($acting->getJson('/api/issues?priority=urgent')->json('data.*.identifier'))->toBe(['THI-2']);
});

it('rejects an unknown status filter', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/issues?status=nonsense')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('searches title, identifier and description', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);
    Issue::factory()->for($thi)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Widget alignment', 'description' => 'nothing here']);
    Issue::factory()->for($thi)->create(['number' => 2, 'identifier' => 'THI-2', 'title' => 'Unrelated', 'description' => 'mentions widget too']);
    Issue::factory()->for($thi)->create(['number' => 3, 'identifier' => 'THI-3', 'title' => 'Third', 'description' => null]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues?search=widget');

    expect($response->json('data.*.identifier'))->toBe(['THI-1', 'THI-2']);
});

it('filters by label name case-insensitively', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);
    $label = Label::factory()->create(['name' => 'backend', 'organization_id' => $thi->organization_id]);
    $tagged = Issue::factory()->for($thi)->create(['number' => 1, 'identifier' => 'THI-1']);
    $tagged->labels()->sync([$label->id]);
    Issue::factory()->for($thi)->create(['number' => 2, 'identifier' => 'THI-2']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues?label=BACKEND');

    expect($response->json('data.*.identifier'))->toBe(['THI-1']);
});

it('filters by assignee email and by none', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);
    Issue::factory()->for($thi)->create(['number' => 1, 'identifier' => 'THI-1', 'assignee_id' => $user->id]);
    Issue::factory()->for($thi)->create(['number' => 2, 'identifier' => 'THI-2', 'assignee_id' => null]);

    $acting = $this->actingAs($user, 'sanctum');

    expect($acting->getJson('/api/issues?assignee='.$user->email)->json('data.*.identifier'))->toBe(['THI-1']);
    expect($acting->getJson('/api/issues?assignee=none')->json('data.*.identifier'))->toBe(['THI-2']);
});

it('filters by parent epic', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);
    $epic = Issue::factory()->for($thi)->create(['number' => 1, 'identifier' => 'THI-1']);
    Issue::factory()->for($thi)->create(['number' => 2, 'identifier' => 'THI-2', 'parent_id' => $epic->id]);
    Issue::factory()->for($thi)->create(['number' => 3, 'identifier' => 'THI-3']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues?parent=THI-1');

    expect($response->json('data.*.identifier'))->toBe(['THI-2']);
});

it('never leaks issues from projects you are not a member of', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    $other = Project::factory()->create(['key' => 'OTHER']);
    joinProjects($user, $thi);
    Issue::factory()->for($thi)->create(['identifier' => 'THI-1', 'title' => 'Mine']);
    Issue::factory()->for($other)->create(['identifier' => 'OTHER-1', 'title' => 'Mine too']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues?search=Mine');

    expect($response->json('data.*.identifier'))->toBe(['THI-1'])
        ->and($response->json('meta.total'))->toBe(1);
});

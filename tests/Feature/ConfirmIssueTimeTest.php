<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectLevel;
use App\Jobs\ReportTimeToBillrJob;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    configureBillr();
});

it('confirms locally but queues nothing for Billr when Billr is not configured', function (string $key, ?string $value) {
    Queue::fake();
    Http::fake();
    config()->set("services.billr.{$key}", $value);

    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $issue->forceFill(['invoiceable' => true])->save();

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/confirm-time", [
            'minutes' => 30,
            'billr_client_name' => 'Acme BV',
        ])
        ->assertRedirect();

    expect($issue->fresh()->confirmed_minutes)->toBe(30);

    Queue::assertNothingPushed();
    Http::assertNothingSent();
})->with(['base_url', 'token'])->with('blank');

it('tells the issue page that Billr is off so it can hide the billing controls', function (?string $value) {
    config()->set('services.billr.token', $value);

    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)
        ->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page->where('integrations.billr', false));
})->with('blank');

it('tells the issue page that Billr is on when it is configured', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)
        ->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page->where('integrations.billr', true));
});

it('confirms time on a non-invoiceable issue without calling Billr', function () {
    Http::fake();

    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/confirm-time", ['minutes' => 90])
        ->assertRedirect();

    expect($issue->fresh()->confirmed_minutes)->toBe(90)
        ->and($issue->fresh()->confirmed_at)->not->toBeNull();

    Http::assertNothingSent();
});

it('reports confirmed time to Billr for an invoiceable issue in an unlinked project', function () {
    Http::fake([
        '*/api/time-entries' => Http::response([
            'time_entry_id' => 1,
            'project_id' => 55,
            'client_id' => 9,
            'billable' => true,
        ], 201),
    ]);

    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Acme Internal']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $issue->forceFill(['invoiceable' => true])->save();

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/confirm-time", [
            'minutes' => 90,
            'billr_client_name' => 'Acme BV',
        ])
        ->assertRedirect();

    expect($issue->fresh()->confirmed_minutes)->toBe(90)
        ->and($project->fresh()->billr_project_id)->toBe(55)
        ->and($project->fresh()->billr_client_id)->toBe(9);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://billr.test/api/time-entries'
            && $request['external_source'] === 'tracker'
            && $request['client_name'] === 'Acme BV'
            && $request['project_name'] === 'Acme Internal';
    });
});

it('sends the cached billr_project_id and no name fields once a project is linked', function () {
    Http::fake([
        '*/api/time-entries' => Http::response([
            'time_entry_id' => 2,
            'project_id' => 55,
            'client_id' => 9,
            'billable' => true,
        ], 201),
    ]);

    $project = Project::factory()->create(['key' => 'THI', 'billr_project_id' => 55, 'billr_client_id' => 9]);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $issue->forceFill(['invoiceable' => true])->save();

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/confirm-time", ['minutes' => 45])
        ->assertRedirect();

    Http::assertSent(function ($request) {
        return $request['billr_project_id'] === 55
            && ! isset($request['client_name'])
            && ! isset($request['project_name']);
    });
});

it('confirms locally and queues the Billr report without blocking the request', function () {
    Queue::fake();

    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $issue->forceFill(['invoiceable' => true])->save();

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/confirm-time", [
            'minutes' => 30,
            'billr_client_name' => 'Acme BV',
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'success');

    expect($issue->fresh()->confirmed_minutes)->toBe(30);

    Queue::assertPushed(
        ReportTimeToBillrJob::class,
        fn (ReportTimeToBillrJob $job): bool => $job->issue->is($issue)
            && $job->minutes === 30
            && $job->clientName === 'Acme BV',
    );
});

it('does not queue a Billr report for a non-invoiceable issue', function () {
    Queue::fake();

    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/confirm-time", ['minutes' => 90])
        ->assertRedirect();

    Queue::assertNotPushed(ReportTimeToBillrJob::class);
});

it('forbids a read member from confirming time', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $reader = member($project, ProjectLevel::Read);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($reader)
        ->post("/issues/{$issue->identifier}/confirm-time", ['minutes' => 30])
        ->assertForbidden();

    expect($issue->fresh()->confirmed_minutes)->toBeNull();
});

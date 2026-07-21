<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Actions\ReportTimeToBillrAction;
use App\Enums\IssueType;
use App\Jobs\ReportTimeToBillrJob;
use App\Models\Project;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.billr.base_url' => 'http://billr.test', 'services.billr.token' => 'test-token']);
});

it('reports the confirmed time to Billr when run', function () {
    Http::fake(['*/api/time-entries' => Http::response(['project_id' => 55, 'client_id' => 9], 201)]);

    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Internal']);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    (new ReportTimeToBillrJob($issue, 90, 'Acme BV'))->handle(new ReportTimeToBillrAction);

    expect($project->fresh()->billr_project_id)->toBe(55);
    Http::assertSent(fn ($request) => $request['minutes'] === 90 && $request['client_name'] === 'Acme BV');
});

it('throws so the queue retries when Billr is unreachable', function () {
    Http::fake(['*/api/time-entries' => Http::response([], 500)]);

    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    expect(fn () => (new ReportTimeToBillrJob($issue, 30, 'Acme BV'))->handle(new ReportTimeToBillrAction))
        ->toThrow(RequestException::class);
});
